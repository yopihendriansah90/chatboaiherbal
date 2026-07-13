<?php

namespace App\Http\Controllers;

use App\Services\EmergencyDetector;
use App\Services\HerbalChatbot;
use App\Services\TelegramClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        HerbalChatbot $chatbot,
        TelegramClient $telegram,
        EmergencyDetector $emergencies,
    ): JsonResponse {
        $configuredSecret = (string) config('services.telegram.webhook_secret');
        $providedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            return response()->json(['ok' => false], 403);
        }

        $updateId = $request->integer('update_id');
        if ($updateId && Cache::has("telegram:update:{$updateId}")) {
            return response()->json(['ok' => true]);
        }

        $chatId = $request->input('message.chat.id');
        $text = $request->input('message.text');
        if ((! is_int($chatId) && ! is_string($chatId)) || ! is_string($text) || trim($text) === '') {
            return response()->json(['ok' => true]);
        }

        $command = explode('@', strtolower(strtok(trim($text), ' ')))[0];
        $isCommand = in_array($command, ['/start', '/reset'], true);
        $isLocalEmergency = $emergencies->detects($text);

        if (! $isCommand && ! $isLocalEmergency && ! $chatbot->isGreeting($text)) {
            try {
                $telegram->sendTyping($chatId);
            } catch (Throwable) {
                // Indikator bersifat opsional; jawaban utama tetap diproses.
            }
        }

        $reply = $isCommand
            ? $chatbot->reset($chatId)
            : $chatbot->reply($chatId, trim($text));

        try {
            $telegram->sendMessage($chatId, $reply);
        } catch (Throwable) {
            return response()->json(['ok' => false], 502);
        }

        if ($updateId) {
            Cache::put("telegram:update:{$updateId}", true, now()->addHours(24));
        }

        return response()->json(['ok' => true]);
    }
}
