<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class FilamentSystemHealthPageTest extends TestCase
{
    public function test_guest_is_redirected_to_filament_login(): void
    {
        $this->get('/admin/system-health')
            ->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_view_system_health_without_secrets(): void
    {
        config([
            'services.telegram.token' => 'telegram-secret-token',
            'services.telegram.webhook_secret' => 'webhook-secret-token',
            'services.telegram.webhook_url' => 'https://bot.example.test/api/telegram/webhook',
            'services.groq.api_key' => 'groq-secret-key',
            'services.groq.parser_model' => 'openai/gpt-oss-20b',
            'services.groq.renderer_model' => 'qwen/qwen3.6-27b',
        ]);

        $user = new User;
        $user->forceFill([
            'id' => 1,
            'name' => 'Admin Internal',
            'email' => 'admin@example.test',
            'is_admin' => true,
        ]);

        $response = $this->actingAs($user)
            ->get('/admin/system-health');

        $response
            ->assertOk()
            ->assertSee('Status Sistem Chatbot')
            ->assertSee('Kegagalan AI terbaru')
            ->assertSee('bot.example.test')
            ->assertDontSee('telegram-secret-token')
            ->assertDontSee('webhook-secret-token')
            ->assertDontSee('groq-secret-key');
    }
}
