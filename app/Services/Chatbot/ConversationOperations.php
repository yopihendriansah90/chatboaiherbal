<?php

namespace App\Services\Chatbot;

use App\Jobs\DeliverOutboundMessage;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\ConversationEvent;
use App\Models\ConversationNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationOperations
{
    public function shouldHandoff(string $message): bool
    {
        return (bool) preg_match(
            '/\b(?:admin|agen|petugas|customer service|cs|orang|manusia)\b.*\b(?:bicara|hubungi|bantu|sambung|langsung|tanya|konsultasi)\b|\b(?:bicara|sambungkan|hubungkan|tanya|konsultasi)\b.*\b(?:admin|agen|cs|manusia)\b/iu',
            $message,
        );
    }

    public function requestHandoff(ChatbotConversation $conversation, string $reason, string $priority = 'normal', ?User $actor = null): void
    {
        $conversation->update([
            'service_status' => 'waiting_agent',
            'bot_mode' => 'paused',
            'priority' => $priority,
            'handoff_reason' => $reason,
            'waiting_since' => now(),
            'sla_due_at' => now()->addMinutes($priority === 'urgent' ? 5 : 30),
        ]);
        $this->event($conversation, 'handoff_requested', $actor, ['reason' => $reason, 'priority' => $priority]);
    }

    public function assign(ChatbotConversation $conversation, User $agent): void
    {
        $conversation->update([
            'service_status' => 'assigned',
            'bot_mode' => 'agent',
            'assigned_to' => $agent->id,
            'waiting_since' => null,
        ]);
        $this->event($conversation, 'assigned', $agent);
    }

    public function returnToBot(ChatbotConversation $conversation, User $actor): void
    {
        $conversation->update([
            'service_status' => 'bot_active',
            'bot_mode' => 'automatic',
            'assigned_to' => null,
            'handoff_reason' => null,
            'waiting_since' => null,
            'sla_due_at' => null,
        ]);
        $this->event($conversation, 'returned_to_bot', $actor);
    }

    public function pauseBot(ChatbotConversation $conversation, User $actor, string $reason): void
    {
        $conversation->update([
            'service_status' => 'waiting_agent',
            'bot_mode' => 'paused',
            'handoff_reason' => trim($reason),
            'waiting_since' => now(),
            'sla_due_at' => $conversation->sla_due_at ?? now()->addMinutes(30),
        ]);
        $this->event($conversation, 'bot_paused', $actor, ['reason' => trim($reason)]);
    }

    public function updateTags(ChatbotConversation $conversation, User $actor, array $tags): void
    {
        $tags = array_values(array_unique(array_filter(array_map('trim', $tags))));
        $conversation->update(['tags' => $tags]);
        $this->event($conversation, 'tags_updated', $actor, ['tags' => $tags]);
    }

    public function resolve(ChatbotConversation $conversation, User $actor, string $resolutionCode): void
    {
        $conversation->update([
            'service_status' => 'resolved',
            'bot_mode' => 'paused',
            'resolved_at' => now(),
            'resolution_code' => $resolutionCode,
        ]);
        $this->event($conversation, 'resolved', $actor, ['resolution_code' => $resolutionCode]);
    }

    public function addNote(ChatbotConversation $conversation, User $actor, string $content): ConversationNote
    {
        $note = ConversationNote::query()->create([
            'chatbot_conversation_id' => $conversation->id,
            'user_id' => $actor->id,
            'content' => trim($content),
        ]);
        $this->event($conversation, 'note_added', $actor);

        return $note;
    }

    public function sendAgentReply(ChatbotConversation $conversation, User $agent, string $content): ChatbotMessage
    {
        return DB::transaction(function () use ($conversation, $agent, $content): ChatbotMessage {
            if ($conversation->bot_mode !== 'agent' || (int) $conversation->assigned_to !== (int) $agent->id) {
                $this->assign($conversation, $agent);
                $conversation->refresh();
            }

            $message = ChatbotMessage::query()->create([
                'chatbot_conversation_id' => $conversation->id,
                'correlation_id' => (string) Str::uuid(),
                'chatbot_channel_identity_id' => $conversation->chatbot_channel_identity_id,
                'channel_integration_id' => $conversation->channel_integration_id,
                'direction' => 'outgoing',
                'message_type' => 'text',
                'content' => trim($content),
                'processing_status' => 'completed',
                'delivery_status' => 'pending',
                'metadata' => ['source' => 'agent', 'agent_id' => $agent->id],
                'occurred_at' => now(),
                'processed_at' => now(),
            ]);
            $conversation->increment('message_count');
            $conversation->update(['last_message_at' => now(), 'service_status' => 'waiting_customer']);
            $this->event($conversation, 'agent_replied', $agent, ['message_uuid' => $message->uuid]);
            DeliverOutboundMessage::dispatch($message->id)->afterCommit();

            return $message;
        });
    }

    private function event(ChatbotConversation $conversation, string $type, ?User $actor = null, array $metadata = []): void
    {
        ConversationEvent::query()->create([
            'chatbot_conversation_id' => $conversation->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }
}
