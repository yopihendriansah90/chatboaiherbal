<?php

namespace Tests\Unit;

use App\Services\ConversationStore;
use App\Services\OpenAiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiClientTest extends TestCase
{
    public function test_uses_responses_api_with_strict_json_schema(): void
    {
        config([
            'services.openai.api_key' => 'test-openai-key',
            'services.openai.parser_model' => 'gpt-5.4-mini',
        ]);
        $result = json_encode([
            'intent' => 'health',
            'confidence' => 'high',
            'category' => 'joints',
            'emergency' => false,
            'facts' => ['complaint' => 'nyeri lutut'],
        ]);
        Http::fake(['api.openai.com/*' => Http::response([
            'output' => [['content' => [['type' => 'output_text', 'text' => $result]]]],
        ])]);

        $parsed = app(OpenAiClient::class)->respond('Lutut saya sakit', app(ConversationStore::class)->fresh());

        $this->assertSame('joints', $parsed['category']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['model'] === 'gpt-5.4-mini'
            && data_get($request->data(), 'text.format.type') === 'json_schema'
            && data_get($request->data(), 'text.format.strict') === true);
    }
}
