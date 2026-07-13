<?php

namespace App\Services;

use App\Models\AiProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiProviderTester
{
    public function test(AiProvider $provider): bool
    {
        $startedAt = hrtime(true);

        try {
            if (blank($provider->api_key)) {
                throw new \RuntimeException('missing_api_key');
            }

            match ($provider->provider) {
                'groq' => Http::acceptJson()->withToken($provider->api_key)->timeout(10)->get('https://api.groq.com/openai/v1/models')->throw(),
                'openai' => Http::acceptJson()->withToken($provider->api_key)->timeout(10)->get('https://api.openai.com/v1/models')->throw(),
                'gemini' => Http::acceptJson()->withHeader('x-goog-api-key', $provider->api_key)->timeout(10)->get('https://generativelanguage.googleapis.com/v1beta/models')->throw(),
                default => throw new \RuntimeException('unsupported_provider'),
            };

            $provider->forceFill([
                'last_test_status' => 'ready',
                'last_error_code' => null,
                'last_latency_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
                'last_tested_at' => now(),
            ])->save();

            return true;
        } catch (Throwable $exception) {
            $status = $exception instanceof RequestException ? $exception->response->status() : null;
            $provider->forceFill([
                'last_test_status' => match ($status) {
                    401, 403 => 'invalid_key',
                    429 => 'rate_limited',
                    default => 'unavailable',
                },
                'last_error_code' => $status ? 'http_'.$status : class_basename($exception),
                'last_latency_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
                'last_tested_at' => now(),
            ])->save();

            return false;
        }
    }
}
