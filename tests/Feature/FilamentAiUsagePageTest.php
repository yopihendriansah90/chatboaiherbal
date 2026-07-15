<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAiUsagePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_configuration_and_report_pages_are_admin_only(): void
    {
        foreach (['/admin/ai-usage', '/admin/ai-model-prices', '/admin/exchange-rates'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/ai-usage')->assertOk()->assertSee('Laporan Usage AI');
        $this->actingAs($admin)->get('/admin/ai-model-prices')->assertOk()->assertSee('Harga Model');
        $this->actingAs($admin)->get('/admin/exchange-rates')->assertOk()->assertSee('Nilai Dolar');
    }
}
