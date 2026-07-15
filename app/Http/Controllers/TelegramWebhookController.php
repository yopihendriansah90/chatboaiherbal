<?php

namespace App\Http\Controllers;

use App\Services\BotConfiguration;
use App\Services\Channels\ChannelEventReceiver;
use App\Services\Channels\TelegramChannel;
use App\Services\Chatbot\ChatOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        TelegramChannel $telegram,
        ChatOrchestrator $orchestrator,
        BotConfiguration $configuration,
        ChannelEventReceiver $events,
    ): JsonResponse {
        $configuredSecret = (string) $configuration->telegramWebhookSecret();
        $providedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            return response()->json(['ok' => false], 403);
        }

        $updateId = $request->integer('update_id');
        if ($updateId && Cache::has("telegram:update:{$updateId}")) {
            return response()->json(['ok' => true]);
        }

        $payload = $request->all();
        $status = $telegram->normalizeStatus($payload);
        $message = $status ? null : $telegram->normalize($payload);
        if (! $status && ! $message) {
            return response()->json(['ok' => true]);
        }

        if ($message && mb_strlen($message->text) > (int) config('chatbot.max_input_characters', 8000)) {
            return response()->json(['ok' => true]);
        }

        $identityKey = $message?->externalUserId ?? $status?->externalUserId ?? 'unknown';
        $rateKey = 'telegram:inbound:'.$identityKey;
        if (RateLimiter::tooManyAttempts($rateKey, (int) config('chatbot.rate_limit_per_minute', 30))) {
            return response()->json(['ok' => true]);
        }
        RateLimiter::hit($rateKey, 60);

        if (Schema::hasTable('channel_events')) {
            $eventId = (string) ($message?->eventId ?? $status?->eventId ?? $updateId);
            $event = $events->receive(
                channel: 'telegram',
                integrationKey: $telegram->key(),
                eventId: $eventId,
                eventType: $status ? 'identity_status' : 'message',
                payload: $payload,
            );

            return response()->json(['ok' => true, 'event' => $event->uuid]);
        }

        if ($status) {
            $orchestrator->updateStatus($status);
            if ($updateId) {
                Cache::put("telegram:update:{$updateId}", true, now()->addHours(24));
            }

            return response()->json(['ok' => true]);
        }

        try {
            $delivered = $orchestrator->handle($message, $telegram);
        } catch (Throwable $exception) {
            Log::warning('Telegram update processing failed', [
                'update_id' => $updateId,
                'exception' => $exception::class,
            ]);

            return response()->json(['ok' => false], 500);
        }
        if (! $delivered) {
            return response()->json(['ok' => false], 502);
        }

        if ($updateId) {
            Cache::put("telegram:update:{$updateId}", true, now()->addHours(24));
        }

        return response()->json(['ok' => true]);
    }
}
