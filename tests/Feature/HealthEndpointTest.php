<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'services.telegram.token' => 'secret-telegram-token',
            'services.telegram.webhook_secret' => 'secret-webhook-token',
            'services.telegram.webhook_url' => 'https://example.test/api/telegram/webhook',
            'services.groq.api_key' => 'secret-groq-key',
            'services.groq.parser_model' => 'openai/gpt-oss-20b',
            'services.groq.renderer_model' => 'qwen/qwen3.6-27b',
            'chatbot.natural_renderer' => true,
        ]);
    }

    public function test_reports_safe_operational_health_information(): void
    {
        $response = $this->getJson('/up')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.cache', 'ok')
            ->assertJsonPath('checks.product_catalog.products', 24)
            ->assertJsonPath('checks.telegram.configured', true)
            ->assertJsonPath('checks.ai_parser.configured', true)
            ->assertJsonPath('checks.natural_renderer.enabled', true);

        $body = $response->getContent();
        $this->assertStringNotContainsString('secret-telegram-token', $body);
        $this->assertStringNotContainsString('secret-webhook-token', $body);
        $this->assertStringNotContainsString('secret-groq-key', $body);
        $this->assertStringNotContainsString('example.test', $body);
    }

    public function test_reports_degraded_when_optional_renderer_is_not_configured(): void
    {
        config(['services.groq.renderer_model' => null]);

        $this->getJson('/up')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.natural_renderer.configured', false);
    }

    public function test_returns_service_unavailable_when_required_parser_is_not_configured(): void
    {
        config(['services.groq.api_key' => null]);

        $this->getJson('/up')
            ->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('checks.ai_parser.configured', false);
    }
}
