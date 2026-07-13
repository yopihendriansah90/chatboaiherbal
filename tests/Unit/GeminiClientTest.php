<?php

namespace Tests\Unit;

use App\Services\ConversationStore;
use App\Services\GeminiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    public function test_retries_when_first_structured_response_is_invalid(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $validResult = json_encode([
            'intent' => 'health',
            'confidence' => 'high',
            'category' => 'digestion',
            'emergency' => false,
            'facts' => ['complaint' => 'perut kembung'],
        ]);

        Http::fakeSequence()
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'bukan-json']]]]]])
            ->push(['candidates' => [['content' => ['parts' => [['text' => $validResult]]]]]]);

        $result = app(GeminiClient::class)->respond(
            'Perut saya kembung',
            app(ConversationStore::class)->fresh(),
        );

        $this->assertSame('health', $result['intent']);
        Http::assertSentCount(2);
    }

    public function test_does_not_retry_when_quota_is_exhausted(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake([
            '*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429),
        ]);

        try {
            app(GeminiClient::class)->respond(
                'Perut saya kembung',
                app(ConversationStore::class)->fresh(),
            );
            $this->fail('RequestException seharusnya dilempar.');
        } catch (RequestException $exception) {
            $this->assertSame(429, $exception->response->status());
        }

        Http::assertSentCount(1);
    }
}
