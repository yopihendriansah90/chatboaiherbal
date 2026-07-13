<?php

namespace App\Services;

use App\Models\AiProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GroqClient
{
    public function __construct(
        private HerbalPrompt $prompt,
        private AiUsageRecorder $usage,
    ) {}

    public function respond(string $message, array $state, ?AiProvider $provider = null): array
    {
        $key = $provider?->api_key ?: config('services.groq.api_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('GROQ_API_KEY belum dikonfigurasi.');
        }

        $lastException = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                return $this->request($key, $message, $state, $provider, $attempt);
            } catch (Throwable $exception) {
                $lastException = $exception;
                $response = $exception instanceof RequestException ? $exception->response : null;
                Log::warning('Groq request failed', [
                    'attempt' => $attempt,
                    'exception' => $exception::class,
                    'status' => $response?->status(),
                    'api_code' => $response?->json('error.code'),
                ]);

                $apiCode = $response?->json('error.code');
                $jsonValidationFailed = $response?->status() === 400 && $apiCode === 'json_validate_failed';
                $rateLimitedWithoutFallback = $response?->status() === 429 && ! config('chatbot.parser_fallback_enabled', true);
                $shouldRetry = ! $response || $response->serverError() || $rateLimitedWithoutFallback || $jsonValidationFailed;
                if ($attempt < 2 && $shouldRetry) {
                    if ($response?->status() === 429) {
                        $delay = min(10, max(1, (int) ceil((float) $response->header('retry-after'))));
                        sleep($delay);
                    } elseif ($jsonValidationFailed) {
                        usleep(350_000);
                    } else {
                        sleep(1);
                    }
                } else {
                    break;
                }
            }
        }

        throw $lastException ?? new RuntimeException('Groq gagal memberikan jawaban.');
    }

    private function request(string $key, string $message, array $state, ?AiProvider $provider, int $attempt): array
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->prompt->instruction($state, $message)]],
            $this->prompt->messages($message, array_slice($state['history'] ?? [], -6)),
        );

        $model = (string) ($provider?->parser_model ?: config('services.groq.parser_model'));
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => 600,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'parsed_herbal_message',
                    'strict' => true,
                    'schema' => $this->prompt->jsonSchema(),
                ],
            ],
        ];

        if (str_starts_with($model, 'openai/gpt-oss-')) {
            $payload['reasoning_effort'] = 'low';
            $payload['include_reasoning'] = false;
        }

        $startedAt = hrtime(true);
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($key)
                ->connectTimeout(5)
                ->timeout((int) ($provider?->parser_timeout ?: config('services.groq.timeout', 25)))
                ->post('https://api.groq.com/openai/v1/chat/completions', $payload);
        } catch (Throwable $exception) {
            $this->usage->recordTransportFailure('groq', 'parser', $model, $this->latency($startedAt), $attempt, $exception, $provider);
            throw $exception;
        }

        $this->usage->recordResponse('groq', 'parser', $model, $response, $this->latency($startedAt), $attempt, $provider);
        $response->throw();

        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Groq tidak memberikan jawaban yang dapat digunakan.');
        }

        $result = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        if (! isset($result['intent'], $result['confidence'], $result['emergency'], $result['facts'])) {
            throw new RuntimeException('Format jawaban Groq tidak lengkap.');
        }

        return $result;
    }

    private function latency(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
