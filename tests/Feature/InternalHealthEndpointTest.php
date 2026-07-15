<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalHealthEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'app.timezone' => 'Asia/Jakarta',
            'health.internal_token' => 'internal-test-token',
            'services.telegram.token' => 'secret-telegram-token',
            'services.telegram.webhook_secret' => 'secret-webhook-token',
            'services.telegram.webhook_url' => 'https://internal.example.test/api/telegram/webhook',
            'services.groq.api_key' => 'secret-groq-key',
            'services.groq.parser_model' => 'openai/gpt-oss-20b',
            'services.groq.renderer_model' => 'qwen/qwen3.6-27b',
            'chatbot.natural_renderer' => true,
        ]);
    }

    public function test_requires_internal_token(): void
    {
        $this->getJson('/api/internal/health')->assertUnauthorized();
        $this->withToken('wrong-token')->getJson('/api/internal/health')->assertUnauthorized();
    }

    public function test_returns_detailed_safe_diagnostics_with_bearer_token(): void
    {
        $response = $this->withToken('internal-test-token')
            ->getJson('/api/internal/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('runtime.timezone', 'Asia/Jakarta')
            ->assertJsonPath('checks.cache', 'ok')
            ->assertJsonPath('ai.parser.model', 'openai/gpt-oss-20b')
            ->assertJsonPath('ai.renderer.model', 'qwen/qwen3.6-27b')
            ->assertJsonPath('conversation.state_version', 'v5-durable')
            ->assertJsonPath('catalog.products', 24)
            ->assertJsonPath('telegram.webhook.host', 'internal.example.test');

        $body = $response->getContent();
        foreach (['internal-test-token', 'secret-telegram-token', 'secret-webhook-token', 'secret-groq-key'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_accepts_custom_internal_header(): void
    {
        $this->withHeader('X-Internal-Health-Token', 'internal-test-token')
            ->getJson('/api/internal/health')
            ->assertOk();
    }

    public function test_returns_down_when_required_configuration_is_missing(): void
    {
        config(['services.telegram.token' => null]);

        $this->withToken('internal-test-token')
            ->getJson('/api/internal/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('checks.telegram', 'failed');
    }
}
