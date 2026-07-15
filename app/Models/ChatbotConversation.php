<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'service_status',
        'bot_mode',
        'priority',
        'assigned_to',
        'domain_code',
        'category',
        'product_code',
        'is_emergency',
        'handoff_reason',
        'message_count',
        'started_at',
        'last_message_at',
        'waiting_since',
        'sla_due_at',
        'closed_at',
        'resolved_at',
        'resolution_code',
        'tags',
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
            'waiting_since' => 'datetime',
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'tags' => 'array',
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

    public function state(): HasOne
    {
        return $this->hasOne(ChatbotConversationState::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ConversationNote::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ConversationEvent::class);
    }

    public function consultationCases(): HasMany
    {
        return $this->hasMany(ConsultationCase::class);
    }

    public function latestConsultation(): HasOne
    {
        return $this->hasOne(ConsultationCase::class)->latestOfMany();
    }
}
