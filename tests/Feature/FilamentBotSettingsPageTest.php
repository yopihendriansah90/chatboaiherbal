<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class FilamentBotSettingsPageTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/bot-settings')->assertRedirect('/admin/login');
    }

    public function test_authenticated_admin_can_view_settings_without_exposing_secrets(): void
    {
        config([
            'services.telegram.token' => 'telegram-secret-value',
            'services.telegram.webhook_secret' => 'webhook-secret-value',
            'services.groq.api_key' => 'groq-secret-value',
        ]);

        $user = new User;
        $user->forceFill(['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.test', 'is_admin' => true]);

        $this->actingAs($user)
            ->get('/admin/bot-settings')
            ->assertOk()
            ->assertSee('Pengaturan Bot')
            ->assertSee('Simpan pengaturan')
            ->assertSee('Token sudah tersimpan')
            ->assertDontSee('telegram-secret-value')
            ->assertDontSee('webhook-secret-value')
            ->assertDontSee('groq-secret-value');
    }

    public function test_non_admin_user_cannot_access_panel_settings(): void
    {
        $user = new User;
        $user->forceFill(['id' => 2, 'name' => 'User', 'email' => 'user@example.test', 'is_admin' => false]);

        $this->actingAs($user)
            ->get('/admin/bot-settings')
            ->assertForbidden();
    }

    public function test_ai_provider_resource_is_protected_by_panel_authentication(): void
    {
        $this->get('/admin/ai-providers')->assertRedirect('/admin/login');

        $user = new User;
        $user->forceFill(['id' => 3, 'name' => 'User', 'email' => 'user2@example.test', 'is_admin' => false]);

        $this->actingAs($user)->get('/admin/ai-providers')->assertForbidden();
    }
}
