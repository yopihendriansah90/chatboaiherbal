<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\AiUsageRecord;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AiUsageRecorder
{
    public function __construct(private AiCostCalculator $costs) {}

    public function recordResponse(
        string $providerName,
        string $role,
        string $model,
        Response $response,
        int $latencyMs,
        int $attempt = 1,
        ?AiProvider $provider = null,
    ): ?AiUsageRecord {
        $tokens = $this->extractTokens($providerName, $response);
        $storedTokens = $tokens;
        unset($storedTokens['billable_output_tokens']);

        return $this->store([
            'provider' => $providerName,
            'role' => $role,
            'model' => $model,
            'request_id' => $this->requestId($providerName, $response),
            'attempt' => $attempt,
            'successful' => $response->successful(),
            'status_code' => $response->status(),
            'error_code' => $this->errorCode($providerName, $response),
            ...$storedTokens,
            'latency_ms' => $latencyMs,
            'occurred_at' => now(),
        ], $provider, $providerName, $model, $tokens);
    }

    public function recordTransportFailure(
        string $providerName,
        string $role,
        string $model,
        int $latencyMs,
        int $attempt,
        Throwable $exception,
        ?AiProvider $provider = null,
    ): ?AiUsageRecord {
        return $this->store([
            'provider' => $providerName,
            'role' => $role,
            'model' => $model,
            'attempt' => $attempt,
            'successful' => false,
            'error_code' => class_basename($exception),
            'latency_ms' => $latencyMs,
            'occurred_at' => now(),
        ], $provider, $providerName, $model, []);
    }

    private function store(array $data, ?AiProvider $provider, string $providerName, string $model, array $tokens): ?AiUsageRecord
    {
        try {
            if (! Schema::hasTable('ai_usage_records')) {
                return null;
            }

            $costs = $this->costs->calculate($provider, $providerName, $model, $tokens);

            return AiUsageRecord::query()->create([...$data, ...$costs]);
        } catch (Throwable $exception) {
            Log::warning('AI usage could not be recorded', [
                'provider' => $providerName,
                'role' => $data['role'] ?? null,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function extractTokens(string $provider, Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            return [];
        }

        $tokens = match ($provider) {
            'groq' => [
                'input_tokens' => data_get($json, 'usage.prompt_tokens'),
                'cached_input_tokens' => data_get($json, 'usage.prompt_tokens_details.cached_tokens', 0),
                'output_tokens' => data_get($json, 'usage.completion_tokens'),
                'reasoning_tokens' => data_get($json, 'usage.completion_tokens_details.reasoning_tokens', 0),
                'total_tokens' => data_get($json, 'usage.total_tokens'),
            ],
            'gemini' => [
                'input_tokens' => data_get($json, 'usageMetadata.promptTokenCount'),
                'cached_input_tokens' => data_get($json, 'usageMetadata.cachedContentTokenCount', 0),
                'output_tokens' => data_get($json, 'usageMetadata.candidatesTokenCount'),
                'reasoning_tokens' => data_get($json, 'usageMetadata.thoughtsTokenCount', 0),
                'billable_output_tokens' => (int) data_get($json, 'usageMetadata.candidatesTokenCount', 0)
                    + (int) data_get($json, 'usageMetadata.thoughtsTokenCount', 0),
                'total_tokens' => data_get($json, 'usageMetadata.totalTokenCount'),
            ],
            'openai' => [
                'input_tokens' => data_get($json, 'usage.input_tokens'),
                'cached_input_tokens' => data_get($json, 'usage.input_tokens_details.cached_tokens', 0),
                'output_tokens' => data_get($json, 'usage.output_tokens'),
                'reasoning_tokens' => data_get($json, 'usage.output_tokens_details.reasoning_tokens', 0),
                'total_tokens' => $this->openAiTotal($json),
            ],
            default => [],
        };

        return array_filter($tokens, static fn ($value) => $value !== null);
    }

    private function openAiTotal(array $json): ?int
    {
        $input = data_get($json, 'usage.input_tokens');
        $output = data_get($json, 'usage.output_tokens');

        return is_numeric($input) && is_numeric($output) ? (int) $input + (int) $output : null;
    }

    private function requestId(string $provider, Response $response): ?string
    {
        $value = match ($provider) {
            'openai' => $response->json('id') ?: $response->header('x-request-id'),
            'groq' => $response->json('id') ?: $response->header('x-request-id'),
            'gemini' => $response->json('responseId') ?: $response->header('x-request-id'),
            default => $response->header('x-request-id'),
        };

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function errorCode(string $provider, Response $response): ?string
    {
        if ($response->successful()) {
            return null;
        }

        $value = $provider === 'gemini'
            ? $response->json('error.status')
            : $response->json('error.code');

        return is_scalar($value) ? (string) $value : 'http_'.$response->status();
    }
}
