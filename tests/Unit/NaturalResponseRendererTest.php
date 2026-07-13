<?php

namespace Tests\Unit;

use App\Data\ResponsePlan;
use App\Services\NaturalResponseRenderer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NaturalResponseRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['chatbot.natural_renderer' => true, 'services.groq.api_key' => 'test-key']);
    }

    public function test_returns_valid_natural_text(): void
    {
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'text' => 'Baik, agar pilihannya sesuai, apakah Anda memiliki alergi tertentu?',
            ])]]],
        ])]);

        $text = app(NaturalResponseRenderer::class)->render(new ResponsePlan(
            'ask_screening', 'fallback', ['complaint' => 'mudah lelah'], ['allergies'],
        ));

        $this->assertStringContainsString('pilihannya sesuai', $text);
    }

    public function test_returns_null_when_model_injects_product(): void
    {
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'text' => 'Silakan beli Kapsul Gamat Emas di Shopee.',
            ])]]],
        ])]);

        $text = app(NaturalResponseRenderer::class)->render(new ResponsePlan(
            'recommend', 'fallback', category: 'joints', product: [
                'code' => 'KGE', 'name' => 'Kapsul Gamat Emas', 'benefit' => 'sendi',
            ],
        ));

        $this->assertNull($text);
    }
}
