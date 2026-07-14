<?php

namespace App\Http\Controllers;

use App\Services\BotConfiguration;
use App\Services\Channels\TelegramChannel;
use App\Services\Chatbot\ChatOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        TelegramChannel $telegram,
        ChatOrchestrator $orchestrator,
        BotConfiguration $configuration,
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
        if ($status = $telegram->normalizeStatus($payload)) {
            $orchestrator->updateStatus($status);
            if ($updateId) {
                Cache::put("telegram:update:{$updateId}", true, now()->addHours(24));
            }

            return response()->json(['ok' => true]);
        }

        $message = $telegram->normalize($payload);
        if (! $message) {
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
