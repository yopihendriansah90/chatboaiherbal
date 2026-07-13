<?php

namespace Tests\Unit;

use App\Services\ConversationStore;
use App\Services\GroqClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqClientTest extends TestCase
{
    public function test_retries_json_validation_failure_once(): void
    {
        config(['services.groq.api_key' => 'test-key']);

        $validResult = json_encode([
            'intent' => 'health',
            'confidence' => 'high',
            'category' => 'nutrition',
            'emergency' => false,
            'facts' => ['complaint' => 'mudah lelah'],
        ]);

        Http::fakeSequence()
            ->push(['error' => ['code' => 'json_validate_failed']], 400)
            ->push(['choices' => [['message' => ['content' => $validResult]]]], 200);

        $result = app(GroqClient::class)->respond(
            'Saya mudah lelah',
            app(ConversationStore::class)->fresh(),
        );

        $this->assertSame('health', $result['intent']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['model'] === 'openai/gpt-oss-20b'
            && data_get($request->data(), 'response_format.type') === 'json_schema'
            && data_get($request->data(), 'response_format.json_schema.strict') === true);
    }
}
