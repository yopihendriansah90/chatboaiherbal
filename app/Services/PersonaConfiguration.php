<?php

namespace App\Services;

use App\Models\ChatbotPersona;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PersonaConfiguration
{
    public function current(): array
    {
        $defaults = [
            'name' => 'Asisten Herbal Walatra',
            'formality' => 'friendly',
            'empathy_style' => 'brief_relevant',
            'emoji_policy' => 'minimal',
            'max_words' => 80,
            'tone_rules' => ['Gunakan sapaan kak', 'Jujur bila informasi belum tersedia', 'Jangan terdengar menggurui'],
        ];

        try {
            if (! Schema::hasTable('chatbot_personas')) {
                return $defaults;
            }
            $persona = ChatbotPersona::query()->where('is_active', true)->first();

            return $persona ? array_replace($defaults, $persona->only(array_keys($defaults))) : $defaults;
        } catch (Throwable) {
            return $defaults;
        }
    }

    public function save(array $data, ?int $userId): ChatbotPersona
    {
        $business = app(BusinessProfileResolver::class)->currentOrFail();
        $data['tone_rules'] = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($data['tone_rules_text'] ?? '')) ?: [])));
        unset($data['tone_rules_text']);

        return ChatbotPersona::query()->updateOrCreate(
            ['business_profile_id' => $business->id],
            $data + ['is_active' => true, 'updated_by' => $userId],
        );
    }
}
