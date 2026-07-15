<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotConversationState extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'facts' => 'encrypted:array',
            'domain_states' => 'encrypted:array',
            'missing_fields' => 'encrypted:array',
            'offered_products' => 'encrypted:array',
            'preferences' => 'encrypted:array',
            'catalog_context' => 'encrypted:array',
            'history' => 'encrypted:array',
            'safety_state' => 'encrypted:array',
            'last_decision' => 'encrypted:array',
            'summary' => 'encrypted',
            'version' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'chatbot_conversation_id');
    }
}
