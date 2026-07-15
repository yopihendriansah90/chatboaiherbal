<?php

namespace App\Services;

use App\Models\ChatbotContact;
use App\Models\ChatbotMessage;
use App\Models\CustomerMemory;

class CustomerMemoryService
{
    public function grantConsent(ChatbotContact $contact): void
    {
        $contact->update(['memory_consented_at' => now(), 'memory_consent_revoked_at' => null]);
    }

    public function revokeConsent(ChatbotContact $contact): void
    {
        $contact->update(['memory_consent_revoked_at' => now()]);
        $contact->memories()->delete();
    }

    public function remember(ChatbotContact $contact, string $key, string $value, ?ChatbotMessage $source = null, ?\DateTimeInterface $expiresAt = null): ?CustomerMemory
    {
        if (! $contact->memory_consented_at || $contact->memory_consent_revoked_at) {
            return null;
        }

        return CustomerMemory::query()->updateOrCreate(
            ['chatbot_contact_id' => $contact->id, 'key' => $key],
            [
                'value' => $value,
                'status' => 'active',
                'source_message_id' => $source?->id,
                'consented_at' => $contact->memory_consented_at,
                'expires_at' => $expiresAt,
            ],
        );
    }

    public function active(ChatbotContact $contact): array
    {
        if (! $contact->memory_consented_at || $contact->memory_consent_revoked_at) {
            return [];
        }

        return $contact->memories()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('value', 'key')
            ->all();
    }

    public function hydrateConversation(ChatbotContact $contact, string $conversationUuid, ConversationStore $store): void
    {
        $memories = array_intersect_key($this->active($contact), array_flip([
            'age_years', 'age_group', 'sex', 'allergies', 'conditions', 'medications',
        ]));
        if ($memories === []) {
            return;
        }

        $state = $store->get($conversationUuid);
        foreach ($memories as $key => $value) {
            if (blank($state['facts'][$key] ?? null)) {
                $state['facts'][$key] = $key === 'age_years' ? (int) $value : $value;
            }
        }
        $store->put($conversationUuid, $state);
    }
}
