<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiClient
{
    public function __construct(
        private GeminiClient $gemini,
        private GroqClient $groq,
    ) {}

    public function respond(string $message, array $state): array
    {
        $provider = strtolower((string) config('chatbot.ai_provider', 'groq'));

        if ($provider === 'groq') {
            return $this->groq->respond($message, $state);
        }

        if ($provider === 'gemini') {
            return $this->gemini->respond($message, $state);
        }

        if ($provider !== 'auto') {
            throw new RuntimeException("AI_PROVIDER tidak valid: {$provider}.");
        }

        try {
            return $this->gemini->respond($message, $state);
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 429) {
                throw $exception;
            }

            Log::notice('Switching AI provider from Gemini to Groq', ['reason' => 'quota_exhausted']);

            return $this->groq->respond($message, $state);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'GEMINI_API_KEY belum dikonfigurasi.') {
                throw $exception;
            }

            Log::notice('Switching AI provider from Gemini to Groq', ['reason' => 'gemini_key_missing']);

            return $this->groq->respond($message, $state);
        }
    }
}
