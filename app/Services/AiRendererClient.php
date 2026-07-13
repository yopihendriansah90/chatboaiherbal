<?php

namespace App\Services;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AiRendererClient
{
    public function __construct(private AiUsageRecorder $usage) {}

    public function render(AiProvider $provider, string $instruction, string $payload): string
    {
        if (blank($provider->api_key)) {
            throw new RuntimeException("API key {$provider->name} belum dikonfigurasi.");
        }

        return match ($provider->provider) {
            'groq' => $this->groq($provider, $instruction, $payload),
            'gemini' => $this->gemini($provider, $instruction, $payload),
            'openai' => $this->openai($provider, $instruction, $payload),
            default => throw new RuntimeException("Renderer {$provider->provider} tidak didukung."),
        };
    }

    private function groq(AiProvider $provider, string $instruction, string $payload): string
    {
        $startedAt = hrtime(true);
        try {
            $response = Http::acceptJson()->asJson()->withToken($provider->api_key)
                ->connectTimeout(4)->timeout($provider->renderer_timeout)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $provider->renderer_model,
                    'messages' => [
                        ['role' => 'system', 'content' => $instruction],
                        ['role' => 'user', 'content' => $payload],
                    ],
                    'max_completion_tokens' => 180,
                ]);
        } catch (Throwable $exception) {
            $this->transportFailure($provider, $startedAt, $exception);
            throw $exception;
        }

        $this->usage->recordResponse('groq', 'renderer', $provider->renderer_model, $response, $this->latency($startedAt), 1, $provider);
        $response->throw();

        return $this->extractText($response->json('choices.0.message.content'));
    }

    private function gemini(AiProvider $provider, string $instruction, string $payload): string
    {
        $model = rawurlencode($provider->renderer_model);
        $startedAt = hrtime(true);
        try {
            $response = Http::acceptJson()->asJson()
                ->withHeader('x-goog-api-key', $provider->api_key)
                ->connectTimeout(4)->timeout($provider->renderer_timeout)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'system_instruction' => ['parts' => [['text' => $instruction]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $payload]]]],
                    'generationConfig' => ['maxOutputTokens' => 180],
                ]);
        } catch (Throwable $exception) {
            $this->transportFailure($provider, $startedAt, $exception);
            throw $exception;
        }

        $this->usage->recordResponse('gemini', 'renderer', $provider->renderer_model, $response, $this->latency($startedAt), 1, $provider);
        $response->throw();

        return $this->extractText($response->json('candidates.0.content.parts.0.text'));
    }

    private function openai(AiProvider $provider, string $instruction, string $payload): string
    {
        $startedAt = hrtime(true);
        try {
            $response = Http::acceptJson()->asJson()->withToken($provider->api_key)
                ->connectTimeout(4)->timeout($provider->renderer_timeout)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $provider->renderer_model,
                    'instructions' => $instruction,
                    'input' => $payload,
                    'max_output_tokens' => 180,
                ]);
        } catch (Throwable $exception) {
            $this->transportFailure($provider, $startedAt, $exception);
            throw $exception;
        }

        $this->usage->recordResponse('openai', 'renderer', $provider->renderer_model, $response, $this->latency($startedAt), 1, $provider);
        $response->throw();

        return $this->extractText($response->json('output_text') ?? $response->json('output.0.content.0.text'));
    }

    private function extractText(mixed $content): string
    {
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI renderer tidak memberikan teks.');
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) && is_string($decoded['text'] ?? null)
            ? $decoded['text']
            : $content;
    }

    private function transportFailure(AiProvider $provider, int $startedAt, Throwable $exception): void
    {
        $this->usage->recordTransportFailure(
            $provider->provider,
            'renderer',
            $provider->renderer_model,
            $this->latency($startedAt),
            1,
            $exception,
            $provider,
        );
    }

    private function latency(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
