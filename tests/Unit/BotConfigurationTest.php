<?php

namespace Tests\Unit;

use App\Models\BotSetting;
use App\Services\BotConfiguration;
use Mockery;
use Tests\TestCase;

class BotConfigurationTest extends TestCase
{
    public function test_active_database_settings_override_runtime_config(): void
    {
        $setting = new BotSetting;
        $setting->forceFill([
            'telegram_bot_token' => 'database-telegram-token',
            'telegram_webhook_secret' => 'database-webhook-secret',
            'telegram_webhook_url' => 'https://bot.example.test/api/telegram/webhook',
            'telegram_timeout' => 15,
            'groq_api_key' => 'database-groq-key',
            'parser_model' => 'openai/gpt-oss-120b',
            'renderer_model' => 'openai/gpt-oss-20b',
            'natural_renderer_enabled' => false,
            'parser_timeout' => 30,
            'renderer_timeout' => 9,
            'renderer_max_words' => 35,
            'memory_ttl_hours' => 48,
            'history_limit' => 8,
            'is_active' => true,
        ]);

        $configuration = Mockery::mock(BotConfiguration::class)->makePartial();
        $configuration->shouldReceive('current')->once()->andReturn($setting);
        $configuration->apply();

        $this->assertSame('database-telegram-token', config('services.telegram.token'));
        $this->assertSame('database-groq-key', config('services.groq.api_key'));
        $this->assertSame('openai/gpt-oss-120b', config('services.groq.parser_model'));
        $this->assertFalse(config('chatbot.natural_renderer'));
        $this->assertSame(48, config('chatbot.memory_ttl_hours'));
    }

    public function test_empty_database_secrets_keep_environment_fallback(): void
    {
        config([
            'services.telegram.token' => 'environment-telegram-token',
            'services.groq.api_key' => 'environment-groq-key',
        ]);

        $setting = new BotSetting;
        $setting->forceFill([
            'telegram_bot_token' => null,
            'telegram_webhook_secret' => null,
            'telegram_webhook_url' => null,
            'telegram_timeout' => 10,
            'groq_api_key' => null,
            'parser_model' => 'openai/gpt-oss-20b',
            'renderer_model' => 'qwen/qwen3.6-27b',
            'natural_renderer_enabled' => true,
            'parser_timeout' => 25,
            'renderer_timeout' => 12,
            'renderer_max_words' => 45,
            'memory_ttl_hours' => 24,
            'history_limit' => 6,
            'is_active' => true,
        ]);

        $configuration = Mockery::mock(BotConfiguration::class)->makePartial();
        $configuration->shouldReceive('current')->once()->andReturn($setting);
        $configuration->apply();

        $this->assertSame('environment-telegram-token', config('services.telegram.token'));
        $this->assertSame('environment-groq-key', config('services.groq.api_key'));
    }
}
