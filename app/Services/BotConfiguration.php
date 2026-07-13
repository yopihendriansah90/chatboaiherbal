<?php

namespace App\Services;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BotConfiguration
{
    private const CACHE_KEY = 'bot-settings:runtime:v2';

    public function apply(): void
    {
        try {
            $settings = $this->current();
            if (! $settings || ! $settings->is_active) {
                return;
            }

            config([
                'services.telegram.token' => $settings->telegram_bot_token ?: config('services.telegram.token'),
                'services.telegram.webhook_secret' => $settings->telegram_webhook_secret ?: config('services.telegram.webhook_secret'),
                'services.telegram.webhook_url' => $settings->telegram_webhook_url ?: config('services.telegram.webhook_url'),
                'services.telegram.timeout' => $settings->telegram_timeout,
                'services.groq.api_key' => $settings->groq_api_key ?: config('services.groq.api_key'),
                'services.groq.parser_model' => $settings->parser_model,
                'services.groq.renderer_model' => $settings->renderer_model,
                'services.groq.timeout' => $settings->parser_timeout,
                'services.groq.renderer_timeout' => $settings->renderer_timeout,
                'chatbot.natural_renderer' => $settings->natural_renderer_enabled,
                'chatbot.ai_provider' => $settings->parser_provider ?: config('chatbot.parser_provider', 'groq'),
                'chatbot.parser_provider' => $settings->parser_provider ?: config('chatbot.parser_provider', 'groq'),
                'chatbot.renderer_provider' => $settings->renderer_provider ?: config('chatbot.renderer_provider', 'groq'),
                'chatbot.parser_fallback_enabled' => $settings->parser_fallback_enabled ?? config('chatbot.parser_fallback_enabled', true),
                'chatbot.parser_fallback_order' => $settings->parser_fallback_order ?: ['groq', 'openai', 'gemini'],
                'chatbot.renderer_max_words' => $settings->renderer_max_words,
                'chatbot.memory_ttl_hours' => $settings->memory_ttl_hours,
                'chatbot.history_limit' => $settings->history_limit,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Bot settings could not be applied', ['exception' => $exception::class]);
        }
    }

    public function current(): ?BotSetting
    {
        try {
            if (! Schema::hasTable('bot_settings')) {
                return null;
            }

            return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), fn () => BotSetting::query()->first());
        } catch (Throwable) {
            return null;
        }
    }

    public function save(array $values, ?int $userId = null): BotSetting
    {
        $setting = BotSetting::query()->firstOrNew();

        foreach (['telegram_bot_token', 'telegram_webhook_secret', 'groq_api_key'] as $secret) {
            if (blank($values[$secret] ?? null)) {
                unset($values[$secret]);
            }
        }

        $setting->fill($values);
        $setting->updated_by = $userId;
        $changedFields = array_keys($setting->getDirty());
        $setting->save();

        Log::info('Bot settings updated', [
            'user_id' => $userId,
            'fields' => $changedFields,
        ]);

        $this->forget();
        $this->apply();

        return $setting;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function formData(): array
    {
        $setting = $this->current();

        return [
            'telegram_bot_token' => null,
            'telegram_webhook_secret' => null,
            'telegram_webhook_url' => $setting?->telegram_webhook_url ?? config('services.telegram.webhook_url'),
            'telegram_timeout' => $setting?->telegram_timeout ?? config('services.telegram.timeout', 10),
            'groq_api_key' => null,
            'parser_model' => $setting?->parser_model ?? config('services.groq.parser_model'),
            'renderer_model' => $setting?->renderer_model ?? config('services.groq.renderer_model'),
            'natural_renderer_enabled' => $setting?->natural_renderer_enabled ?? config('chatbot.natural_renderer'),
            'parser_timeout' => $setting?->parser_timeout ?? config('services.groq.timeout', 25),
            'renderer_timeout' => $setting?->renderer_timeout ?? config('services.groq.renderer_timeout', 12),
            'renderer_max_words' => $setting?->renderer_max_words ?? config('chatbot.renderer_max_words', 45),
            'memory_ttl_hours' => $setting?->memory_ttl_hours ?? config('chatbot.memory_ttl_hours', 24),
            'history_limit' => $setting?->history_limit ?? config('chatbot.history_limit', 6),
            'is_active' => $setting?->is_active ?? true,
            'telegram_token_configured' => filled($setting?->telegram_bot_token ?: config('services.telegram.token')),
            'webhook_secret_configured' => filled($setting?->telegram_webhook_secret ?: config('services.telegram.webhook_secret')),
            'groq_key_configured' => filled($setting?->groq_api_key ?: config('services.groq.api_key')),
            'parser_provider' => $setting?->parser_provider ?? config('chatbot.parser_provider', 'groq'),
            'renderer_provider' => $setting?->renderer_provider ?? config('chatbot.renderer_provider', 'groq'),
            'parser_fallback_enabled' => $setting?->parser_fallback_enabled ?? config('chatbot.parser_fallback_enabled', true),
            'parser_fallback_order' => $setting?->parser_fallback_order ?? config('chatbot.parser_fallback_order', ['groq', 'openai', 'gemini']),
        ];
    }
}
