<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatbotConversation extends Model
{
    protected $fillable = [
        'uuid',
        'chatbot_contact_id',
        'chatbot_channel_identity_id',
        'channel_integration_id',
        'channel',
        'external_conversation_id',
        'status',
        'category',
        'product_code',
        'is_emergency',
        'message_count',
        'started_at',
        'last_message_at',
        'closed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $conversation): void {
            $conversation->uuid ??= (string) Str::uuid();
            $conversation->started_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'is_emergency' => 'boolean',
            'started_at' => 'datetime',
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ChatbotContact::class, 'chatbot_contact_id');
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(ChatbotChannelIdentity::class, 'chatbot_channel_identity_id');
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ChannelIntegration::class, 'channel_integration_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class)->orderBy('id');
    }
}
