<?php

namespace App\Services\Channels;

use App\Jobs\ProcessChannelEvent;
use App\Models\ChannelEvent;
use Illuminate\Database\UniqueConstraintViolationException;

class ChannelEventReceiver
{
    public function receive(string $channel, string $integrationKey, string $eventId, string $eventType, array $payload): ChannelEvent
    {
        try {
            $event = ChannelEvent::query()->create([
                'channel' => $channel,
                'integration_key' => $integrationKey,
                'external_event_id' => $eventId,
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => 'pending',
            ]);
        } catch (UniqueConstraintViolationException) {
            return ChannelEvent::query()
                ->where('integration_key', $integrationKey)
                ->where('external_event_id', $eventId)
                ->firstOrFail();
        }

        ProcessChannelEvent::dispatch($event->id)->afterCommit();

        return $event;
    }
}
