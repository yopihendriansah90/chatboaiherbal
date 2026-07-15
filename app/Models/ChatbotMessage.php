<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ChatbotMessage extends Model
{
    protected $fillable = [
        'uuid',
        'correlation_id',
        'chatbot_conversation_id',
        'consultation_case_id',
        'reply_to_message_id',
        'chatbot_channel_identity_id',
        'channel_integration_id',
        'external_event_id',
        'external_message_id',
        'direction',
        'message_type',
        'content',
        'processing_status',
        'delivery_status',
        'delivery_attempt_count',
        'error_code',
        'metadata',
        'occurred_at',
        'processed_at',
        'delivered_at',
        'failed_at',
        'next_delivery_attempt_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $message->uuid ??= (string) Str::uuid();
            $message->occurred_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'content' => 'encrypted',
            'metadata' => 'encrypted:array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'next_delivery_attempt_at' => 'datetime',
            'delivery_attempt_count' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'chatbot_conversation_id');
    }

    public function consultationCase(): BelongsTo
    {
        return $this->belongsTo(ConsultationCase::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function reply(): HasOne
    {
        return $this->hasOne(self::class, 'reply_to_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_message_id');
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(ChatbotChannelIdentity::class, 'chatbot_channel_identity_id');
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ChannelIntegration::class, 'channel_integration_id');
    }
}
