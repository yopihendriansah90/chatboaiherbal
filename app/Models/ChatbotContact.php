<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ChatbotContact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'display_name',
        'status',
        'admin_notes',
        'first_seen_at',
        'last_seen_at',
        'memory_consented_at',
        'memory_consent_revoked_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $contact): void {
            $contact->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'memory_consented_at' => 'datetime',
            'memory_consent_revoked_at' => 'datetime',
        ];
    }

    public function identities(): HasMany
    {
        return $this->hasMany(ChatbotChannelIdentity::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatbotConversation::class);
    }

    public function messages(): HasManyThrough
    {
        return $this->hasManyThrough(
            ChatbotMessage::class,
            ChatbotConversation::class,
            'chatbot_contact_id',
            'chatbot_conversation_id',
        );
    }

    public function memories(): HasMany
    {
        return $this->hasMany(CustomerMemory::class);
    }
}
