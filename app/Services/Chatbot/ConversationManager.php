<?php

namespace App\Services\Chatbot;

use App\Models\ChannelIntegration;
use App\Models\ChatbotChannelIdentity;
use App\Models\ChatbotConversation;
use Illuminate\Support\Facades\DB;

class ConversationManager
{
    public function activeOrCreate(
        ChannelIntegration $integration,
        ChatbotChannelIdentity $identity,
        string $externalConversationId,
        bool $restart = false,
    ): ChatbotConversation {
        return DB::transaction(function () use ($integration, $identity, $externalConversationId, $restart): ChatbotConversation {
            $query = ChatbotConversation::query()
                ->where('channel_integration_id', $integration->id)
                ->where('external_conversation_id', $externalConversationId)
                ->where('status', 'active')
                ->lockForUpdate();

            if ($restart) {
                $query->update([
                    'status' => 'reset',
                    'closed_at' => now(),
                ]);
            } elseif ($conversation = $query->latest('id')->first()) {
                return $conversation;
            }

            return ChatbotConversation::query()->create([
                'chatbot_contact_id' => $identity->chatbot_contact_id,
                'chatbot_channel_identity_id' => $identity->id,
                'channel_integration_id' => $integration->id,
                'channel' => $identity->channel,
                'external_conversation_id' => $externalConversationId,
                'status' => 'active',
                'service_status' => 'bot_active',
                'bot_mode' => 'automatic',
                'priority' => 'normal',
                'started_at' => now(),
                'last_message_at' => now(),
            ]);
        });
    }
}
