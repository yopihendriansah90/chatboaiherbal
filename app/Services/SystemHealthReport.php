<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Repositories\ProductRepository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SystemHealthReport
{
    public function __construct(
        private ProductRepository $products,
        private BotConfiguration $botConfiguration,
        private AiProviderResolver $aiProviders,
    ) {}

    public function generate(): array
    {
        $catalog = $this->catalog();
        $checks = [
            'cache' => $this->cacheCheck(),
            'catalog' => $catalog['status'],
            'telegram' => $this->telegramConfigured() ? 'ok' : 'failed',
            'parser' => $this->parserConfigured() ? 'ok' : 'failed',
            'renderer' => $this->rendererStatus(),
            'storage' => is_writable(storage_path()) ? 'ok' : 'failed',
            'logging' => is_writable(storage_path('logs')) ? 'ok' : 'failed',
        ];
        $requiredFailed = in_array('failed', Arr::only($checks, ['cache', 'catalog', 'telegram', 'parser', 'storage', 'logging']), true);
        $status = $requiredFailed ? 'down' : ($checks['renderer'] === 'degraded' ? 'degraded' : 'ok');

        return [
            'status' => $status,
            'service' => config('chatbot.name'),
            'timestamp' => now()->toIso8601String(),
            'runtime' => [
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'timezone' => config('app.timezone'),
                'php' => PHP_VERSION,
                'laravel' => Application::VERSION,
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ],
            'checks' => $checks,
            'telegram' => $this->telegramDetails(),
            'ai' => [
                'provider' => $this->parserProvider()?->provider,
                'api_key_configured' => filled($this->parserProvider()?->api_key),
                'fallback_enabled' => (bool) config('chatbot.parser_fallback_enabled'),
                'fallback_models' => collect(config('chatbot.fallback_ai_model_ids', []))
                    ->map(fn ($id) => $this->aiProviders->model((int) $id)?->optionLabel())
                    ->filter()
                    ->values()
                    ->all(),
                'parser' => [
                    'provider' => $this->parserProvider()?->provider,
                    'model' => $this->parserProvider()?->parser_model,
                    'timeout_seconds' => (int) ($this->parserProvider()?->parser_timeout ?? 0),
                    'configured' => $this->parserConfigured(),
                ],
                'renderer' => [
                    'enabled' => (bool) config('chatbot.natural_renderer'),
                    'provider' => $this->aiProviders->renderer()?->provider,
                    'model' => $this->aiProviders->renderer()?->renderer_model,
                    'timeout_seconds' => (int) ($this->aiProviders->renderer()?->renderer_timeout ?? 0),
                    'max_words' => (int) config('chatbot.renderer_max_words'),
                    'configured' => $this->rendererStatus() === 'ok',
                ],
            ],
            'conversation' => [
                'cache_store' => config('cache.default'),
                'state_version' => 'v3',
                'memory_ttl_hours' => (int) config('chatbot.memory_ttl_hours'),
                'history_limit' => (int) config('chatbot.history_limit'),
            ],
            'configuration' => [
                'source' => $this->botConfiguration->current()?->is_active ? 'database' : 'environment',
                'panel_configured' => $this->botConfiguration->current() !== null,
            ],
            'catalog' => $catalog,
            'recent_failures' => $this->recentFailures(),
        ];
    }

    private function cacheCheck(): string
    {
        $key = 'health:internal:'.bin2hex(random_bytes(8));
        try {
            Cache::put($key, 'ok', 10);
            $healthy = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $healthy ? 'ok' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function catalog(): array
    {
        try {
            $metadata = $this->products->metadata();

            return [
                'status' => 'ok',
                'file' => basename((string) config('chatbot.catalog_path')),
                'dataset' => $metadata['nama_dataset'] ?? null,
                'products' => count($this->products->all()),
                'composition_rows' => $metadata['jumlah_baris_komposisi'] ?? null,
            ];
        } catch (Throwable) {
            return ['status' => 'failed', 'file' => null, 'dataset' => null, 'products' => 0, 'composition_rows' => 0];
        }
    }

    private function telegramConfigured(): bool
    {
        return filled(config('services.telegram.token'))
            && filled(config('services.telegram.webhook_secret'))
            && filled(config('services.telegram.webhook_url'));
    }

    private function telegramDetails(): array
    {
        $url = parse_url((string) config('services.telegram.webhook_url')) ?: [];

        return [
            'configured' => $this->telegramConfigured(),
            'webhook' => ['scheme' => $url['scheme'] ?? null, 'host' => $url['host'] ?? null, 'path' => $url['path'] ?? null],
            'timeout_seconds' => (int) config('services.telegram.timeout'),
        ];
    }

    private function parserConfigured(): bool
    {
        return filled($this->parserProvider()?->api_key) && filled($this->parserProvider()?->parser_model);
    }

    private function rendererStatus(): string
    {
        if (! config('chatbot.natural_renderer')) {
            return 'disabled';
        }

        $renderer = $this->aiProviders->renderer();

        return filled($renderer?->api_key) && filled($renderer?->renderer_model) ? 'ok' : 'degraded';
    }

    private function parserProvider(): ?AiProvider
    {
        return $this->aiProviders->parser();
    }

    private function recentFailures(): array
    {
        $path = storage_path('logs/laravel.log');
        if (! is_readable($path)) {
            return [];
        }

        $events = [];
        $lines = array_slice(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -400);
        foreach ($lines as $line) {
            if (! preg_match('/^\[([^]]+)]\s+\w+\.(WARNING|ERROR|NOTICE):\s+(Groq request failed|Gemini request failed|Natural renderer fallback|Switching AI parser provider)\s+(\{.*})$/', $line, $matches)) {
                continue;
            }
            $context = json_decode($matches[4], true) ?: [];
            $events[] = array_merge([
                'timestamp' => $matches[1], 'level' => strtolower($matches[2]), 'event' => $matches[3],
            ], Arr::only($context, [
                'attempt', 'status', 'api_code', 'action', 'provider', 'model', 'latency_ms',
                'validator_passed', 'fallback_reason', 'product_code',
            ]));
        }

        return array_slice(array_reverse($events), 0, (int) config('health.recent_failures_limit', 10));
    }
}
