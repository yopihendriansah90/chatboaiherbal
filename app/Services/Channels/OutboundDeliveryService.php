<?php

namespace App\Services\Channels;

use App\Data\OutboundMessage;
use App\Models\ChatbotMessage;
use Illuminate\Support\Facades\Log;

class OutboundDeliveryService
{
    public function deliver(ChatbotMessage $message, ChannelRegistry $channels): bool
    {
        $message->loadMissing(['conversation', 'identity', 'integration']);
        $channel = $channels->get($message->integration->key);
        $result = $channel->send(new OutboundMessage(
            channel: $message->conversation->channel,
            integrationKey: $message->integration->key,
            externalConversationId: $message->conversation->external_conversation_id,
            text: $message->content,
        ));

        if ($result->successful) {
            $message->update([
                'external_message_id' => $result->externalMessageId,
                'delivery_status' => 'delivered',
                'delivered_at' => now(),
                'error_code' => null,
                'failed_at' => null,
                'next_delivery_attempt_at' => null,
            ]);

            return true;
        }

        $message->update([
            'delivery_status' => $result->errorCode === 'telegram_403' ? 'dead' : 'failed',
            'error_code' => $result->errorCode,
            'failed_at' => now(),
        ]);

        if ($result->errorCode === 'telegram_403') {
            $message->identity?->update(['status' => 'blocked']);
            $message->identity?->contact?->update(['status' => 'blocked']);
        }

        Log::warning('Channel message delivery failed', [
            'channel' => $message->conversation->channel,
            'error_code' => $result->errorCode,
            'conversation_id' => $message->chatbot_conversation_id,
        ]);

        return false;
    }
}
