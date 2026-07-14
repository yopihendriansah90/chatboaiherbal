<?php

namespace App\Services;

use App\Models\ChannelIntegration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ChannelConfiguration
{
    private const TELEGRAM_KEY = 'telegram-primary';

    public function telegram(): ?ChannelIntegration
    {
        try {
            if (! Schema::hasTable('channel_integrations')) {
                return null;
            }

            return Cache::remember(
                'channel-integration:'.self::TELEGRAM_KEY,
                now()->addMinutes(5),
                fn () => ChannelIntegration::query()
                    ->where('key', self::TELEGRAM_KEY)
                    ->where('is_enabled', true)
                    ->first(),
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function telegramToken(): ?string
    {
        return $this->credential('bot_token');
    }

    public function telegramWebhookSecret(): ?string
    {
        return $this->credential('webhook_secret');
    }

    public function telegramWebhookUrl(): ?string
    {
        $value = $this->telegram()?->settings['webhook_url'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function telegramTimeout(): ?int
    {
        $value = $this->telegram()?->settings['timeout'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    public function forget(): void
    {
        Cache::forget('channel-integration:'.self::TELEGRAM_KEY);
    }

    public function saveTelegram(array $values, ?int $userId = null): void
    {
        if (! Schema::hasTable('channel_integrations')) {
            return;
        }

        $integration = ChannelIntegration::query()->firstOrNew(['key' => self::TELEGRAM_KEY]);
        $credentials = $integration->credentials ?? [];
        if (filled($values['telegram_bot_token'] ?? null)) {
            $credentials['bot_token'] = $values['telegram_bot_token'];
        }
        if (filled($values['telegram_webhook_secret'] ?? null)) {
            $credentials['webhook_secret'] = $values['telegram_webhook_secret'];
        }

        $integration->fill([
            'driver' => 'telegram',
            'name' => $integration->name ?: 'Telegram Bot Utama',
            'description' => $integration->description ?: 'Channel Telegram utama untuk konsultasi herbal.',
            'credentials' => $credentials,
            'settings' => [
                ...($integration->settings ?? []),
                'webhook_url' => $values['telegram_webhook_url'] ?? null,
                'timeout' => (int) ($values['telegram_timeout'] ?? 10),
            ],
            'is_enabled' => (bool) ($values['is_active'] ?? true),
            'updated_by' => $userId,
        ])->save();

        $this->forget();
    }

    private function credential(string $key): ?string
    {
        $value = $this->telegram()?->credentials[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
