<?php

namespace App\Services;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AiProviderResolver
{
    public function find(string $provider): ?AiProvider
    {
        $provider = strtolower($provider);
        if (! in_array($provider, AiProvider::TYPES, true)) {
            return null;
        }

        try {
            if (Schema::hasTable('ai_providers')) {
                $record = AiProvider::query()->where('provider', $provider)->first();
                if ($record) {
                    if (! $record->is_enabled) {
                        return null;
                    }

                    $record->api_key = $record->api_key ?: $this->legacyKey($provider);

                    return $record;
                }
            }
        } catch (Throwable) {
            // The environment configuration remains available during migrations or database outages.
        }

        $key = $this->legacyKey($provider);
        if (blank($key)) {
            return null;
        }

        return new AiProvider([
            'provider' => $provider,
            'name' => ucfirst($provider),
            'api_key' => $key,
            'parser_model' => $this->legacyParserModel($provider),
            'renderer_model' => $this->legacyRendererModel($provider),
            'parser_timeout' => $this->legacyParserTimeout($provider),
            'renderer_timeout' => $this->legacyRendererTimeout($provider),
            'is_enabled' => true,
            'priority' => 1,
        ]);
    }

    /** @return list<string> */
    public function parserCandidates(): array
    {
        $primary = strtolower((string) config('chatbot.parser_provider', config('chatbot.ai_provider', 'groq')));
        $candidates = [$primary];

        if (config('chatbot.parser_fallback_enabled', true)) {
            $configured = config('chatbot.parser_fallback_order', ['groq', 'openai', 'gemini']);
            $candidates = array_merge($candidates, is_array($configured) ? $configured : []);
        }

        return array_values(array_unique(array_filter($candidates, fn ($value) => in_array($value, AiProvider::TYPES, true))));
    }

    public function renderer(): ?AiProvider
    {
        return $this->find((string) config('chatbot.renderer_provider', 'groq'));
    }

    public function availableOptions(): array
    {
        try {
            if (Schema::hasTable('ai_providers')) {
                return AiProvider::query()->where('is_enabled', true)->orderBy('priority')->pluck('name', 'provider')->all();
            }
        } catch (Throwable) {
            // Use fixed options below.
        }

        return ['groq' => 'Groq', 'openai' => 'OpenAI', 'gemini' => 'Gemini'];
    }

    public function circuitOpen(string $provider, string $role = 'parser'): bool
    {
        return Cache::has("ai-provider:circuit:{$role}:{$provider}");
    }

    public function recordFailure(string $provider, string $role = 'parser'): void
    {
        $failuresKey = "ai-provider:failures:{$role}:{$provider}";
        $failures = (int) Cache::get($failuresKey, 0) + 1;
        Cache::put($failuresKey, $failures, now()->addMinutes(5));
        if ($failures >= 3) {
            Cache::put("ai-provider:circuit:{$role}:{$provider}", true, now()->addMinutes(5));
        }
    }

    public function recordSuccess(string $provider, string $role = 'parser'): void
    {
        Cache::forget("ai-provider:failures:{$role}:{$provider}");
        Cache::forget("ai-provider:circuit:{$role}:{$provider}");
    }

    private function legacyKey(string $provider): mixed
    {
        return config("services.{$provider}.api_key");
    }

    private function legacyParserModel(string $provider): string
    {
        return (string) match ($provider) {
            'groq' => config('services.groq.parser_model', 'openai/gpt-oss-20b'),
            'gemini' => config('services.gemini.model', 'gemini-3.5-flash'),
            'openai' => config('services.openai.parser_model', 'gpt-5.4-mini'),
        };
    }

    private function legacyRendererModel(string $provider): string
    {
        return (string) match ($provider) {
            'groq' => config('services.groq.renderer_model', 'qwen/qwen3.6-27b'),
            'gemini' => config('services.gemini.model', 'gemini-3.5-flash'),
            'openai' => config('services.openai.renderer_model', 'gpt-5.4-mini'),
        };
    }

    private function legacyParserTimeout(string $provider): int
    {
        return (int) config("services.{$provider}.timeout", 25);
    }

    private function legacyRendererTimeout(string $provider): int
    {
        return (int) config("services.{$provider}.renderer_timeout", 12);
    }
}
