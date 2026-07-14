<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ConversationStore
{
    public function get(int|string $chatId): array
    {
        return Cache::get($this->key($chatId), $this->fresh());
    }

    public function put(int|string $chatId, array $state): void
    {
        $limit = max(2, (int) config('chatbot.history_limit', 12));
        $state['history'] = array_slice($state['history'] ?? [], -$limit);
        Cache::put($this->key($chatId), $state, now()->addHours((int) config('chatbot.memory_ttl_hours', 24)));
    }

    public function forget(int|string $chatId): void
    {
        Cache::forget($this->key($chatId));
    }

    public function fresh(): array
    {
        $healthFacts = ['subject' => null, 'sex' => null, 'complaint' => null, 'category' => null, 'age_group' => null, 'pregnancy' => null, 'allergies' => null, 'conditions' => null, 'medications' => null, 'duration' => null, 'red_flags' => null, 'product_requested' => false];

        return [
            'active_domain' => null,
            'phase' => 'complaint',
            'facts' => $healthFacts,
            'domain_states' => [
                'health_herbal' => ['phase' => 'complaint', 'facts' => $healthFacts, 'missing_fields' => [], 'offered_products' => []],
                'company_profile' => ['last_intent' => null],
            ],
            'history' => [],
            'offered_products' => [],
            'missing_fields' => [],
        ];
    }

    private function key(int|string $chatId): string
    {
        return 'chatbot:v5:conversation:'.$chatId;
    }
}
