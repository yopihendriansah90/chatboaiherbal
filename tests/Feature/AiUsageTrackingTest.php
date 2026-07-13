<?php

namespace Tests\Feature;

use App\Models\AiModelPrice;
use App\Models\AiProvider;
use App\Models\AiUsageRecord;
use App\Models\ExchangeRate;
use App\Services\ConversationStore;
use App\Services\GeminiClient;
use App\Services\GroqClient;
use App\Services\OpenAiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiUsageTrackingTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as performRefreshDatabase;
    }

    public function refreshDatabase(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Ekstensi pdo_sqlite diperlukan untuk pengujian database in-memory.');
        }

        $this->performRefreshDatabase();
    }

    public function test_groq_usage_is_costed_with_manual_price_and_exchange_rate_snapshot(): void
    {
        $provider = $this->provider('groq', 'openai/gpt-oss-20b');
        $price = AiModelPrice::query()->create([
            'ai_provider_id' => $provider->id,
            'model' => 'openai/gpt-oss-20b',
            'input_price_per_million_usd' => 1,
            'cached_input_price_per_million_usd' => 0.5,
            'output_price_per_million_usd' => 2,
            'effective_at' => now()->subMinute(),
            'is_active' => true,
        ]);
        $rate = ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'quote_currency' => 'IDR',
            'rate' => 16000,
            'rate_date' => today(),
            'source_name' => 'Test manual',
            'is_active' => true,
        ]);
        Http::fake(['api.groq.com/*' => Http::response([
            'id' => 'groq-request-1',
            'choices' => [['message' => ['content' => $this->parsedResult()]]],
            'usage' => [
                'prompt_tokens' => 1000,
                'prompt_tokens_details' => ['cached_tokens' => 200],
                'completion_tokens' => 500,
                'total_tokens' => 1500,
            ],
        ])]);

        app(GroqClient::class)->respond('Saya mudah lelah', app(ConversationStore::class)->fresh(), $provider);

        $usage = AiUsageRecord::query()->sole();
        $this->assertSame('groq', $usage->provider);
        $this->assertSame('parser', $usage->role);
        $this->assertSame(1000, $usage->input_tokens);
        $this->assertSame(200, $usage->cached_input_tokens);
        $this->assertSame(500, $usage->output_tokens);
        $this->assertSame($price->id, $usage->ai_model_price_id);
        $this->assertSame($rate->id, $usage->exchange_rate_id);
        $this->assertEqualsWithDelta(0.0019, (float) $usage->total_cost_usd, 0.000000001);
        $this->assertEqualsWithDelta(30.4, (float) $usage->total_cost_idr, 0.0001);
    }

    public function test_gemini_and_openai_usage_fields_are_normalized(): void
    {
        $gemini = $this->provider('gemini', 'gemini-test');
        $openai = $this->provider('openai', 'gpt-test');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'responseId' => 'gemini-request-1',
                'candidates' => [['content' => ['parts' => [['text' => $this->parsedResult()]]]]],
                'usageMetadata' => [
                    'promptTokenCount' => 120,
                    'cachedContentTokenCount' => 20,
                    'candidatesTokenCount' => 40,
                    'thoughtsTokenCount' => 10,
                    'totalTokenCount' => 170,
                ],
            ]),
            'api.openai.com/*' => Http::response([
                'id' => 'openai-request-1',
                'output' => [['content' => [['text' => $this->parsedResult()]]]],
                'usage' => [
                    'input_tokens' => 200,
                    'input_tokens_details' => ['cached_tokens' => 50],
                    'output_tokens' => 80,
                    'output_tokens_details' => ['reasoning_tokens' => 30],
                ],
            ]),
        ]);

        app(GeminiClient::class)->respond('Saya mudah lelah', app(ConversationStore::class)->fresh(), $gemini);
        app(OpenAiClient::class)->respond('Saya mudah lelah', app(ConversationStore::class)->fresh(), $openai);

        $geminiUsage = AiUsageRecord::query()->where('provider', 'gemini')->sole();
        $this->assertSame(120, $geminiUsage->input_tokens);
        $this->assertSame(20, $geminiUsage->cached_input_tokens);
        $this->assertSame(40, $geminiUsage->output_tokens);
        $this->assertSame(10, $geminiUsage->reasoning_tokens);
        $this->assertSame(170, $geminiUsage->total_tokens);

        $openAiUsage = AiUsageRecord::query()->where('provider', 'openai')->sole();
        $this->assertSame(200, $openAiUsage->input_tokens);
        $this->assertSame(50, $openAiUsage->cached_input_tokens);
        $this->assertSame(80, $openAiUsage->output_tokens);
        $this->assertSame(30, $openAiUsage->reasoning_tokens);
        $this->assertSame(280, $openAiUsage->total_tokens);
    }

    public function test_failed_api_attempt_is_recorded_without_health_message_content(): void
    {
        $provider = $this->provider('groq', 'test-model');
        Http::fake(['api.groq.com/*' => Http::response([
            'error' => ['code' => 'rate_limit_exceeded'],
        ], 429)]);

        try {
            app(GroqClient::class)->respond('Keluhan rahasia pengguna', app(ConversationStore::class)->fresh(), $provider);
        } catch (\Throwable) {
            // Expected: tracking must not alter provider error behaviour.
        }

        $usage = AiUsageRecord::query()->sole();
        $this->assertFalse($usage->successful);
        $this->assertSame(429, $usage->status_code);
        $this->assertSame('rate_limit_exceeded', $usage->error_code);
        $this->assertArrayNotHasKey('message', $usage->getAttributes());
    }

    private function provider(string $name, string $model): AiProvider
    {
        return AiProvider::query()->create([
            'provider' => $name,
            'name' => ucfirst($name),
            'api_key' => 'test-key',
            'parser_model' => $model,
            'renderer_model' => $model,
            'parser_timeout' => 10,
            'renderer_timeout' => 10,
            'is_enabled' => true,
            'priority' => 1,
        ]);
    }

    private function parsedResult(): string
    {
        return json_encode([
            'intent' => 'health',
            'confidence' => 'high',
            'category' => 'nutrition',
            'emergency' => false,
            'facts' => ['complaint' => 'mudah lelah'],
        ], JSON_THROW_ON_ERROR);
    }
}
