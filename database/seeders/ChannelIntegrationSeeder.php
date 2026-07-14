<?php

namespace Database\Seeders;

use App\Models\BotSetting;
use App\Models\ChannelIntegration;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChannelIntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $bot = BotSetting::query()->first();
        $integration = ChannelIntegration::query()->firstOrNew(['key' => 'telegram-primary']);
        $settings = $integration->settings ?? [];

        $integration->fill([
            'driver' => $integration->driver ?: 'telegram',
            'name' => $integration->name ?: 'Telegram Bot Utama',
            'description' => $integration->description ?: 'Channel Telegram utama untuk konsultasi herbal.',
            'settings' => [
                ...$settings,
                'webhook_url' => $settings['webhook_url'] ?? $bot?->telegram_webhook_url ?? config('services.telegram.webhook_url'),
                'timeout' => $settings['timeout'] ?? $bot?->telegram_timeout ?? config('services.telegram.timeout', 10),
            ],
            'is_enabled' => $integration->exists ? $integration->is_enabled : true,
            'updated_by' => $integration->updated_by ?: User::query()->where('email', 'admin@mail.com')->value('id'),
        ]);

        $credentials = $integration->credentials ?? [];
        $integration->credentials = array_filter([
            'bot_token' => $credentials['bot_token'] ?? $bot?->telegram_bot_token ?? config('services.telegram.token'),
            'webhook_secret' => $credentials['webhook_secret'] ?? $bot?->telegram_webhook_secret ?? config('services.telegram.webhook_secret'),
        ]);

        $integration->save();
    }
}
