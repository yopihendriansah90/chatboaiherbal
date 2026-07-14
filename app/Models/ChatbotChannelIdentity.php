<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatbotChannelIdentity extends Model
{
    protected $fillable = [
        'chatbot_contact_id',
        'channel_integration_id',
        'channel',
        'external_user_id',
        'external_chat_id',
        'username',
        'first_name',
        'last_name',
        'display_name',
        'language_code',
        'status',
        'description',
        'metadata',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'encrypted:array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ChatbotContact::class, 'chatbot_contact_id');
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ChannelIntegration::class, 'channel_integration_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatbotConversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class);
    }
}
