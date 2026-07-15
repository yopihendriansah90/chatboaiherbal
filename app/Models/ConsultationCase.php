<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ConsultationCase extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $case): void {
            $case->uuid ??= (string) Str::uuid();
            $case->started_at ??= now();
            $case->last_activity_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'complaint' => 'encrypted:string',
            'facts' => 'encrypted:array',
            'summary' => 'encrypted:string',
            'safety_reason_codes' => 'encrypted:array',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'resolved_at' => 'datetime',
            'handed_off_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'chatbot_conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ConversationEvent::class);
    }
}
