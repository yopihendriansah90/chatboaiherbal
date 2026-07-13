<?php

namespace Tests\Unit;

use App\Services\AiClient;
use App\Services\GeminiClient;
use App\Services\GroqClient;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Mockery;
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
}
