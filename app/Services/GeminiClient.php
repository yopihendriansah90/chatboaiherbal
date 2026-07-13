<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeminiClient
{
    public function __construct(private HerbalPrompt $prompt) {}

    public function respond(string $message, array $state): array
    {
        $key = config('services.gemini.api_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('GEMINI_API_KEY belum dikonfigurasi.');
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                return $this->request($key, $message, $state);
            } catch (Throwable $exception) {
                $lastException = $exception;
                $response = $exception instanceof RequestException ? $exception->response : null;
                Log::warning('Gemini request failed', [
                    'attempt' => $attempt,
                    'exception' => $exception::class,
                    'status' => $response?->status(),
                    'api_status' => $response?->json('error.status'),
                ]);

                $shouldRetry = ! $response || $response->serverError();
                if ($attempt < 2 && $shouldRetry) {
                    usleep(350_000);
                } else {
                    break;
                }
            }
        }

        throw $lastException ?? new RuntimeException('Gemini gagal memberikan jawaban.');
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.gemini.timeout', 25));
    }

    private function request(string $key, string $message, array $state): array
    {
        $model = rawurlencode((string) config('services.gemini.model'));
        $response = $this->http()
            ->withHeader('x-goog-api-key', $key)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $this->prompt->instruction($state, $message)]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $message]]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'maxOutputTokens' => 600,
                ],
            ])->throw();

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Gemini tidak memberikan jawaban yang dapat digunakan.');
        }

        $result = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        if (! isset($result['intent'], $result['confidence'], $result['emergency'], $result['facts'])) {
            throw new RuntimeException('Format jawaban Gemini tidak lengkap.');
        }

        return $result;
    }
}
