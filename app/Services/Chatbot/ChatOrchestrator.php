<?php

namespace App\Services\Chatbot;

use App\Contracts\MessagingChannel;
use App\Data\ChannelIdentityStatus;
use App\Data\InboundMessage;
use App\Data\OutboundMessage;
use App\Models\ChatbotMessage;
use App\Services\ConversationStore;
use App\Services\EmergencyDetector;
use App\Services\HerbalChatbot;
use App\Services\MentalCrisisDetector;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
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
    ) {}

    public function handle(InboundMessage $message, MessagingChannel $channel): bool
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

        $incoming = $this->recordIncoming($message, $conversation->id, $identity->id, $integration->id);
        if (! $incoming->wasRecentlyCreated) {
            return $this->retryFailedReply($incoming, $message, $channel);
        }

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
                : $this->chatbot->reply($conversation->uuid, $message->text);
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

        $messages = $this->chatbot->outboundMessages($reply);
        $outgoing = collect($messages)->map(fn (string $text, int $index) => ChatbotMessage::query()->create([
            'chatbot_conversation_id' => $conversation->id,
            'reply_to_message_id' => $incoming->id,
            'chatbot_channel_identity_id' => $identity->id,
            'channel_integration_id' => $integration->id,
            'direction' => 'outgoing',
            'message_type' => 'text',
            'content' => $text,
            'processing_status' => 'completed',
            'delivery_status' => 'pending',
            'metadata' => ['sequence' => $index + 1, 'total' => count($messages)],
            'occurred_at' => now(),
            'processed_at' => now(),
        ]));

        $incoming->update(['processing_status' => 'completed', 'processed_at' => now()]);
        $this->syncConversationSummary($conversation, 1 + $outgoing->count());

        foreach ($outgoing as $outgoingMessage) {
            if (! $this->deliver($outgoingMessage, $message, $channel)) {
                return false;
            }
        }

        return true;
    }

    public function updateStatus(ChannelIdentityStatus $status): void
    {
        if ($this->persistenceAvailable()) {
            $this->contacts->updateStatus($status);
        }
    }

    private function recordIncoming(InboundMessage $message, int $conversationId, int $identityId, int $integrationId): ChatbotMessage
    {
        try {
            return ChatbotMessage::query()->create([
                'chatbot_conversation_id' => $conversationId,
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

        foreach ($replies->where('delivery_status', '!=', 'delivered') as $reply) {
            if (! $this->deliver($reply, $message, $channel)) {
                return false;
            }
        }

        return true;
    }

    private function deliver(ChatbotMessage $outgoing, InboundMessage $incoming, MessagingChannel $channel): bool
    {
        $result = $channel->send(new OutboundMessage(
            channel: $incoming->channel,
            integrationKey: $incoming->integrationKey,
            externalConversationId: $incoming->externalConversationId,
            text: $outgoing->content,
        ));

        if ($result->successful) {
            $outgoing->update([
                'external_message_id' => $result->externalMessageId,
                'delivery_status' => 'delivered',
                'delivered_at' => now(),
                'error_code' => null,
                'failed_at' => null,
            ]);

            return true;
        }

        $outgoing->update([
            'delivery_status' => 'failed',
            'error_code' => $result->errorCode,
            'failed_at' => now(),
        ]);

        if ($result->errorCode === 'telegram_403') {
            $outgoing->identity?->update(['status' => 'blocked']);
            $outgoing->identity?->contact?->update(['status' => 'blocked']);
        }

        Log::warning('Channel message delivery failed', [
            'channel' => $incoming->channel,
            'error_code' => $result->errorCode,
            'conversation_id' => $outgoing->chatbot_conversation_id,
        ]);

        return false;
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
