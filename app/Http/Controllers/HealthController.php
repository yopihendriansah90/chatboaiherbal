<?php

namespace App\Http\Controllers;

use App\Repositories\ProductRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(ProductRepository $products): JsonResponse
    {
        $checks = [
            'application' => 'ok',
            'cache' => $this->checkCache(),
            'product_catalog' => $this->checkCatalog($products),
            'telegram' => [
                'configured' => $this->telegramConfigured(),
            ],
            'ai_parser' => [
                'configured' => $this->parserConfigured(),
            ],
            'natural_renderer' => [
                'enabled' => (bool) config('chatbot.natural_renderer'),
                'configured' => $this->rendererConfigured(),
            ],
        ];

        $status = $this->status($checks);

        return response()->json([
            'status' => $status,
            'service' => config('chatbot.name'),
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $status === 'down' ? 503 : 200);
    }

    private function checkCache(): string
    {
        $key = 'health:'.bin2hex(random_bytes(8));

        try {
            Cache::put($key, 'ok', 10);
            $healthy = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $healthy ? 'ok' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function checkCatalog(ProductRepository $products): array
    {
        try {
            return ['status' => 'ok', 'products' => count($products->all())];
        } catch (Throwable) {
            return ['status' => 'failed', 'products' => 0];
        }
    }

    private function telegramConfigured(): bool
    {
        return filled(config('services.telegram.token'))
            && filled(config('services.telegram.webhook_secret'))
            && filled(config('services.telegram.webhook_url'));
    }

    private function parserConfigured(): bool
    {
        return filled(config('services.groq.api_key'))
            && filled(config('services.groq.parser_model'));
    }

    private function rendererConfigured(): bool
    {
        return ! config('chatbot.natural_renderer')
            || (filled(config('services.groq.api_key')) && filled(config('services.groq.renderer_model')));
    }

    private function status(array $checks): string
    {
        $requiredFailed = $checks['cache'] !== 'ok'
            || $checks['product_catalog']['status'] !== 'ok'
            || ! $checks['telegram']['configured']
            || ! $checks['ai_parser']['configured'];

        if ($requiredFailed) {
            return 'down';
        }

        return ! $checks['natural_renderer']['configured'] ? 'degraded' : 'ok';
    }
}
