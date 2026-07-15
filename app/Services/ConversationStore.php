<?php

namespace App\Services;

use App\Models\ChatbotConversation;
use App\Models\ChatbotConversationState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ConversationStore
{
    public function get(int|string $chatId): array
    {
        if ($cached = Cache::get($this->key($chatId))) {
            return $cached;
        }

        $state = $this->databaseState($chatId) ?? $this->fresh();
        Cache::put($this->key($chatId), $state, now()->addHours((int) config('chatbot.memory_ttl_hours', 24)));

        return $state;
    }

    public function put(int|string $chatId, array $state): void
    {
        $limit = max(2, (int) config('chatbot.history_limit', 12));
        $state['history'] = array_slice($state['history'] ?? [], -$limit);
        $this->persist($chatId, $state);
        Cache::put($this->key($chatId), $state, now()->addHours((int) config('chatbot.memory_ttl_hours', 24)));
    }

    public function forget(int|string $chatId): void
    {
        Cache::forget($this->key($chatId));
        if ($this->durableStateAvailable()) {
            ChatbotConversationState::query()
                ->whereHas('conversation', fn ($query) => $query->where('uuid', (string) $chatId))
                ->delete();
        }
    }

    public function fresh(): array
    {
        $healthFacts = ['subject' => null, 'sex' => null, 'complaint' => null, 'category' => null, 'age_years' => null, 'age_group' => null, 'pregnancy' => null, 'breastfeeding' => null, 'allergies' => null, 'conditions' => null, 'medications' => null, 'duration' => null, 'frequency' => null, 'red_flags' => null, 'sexual_issue' => null, 'sexual_clarification' => false, 'product_requested' => false];

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
            'product_preferences' => ['dosage_form' => null],
            'catalog_context' => ['product_codes' => [], 'selected_product_code' => null],
            'missing_fields' => [],
            'crisis' => null,
            'last_decision' => null,
        ];
    }

    private function key(int|string $chatId): string
    {
        return 'chatbot:v5:conversation:'.$chatId;
    }

    private function databaseState(int|string $chatId): ?array
    {
        if (! $this->durableStateAvailable()) {
            return null;
        }

        try {
            $record = ChatbotConversationState::query()
                ->whereHas('conversation', fn ($query) => $query->where('uuid', (string) $chatId))
                ->first();
            if (! $record) {
                return null;
            }

            return [
                'active_domain' => $record->active_domain,
                'phase' => $record->phase,
                'facts' => $record->facts ?: [],
                'domain_states' => $record->domain_states ?: [],
                'history' => $record->history ?: [],
                'offered_products' => $record->offered_products ?: [],
                'product_preferences' => $record->preferences ?: [],
                'catalog_context' => $record->catalog_context ?: [],
                'missing_fields' => $record->missing_fields ?: [],
                'crisis' => $record->safety_state,
                'last_decision' => $record->last_decision,
                'summary' => $record->summary,
                '_state_version' => $record->version,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function persist(int|string $chatId, array &$state): void
    {
        if (! $this->durableStateAvailable()) {
            return;
        }

        try {
            DB::transaction(function () use ($chatId, &$state): void {
                $conversation = ChatbotConversation::query()->where('uuid', (string) $chatId)->first();
                if (! $conversation) {
                    return;
                }

                $record = ChatbotConversationState::query()
                    ->where('chatbot_conversation_id', $conversation->id)
                    ->lockForUpdate()
                    ->first();
                $version = ((int) ($record?->version ?? 0)) + 1;
                $attributes = [
                    'version' => $version,
                    'active_domain' => $state['active_domain'] ?? null,
                    'phase' => $state['phase'] ?? 'complaint',
                    'facts' => $state['facts'] ?? [],
                    'domain_states' => $state['domain_states'] ?? [],
                    'missing_fields' => $state['missing_fields'] ?? [],
                    'offered_products' => $state['offered_products'] ?? [],
                    'preferences' => $state['product_preferences'] ?? [],
                    'catalog_context' => $state['catalog_context'] ?? [],
                    'history' => $state['history'] ?? [],
                    'safety_state' => $state['crisis'] ?? null,
                    'last_decision' => $state['last_decision'] ?? null,
                    'summary' => $state['summary'] ?? null,
                ];

                if ($record) {
                    $record->update($attributes);
                } else {
                    ChatbotConversationState::query()->create($attributes + [
                        'chatbot_conversation_id' => $conversation->id,
                    ]);
                }
                $state['_state_version'] = $version;
            });
        } catch (Throwable) {
            // Cache fallback keeps legacy and migration-time traffic available.
        }
    }

    private function durableStateAvailable(): bool
    {
        try {
            return Schema::hasTable('chatbot_conversation_states') && Schema::hasTable('chatbot_conversations');
        } catch (Throwable) {
            return false;
        }
    }
}
