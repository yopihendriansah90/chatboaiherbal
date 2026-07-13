<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramClient
{
    public function __construct(private BotConfiguration $configuration) {}

    public function call(string $method, array $payload = []): array
    {
        $token = $this->configuration->telegramToken();
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Token bot Telegram belum disimpan di Pengaturan Bot.');
        }

        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout($this->configuration->telegramTimeout())
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
