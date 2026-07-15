<?php

namespace App\Services\Chatbot;

use App\Contracts\MessagingChannel;
use App\Data\ChannelIdentityStatus;
use App\Data\InboundMessage;
use App\Data\OutboundMessage;
use App\Jobs\DeliverOutboundMessage;
use App\Models\ChatbotMessage;
use App\Services\ConversationStore;
use App\Services\CustomerMemoryService;
use App\Services\EmergencyDetector;
use App\Services\HerbalChatbot;
use App\Services\MentalCrisisDetector;
use App\Services\TrainingCandidateCollector;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ChatOrchestrator
{
    public function __construct(
        private ContactResolver $contacts,
        private ConversationManager $conversations,
        private HerbalChatbot $chatbot,
        private EmergencyDetector $emergencies,
        private MentalCrisisDetector $mentalCrises,
        private ConversationStore $store,
        private ChatbotRequestContext $requestContext,
        private ConversationOperations $operations,
        private CustomerMemoryService $memories,
        private ConsultationManager $consultations,
        private TrainingCandidateCollector $trainingCandidates,
    ) {}

    public function handle(InboundMessage $message, MessagingChannel $channel, ?string $correlationId = null): bool
    {
        if (! $this->persistenceAvailable()) {
            return $this->handleLegacy($message, $channel);
        }

        [$integration, $identity] = $this->contacts->resolve($message);
        $command = explode('@', strtolower(strtok($message->text, ' ')))[0];
        $restart = in_array($command, ['/start', '/reset'], true);
        $conversation = $this->conversations->activeOrCreate(
            $integration,
            $identity,
            $message->externalConversationId,
            $restart,
        );
        $this->memories->hydrateConversation($identity->contact, $conversation->uuid, $this->store);

        $incoming = $this->recordIncoming($message, $conversation->id, $identity->id, $integration->id, $correlationId);
        if (! $incoming->wasRecentlyCreated) {
            return $this->retryFailedReply($incoming, $message, $channel);
        }

        if ($conversation->bot_mode !== 'automatic') {
            $incoming->update(['processing_status' => 'completed', 'processed_at' => now()]);
            $this->syncConversationSummary($conversation, 1);

            return true;
        }

        $handoffRequested = $this->operations->shouldHandoff($message->text);
        $consultationOpener = $this->chatbot->isConsultationOpener($message->text);

        if (! $restart && ! $this->emergencies->detects($message->text) && ! $this->mentalCrises->detects($message->text) && ! $this->chatbot->isGreeting($message->text)) {
            try {
                $channel->sendActivity($message->externalConversationId);
            } catch (Throwable) {
                // Indikator aktivitas tidak boleh menggagalkan jawaban utama.
            }
        }

        $this->requestContext->set($conversation->id, $incoming->id);
        try {
            $reply = $restart
                ? $this->chatbot->reset($conversation->uuid)
                : ($handoffRequested
                    ? 'Baik kak, percakapan ini saya teruskan ke tim Customer Service. Mohon tunggu sebentar ya.'
                    : $this->chatbot->reply($conversation->uuid, $message->text));
        } catch (Throwable $exception) {
            $incoming->update([
                'processing_status' => 'failed',
                'error_code' => 'chatbot_processing_failed',
                'failed_at' => now(),
            ]);

            throw $exception;
        } finally {
            $this->requestContext->clear();
        }

        if ($handoffRequested) {
            $this->operations->requestHandoff($conversation, 'Pengguna meminta bantuan manusia.');
        }

        $messages = $this->chatbot->outboundMessages($reply);
        $state = $this->store->get($conversation->uuid);
        $case = $this->consultations->syncFromState(
            $conversation,
            $state,
            $incoming->id,
            $consultationOpener,
        );
        if ($case) {
            $incoming->update(['consultation_case_id' => $case->id]);
        }
        $decision = $state['last_decision'] ?? null;
        $outgoing = collect($messages)->map(fn (string $text, int $index) => ChatbotMessage::query()->create([
            'chatbot_conversation_id' => $conversation->id,
            'consultation_case_id' => $case?->id,
            'correlation_id' => $correlationId,
            'reply_to_message_id' => $incoming->id,
            'chatbot_channel_identity_id' => $identity->id,
            'channel_integration_id' => $integration->id,
            'direction' => 'outgoing',
            'message_type' => 'text',
            'content' => $text,
            'processing_status' => 'completed',
            'delivery_status' => 'pending',
            'metadata' => array_filter(['sequence' => $index + 1, 'total' => count($messages), 'decision' => $decision]),
            'occurred_at' => now(),
            'processed_at' => now(),
        ]));

        $incoming->update(['processing_status' => 'completed', 'processed_at' => now()]);
        $this->syncConversationSummary($conversation, 1 + $outgoing->count());
        $this->trainingCandidates->capture(
            $conversation,
            $incoming,
            $outgoing->first(),
            $reply,
            $state,
            $handoffRequested,
        );

        foreach ($outgoing as $outgoingMessage) {
            DeliverOutboundMessage::dispatch($outgoingMessage->id)->afterCommit();
        }

        return true;
    }

    public function updateStatus(ChannelIdentityStatus $status): void
    {
        if ($this->persistenceAvailable()) {
            $this->contacts->updateStatus($status);
        }
    }

    private function recordIncoming(InboundMessage $message, int $conversationId, int $identityId, int $integrationId, ?string $correlationId = null): ChatbotMessage
    {
        try {
            return ChatbotMessage::query()->create([
                'chatbot_conversation_id' => $conversationId,
                'correlation_id' => $correlationId,
                'chatbot_channel_identity_id' => $identityId,
                'channel_integration_id' => $integrationId,
                'external_event_id' => $message->eventId,
                'external_message_id' => $message->externalMessageId,
                'direction' => 'incoming',
                'message_type' => $message->messageType,
                'content' => $message->text,
                'processing_status' => 'processing',
                'delivery_status' => 'received',
                'occurred_at' => $message->occurredAt,
                'metadata' => ['channel' => $message->channel],
            ]);
        } catch (QueryException $exception) {
            $existing = ChatbotMessage::query()
                ->where('channel_integration_id', $integrationId)
                ->where('external_event_id', $message->eventId)
                ->first();
            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function retryFailedReply(ChatbotMessage $incoming, InboundMessage $message, MessagingChannel $channel): bool
    {
        $replies = ChatbotMessage::query()
            ->where('reply_to_message_id', $incoming->id)
            ->where('direction', 'outgoing')
            ->orderBy('id')
            ->get();
        if ($replies->isEmpty() && $incoming->processing_status === 'failed') {
            $incoming->delete();

            return $this->handle($message, $channel);
        }
        if ($replies->isEmpty() || $replies->every(fn (ChatbotMessage $reply): bool => $reply->delivery_status === 'delivered')) {
            return true;
        }

        foreach ($replies->whereNotIn('delivery_status', ['delivered', 'dead']) as $reply) {
            DeliverOutboundMessage::dispatch($reply->id)->afterCommit();
        }

        return true;
    }

    private function syncConversationSummary($conversation, int $messageIncrement): void
    {
        $state = $this->store->get($conversation->uuid);
        $conversation->update([
            'domain_code' => $state['active_domain'] ?? null,
            'category' => $state['facts']['category'] ?? null,
            'product_code' => collect($state['offered_products'] ?? [])->last(),
            'is_emergency' => in_array($state['phase'] ?? null, ['emergency', 'mental_crisis'], true),
            'message_count' => $conversation->message_count + $messageIncrement,
            'last_message_at' => now(),
        ]);
    }

    private function handleLegacy(InboundMessage $message, MessagingChannel $channel): bool
    {
        $command = explode('@', strtolower(strtok($message->text, ' ')))[0];
        $isCommand = in_array($command, ['/start', '/reset'], true);
        if (! $isCommand && ! $this->emergencies->detects($message->text) && ! $this->mentalCrises->detects($message->text) && ! $this->chatbot->isGreeting($message->text)) {
            try {
                $channel->sendActivity($message->externalConversationId);
            } catch (Throwable) {
                // Opsional.
            }
        }

        $reply = $isCommand
            ? $this->chatbot->reset($message->externalConversationId)
            : $this->chatbot->reply($message->externalConversationId, $message->text);

        foreach ($this->chatbot->outboundMessages($reply) as $text) {
            if (! $channel->send(new OutboundMessage(
                $message->channel,
                $message->integrationKey,
                $message->externalConversationId,
                $text,
            ))->successful) {
                return false;
            }
        }

        return true;
    }

    private function persistenceAvailable(): bool
    {
        try {
            return (bool) config('chatbot.history_enabled', true)
                && Schema::hasTable('channel_integrations')
                && Schema::hasTable('chatbot_contacts')
                && Schema::hasTable('chatbot_messages');
        } catch (Throwable) {
            return false;
        }
    }
}
