<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\AiUsageRecord;
use App\Models\ChannelEvent;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Repositories\ProductRepository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Laravel\Horizon\Horizon;
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
            'database' => $this->databaseCheck(),
            'cache' => $this->cacheCheck(),
            'redis' => $this->redisCheck(),
            'horizon' => $this->horizonCheck(),
            'worker' => $this->horizonCheck(),
            'scheduler' => $this->schedulerCheck(),
            'catalog' => $catalog['status'],
            'telegram' => $this->telegramConfigured() ? 'ok' : 'failed',
            'webhook' => $this->telegramConfigured() ? 'ok' : 'failed',
            'parser' => $this->parserConfigured() ? 'ok' : 'failed',
            'provider' => $this->parserConfigured() ? 'ok' : 'failed',
            'renderer' => $this->rendererStatus(),
            'storage' => is_writable(storage_path()) ? 'ok' : 'failed',
            'logging' => is_writable(storage_path('logs')) ? 'ok' : 'failed',
        ];
        $required = ['database', 'cache', 'catalog', 'telegram', 'parser', 'storage', 'logging'];
        if (config('queue.default') === 'redis') {
            $required[] = 'redis';
            $required[] = 'horizon';
        }
        $requiredFailed = in_array('failed', Arr::only($checks, $required), true);
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
                'state_version' => 'v5-durable',
                'memory_ttl_hours' => (int) config('chatbot.memory_ttl_hours'),
                'history_limit' => (int) config('chatbot.history_limit'),
                'runtime' => $this->runtimeCounts(),
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

    private function databaseCheck(): string
    {
        try {
            DB::select('select 1');

            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function redisCheck(): string
    {
        if (config('queue.default') !== 'redis' && config('cache.default') !== 'redis') {
            return 'disabled';
        }
        try {
            return Redis::connection()->ping() ? 'ok' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function horizonCheck(): string
    {
        if (config('queue.default') !== 'redis') {
            return 'disabled';
        }
        try {
            return Horizon::status() === 'running' ? 'ok' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function schedulerCheck(): string
    {
        try {
            $lastSeen = (int) Cache::get('health:scheduler:last_seen', 0);

            return $lastSeen > 0 && (time() - $lastSeen) <= 180 ? 'ok' : 'unknown';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function runtimeCounts(): array
    {
        try {
            $events = Schema::hasTable('channel_events')
                ? ChannelEvent::query()->whereNotNull('processed_at')->latest('id')->limit(1000)->get(['created_at', 'processed_at'])
                : collect();
            $deliveries = Schema::hasTable('chatbot_messages')
                ? ChatbotMessage::query()->where('direction', 'outgoing')->whereNotNull('delivered_at')->latest('id')->limit(1000)->get(['occurred_at', 'delivered_at'])
                : collect();

            return [
                'pending_events' => Schema::hasTable('channel_events') ? ChannelEvent::query()->whereIn('status', ['pending', 'failed'])->count() : 0,
                'dead_events' => Schema::hasTable('channel_events') ? ChannelEvent::query()->where('status', 'dead')->count() : 0,
                'pending_deliveries' => Schema::hasTable('chatbot_messages') ? ChatbotMessage::query()->where('direction', 'outgoing')->whereIn('delivery_status', ['pending', 'failed'])->count() : 0,
                'dead_deliveries' => Schema::hasTable('chatbot_messages') ? ChatbotMessage::query()->where('delivery_status', 'dead')->count() : 0,
                'average_inbound_latency_ms' => $events->isEmpty() ? null : (int) round($events->avg(fn (ChannelEvent $event) => $event->created_at->diffInMilliseconds($event->processed_at))),
                'average_delivery_latency_ms' => $deliveries->isEmpty() ? null : (int) round($deliveries->avg(fn (ChatbotMessage $message) => $message->occurred_at->diffInMilliseconds($message->delivered_at))),
                'average_ai_latency_ms' => Schema::hasTable('ai_usage_records') ? (int) round((float) AiUsageRecord::query()->whereNotNull('latency_ms')->avg('latency_ms')) : null,
                'ai_cost_idr' => Schema::hasTable('ai_usage_records') ? round((float) AiUsageRecord::query()->sum('total_cost_idr'), 2) : null,
                'ai_fallback_attempts' => Schema::hasTable('ai_usage_records') ? AiUsageRecord::query()->where('attempt', '>', 1)->count() : 0,
                'handoff_waiting' => Schema::hasTable('chatbot_conversations') ? ChatbotConversation::query()->where('service_status', 'waiting_agent')->count() : 0,
                'sla_breached' => Schema::hasTable('chatbot_conversations') ? ChatbotConversation::query()->whereNotIn('service_status', ['resolved'])->where('sla_due_at', '<', now())->count() : 0,
                'feedback_average' => Schema::hasTable('conversation_feedback') ? round((float) DB::table('conversation_feedback')->whereNotNull('rating')->avg('rating'), 2) : null,
            ];
        } catch (Throwable) {
            return ['pending_events' => null, 'dead_events' => null, 'pending_deliveries' => null, 'dead_deliveries' => null];
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
        try {
            $limit = (int) config('health.recent_failures_limit', 10);
            $failures = collect();

            if (Schema::hasTable('channel_events')) {
                $failures->push(...ChannelEvent::query()->whereIn('status', ['failed', 'dead'])
                    ->latest('failed_at')->limit($limit)->get()
                    ->map(fn (ChannelEvent $event): array => [
                        'timestamp' => $event->failed_at?->toIso8601String(),
                        'level' => $event->status === 'dead' ? 'error' : 'warning',
                        'event' => 'Inbound '.$event->status,
                        'provider' => $event->channel,
                        'error_code' => $event->error_code,
                        'attempt' => $event->attempt_count,
                    ]));
            }
            if (Schema::hasTable('chatbot_messages')) {
                $failures->push(...ChatbotMessage::query()->where('direction', 'outgoing')
                    ->whereIn('delivery_status', ['failed', 'dead'])->latest('failed_at')->limit($limit)->get()
                    ->map(fn (ChatbotMessage $message): array => [
                        'timestamp' => $message->failed_at?->toIso8601String(),
                        'level' => $message->delivery_status === 'dead' ? 'error' : 'warning',
                        'event' => 'Outbound '.$message->delivery_status,
                        'error_code' => $message->error_code,
                        'attempt' => $message->delivery_attempt_count,
                    ]));
            }
            if (Schema::hasTable('ai_usage_records')) {
                $failures->push(...AiUsageRecord::query()->where('successful', false)
                    ->latest('occurred_at')->limit($limit)->get()
                    ->map(fn (AiUsageRecord $usage): array => [
                        'timestamp' => $usage->occurred_at?->toIso8601String(),
                        'level' => 'warning',
                        'event' => 'AI request failed',
                        'provider' => $usage->provider,
                        'model' => $usage->model,
                        'error_code' => $usage->error_code,
                        'status' => $usage->status_code,
                        'attempt' => $usage->attempt,
                        'latency_ms' => $usage->latency_ms,
                    ]));
            }

            return $failures->sortByDesc('timestamp')->take($limit)->values()->all();
        } catch (Throwable) {
            return [];
        }
    }
}
