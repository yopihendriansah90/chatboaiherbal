<?php

namespace App\Jobs;

use App\Models\ChatbotMessage;
use App\Services\Channels\ChannelRegistry;
use App\Services\Channels\OutboundDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class DeliverOutboundMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 20;

    public int $timeout = 30;

    public function __construct(public int $chatbotMessageId)
    {
        $this->onQueue('outbound');
    }

    public function backoff(): array
    {
        return [2, 10, 30, 120];
    }

    public function handle(OutboundDeliveryService $delivery, ChannelRegistry $channels): void
    {
        $message = ChatbotMessage::query()->findOrFail($this->chatbotMessageId);
        if (in_array($message->delivery_status, ['delivered', 'dead'], true)) {
            return;
        }
        $earlierPending = ChatbotMessage::query()
            ->where('chatbot_conversation_id', $message->chatbot_conversation_id)
            ->where('direction', 'outgoing')
            ->where('id', '<', $message->id)
            ->whereNotIn('delivery_status', ['delivered', 'dead'])
            ->exists();
        if ($earlierPending) {
            $this->release(1);

            return;
        }

        if ($message->delivery_attempt_count >= 5) {
            $message->update(['delivery_status' => 'dead', 'failed_at' => now()]);

            return;
        }

        $message->increment('delivery_attempt_count');
        if (! $delivery->deliver($message->fresh(), $channels)) {
            $message->refresh();
            if ($message->delivery_status !== 'dead') {
                $message->update(['next_delivery_attempt_at' => now()->addSeconds($this->backoff()[min($this->attempts() - 1, 3)])]);
                throw new RuntimeException($message->error_code ?: 'channel_delivery_failed');
            }
        }
    }

    public function failed(): void
    {
        ChatbotMessage::query()->whereKey($this->chatbotMessageId)->update([
            'delivery_status' => 'dead',
            'failed_at' => now(),
        ]);
    }

    public function tags(): array
    {
        return ['outbound-message:'.$this->chatbotMessageId];
    }
}
