<?php

namespace App\Jobs;

use App\Data\OutboundMessage;
use App\Models\ChannelEvent;
use App\Services\Channels\ChannelRegistry;
use App\Services\Chatbot\ChatOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessChannelEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 90;

    public function __construct(public int $channelEventId)
    {
        $this->onQueue('inbound');
    }

    public function backoff(): array
    {
        return [2, 10, 30, 120];
    }

    public function handle(ChannelRegistry $channels, ChatOrchestrator $orchestrator): void
    {
        $event = ChannelEvent::query()->findOrFail($this->channelEventId);
        if ($event->status === 'completed') {
            return;
        }

        $channel = $channels->get($event->integration_key);
        $payload = $event->payload;
        $status = $channel->normalizeStatus($payload);
        $message = $status ? null : $channel->normalize($payload);
        $conversationKey = $status?->externalConversationId ?? $message?->externalConversationId ?? $event->external_event_id;
        $lock = Cache::lock('chatbot:process:'.$event->integration_key.':'.$conversationKey, 120);

        if (! $lock->get()) {
            $this->release(2);

            return;
        }

        try {
            $event->update([
                'status' => 'processing',
                'attempt_count' => $event->attempt_count + 1,
                'processing_started_at' => now(),
                'error_code' => null,
            ]);

            if ($status) {
                $orchestrator->updateStatus($status);
            } elseif ($message) {
                if (($message->profile->metadata['chat_type'] ?? 'private') !== 'private') {
                    $channel->send(new OutboundMessage(
                        channel: $message->channel,
                        integrationKey: $message->integrationKey,
                        externalConversationId: $message->externalConversationId,
                        text: 'Untuk menjaga privasi, konsultasi kesehatan hanya dapat dilakukan melalui chat pribadi dengan bot ya, kak.',
                    ));
                } else {
                    $orchestrator->handle($message, $channel, $event->correlation_id);
                }
            }

            $event->update(['status' => 'completed', 'processed_at' => now(), 'failed_at' => null]);
        } catch (Throwable $exception) {
            $event->update([
                'status' => $this->attempts() >= $this->tries ? 'dead' : 'failed',
                'error_code' => class_basename($exception),
                'failed_at' => now(),
                'available_at' => now()->addSeconds($this->backoff()[min($this->attempts() - 1, 3)]),
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function tags(): array
    {
        return ['channel-event:'.$this->channelEventId];
    }

    public function failed(Throwable $exception): void
    {
        ChannelEvent::query()->whereKey($this->channelEventId)->update([
            'status' => 'dead',
            'error_code' => class_basename($exception),
            'failed_at' => now(),
        ]);
    }
}
