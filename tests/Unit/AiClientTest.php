<?php

namespace Tests\Unit;

use App\Models\AiProvider;
use App\Services\AiClient;
use App\Services\AiProviderResolver;
use App\Services\GeminiClient;
use App\Services\GroqClient;
use App\Services\OpenAiClient;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AiClientTest extends TestCase
{
    public function test_falls_back_to_groq_when_gemini_quota_is_exhausted(): void
    {
        config(['chatbot.ai_provider' => 'auto']);
        $exception = new RequestException(new Response(new PsrResponse(429, [], '{}')));

        $gemini = Mockery::mock(GeminiClient::class);
        $gemini->expects('respond')->once()->andThrow($exception);

        $expected = ['phase' => 'screening', 'reply' => 'Pertanyaan lanjutan'];
        $groq = Mockery::mock(GroqClient::class);
        $groq->expects('respond')->once()->andReturn($expected);

        $result = (new AiClient($gemini, $groq))->respond('Keluhan', []);

        $this->assertSame($expected, $result);
    }

    public function test_groq_mode_never_calls_gemini(): void
    {
        config(['chatbot.ai_provider' => 'groq']);

        $gemini = Mockery::mock(GeminiClient::class);
        $gemini->expects('respond')->never();

        $expected = ['phase' => 'screening', 'reply' => 'Pertanyaan lanjutan'];
        $groq = Mockery::mock(GroqClient::class);
        $groq->expects('respond')->once()->andReturn($expected);

        $result = (new AiClient($gemini, $groq))->respond('Keluhan', []);

        $this->assertSame($expected, $result);
    }

    public function test_uses_configured_openai_fallback_after_groq_failure(): void
    {
        config(['chatbot.ai_provider' => 'groq']);
        $groqProfile = new AiProvider([
            'provider' => 'groq', 'name' => 'Groq', 'api_key' => 'groq-key',
            'parser_model' => 'openai/gpt-oss-20b', 'renderer_model' => 'x',
            'parser_timeout' => 10, 'renderer_timeout' => 10, 'is_enabled' => true,
        ]);
        $openAiProfile = new AiProvider([
            'provider' => 'openai', 'name' => 'OpenAI', 'api_key' => 'openai-key',
            'parser_model' => 'gpt-5.4-mini', 'renderer_model' => 'gpt-5.4-mini',
            'parser_timeout' => 10, 'renderer_timeout' => 10, 'is_enabled' => true,
        ]);

        $providers = Mockery::mock(AiProviderResolver::class);
        $providers->expects('parserCandidates')->andReturn(['groq', 'openai']);
        $providers->expects('circuitOpen')->twice()->andReturnFalse();
        $providers->expects('find')->with('groq')->andReturn($groqProfile);
        $providers->expects('find')->with('openai')->andReturn($openAiProfile);
        $providers->expects('recordFailure')->with('groq')->once();
        $providers->expects('recordSuccess')->with('openai')->once();

        $gemini = Mockery::mock(GeminiClient::class);
        $groq = Mockery::mock(GroqClient::class);
        $groq->expects('respond')->once()->andThrow(new RuntimeException('rate limited'));
        $openai = Mockery::mock(OpenAiClient::class);
        $expected = ['intent' => 'health'];
        $openai->expects('respond')->once()->andReturn($expected);

        $result = (new AiClient($gemini, $groq, $openai, $providers))->respond('Keluhan', []);

        $this->assertSame($expected, $result);
    }
}
