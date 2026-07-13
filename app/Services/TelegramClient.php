<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramClient
{
    public function call(string $method, array $payload = []): array
    {
        $token = config('services.telegram.token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN belum dikonfigurasi.');
        }

        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.telegram.timeout', 10))
            ->post("https://api.telegram.org/bot{$token}/{$method}", $payload)
            ->throw()
            ->json();
    }

    public function sendMessage(int|string $chatId, string $text): void
    {
        $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => mb_substr($text, 0, 4096),
            'disable_web_page_preview' => true,
        ]);
    }

    public function sendTyping(int|string $chatId): void
    {
        $this->call('sendChatAction', [
            'chat_id' => $chatId,
            'action' => 'typing',
        ]);
    }
}
