<?php

namespace App\Services;

use App\Models\ChatbotTrainingRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TrainingRuleEngine
{
    public const CACHE_KEY = 'chatbot:training-rules:published:v1';

    public function __construct(
        private IndonesianTypoNormalizer $typos,
        private TrainingRuleValidator $validator,
    ) {}

    /** @return array{rule_id:int,code:string,intent:string,decision:string,reply:string}|null */
    public function match(string $message, array $state = []): ?array
    {
        $text = $this->typos->normalize($message);

        foreach ($this->publishedRules() as $rule) {
            if (($rule['requires_health_context'] ?? false)
                && empty($state['facts']['complaint'])
                && ($state['active_domain'] ?? null) !== 'health_herbal') {
                continue;
            }
            if (filled($rule['product_code'] ?? null)
                && ! $this->hasProductContext((string) $rule['product_code'], $message, $state)) {
                continue;
            }
            foreach ($rule['patterns'] as $pattern) {
                if (preg_match($this->validator->delimit($pattern), $text)) {
                    return [
                        'rule_id' => (int) $rule['id'],
                        'code' => (string) $rule['code'],
                        'intent' => (string) $rule['intent'],
                        'decision' => (string) $rule['decision'],
                        'reply' => (string) $rule['response_template'],
                    ];
                }
            }
        }

        return null;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return list<array<string, mixed>> */
    private function publishedRules(): array
    {
        try {
            if (! Schema::hasTable('chatbot_training_rules')) {
                return [];
            }

            return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), fn (): array => ChatbotTrainingRule::query()
                ->where('status', 'published')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get()
                ->map(fn (ChatbotTrainingRule $rule): array => $rule->toArray())
                ->all());
        } catch (Throwable) {
            return [];
        }
    }

    private function hasProductContext(string $code, string $message, array $state): bool
    {
        return ($state['catalog_context']['selected_product_code'] ?? null) === $code
            || in_array($code, $state['offered_products'] ?? [], true)
            || ($code === 'RAD' && str_contains($this->typos->normalize($message), 'radimax'));
    }
}
