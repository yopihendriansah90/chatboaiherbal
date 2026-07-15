<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatbotTrainingCandidate extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $candidate): void {
            $candidate->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'user_message' => 'encrypted',
            'bot_response' => 'encrypted',
            'detected_facts' => 'encrypted:array',
            'expected_response' => 'encrypted',
            'expected_facts' => 'encrypted:array',
            'review_notes' => 'encrypted',
            'patterns' => 'array',
            'test_result' => 'array',
            'requires_health_context' => 'boolean',
            'detected_confidence' => 'float',
            'reviewed_at' => 'datetime',
            'tested_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'chatbot_conversation_id');
    }

    public function incomingMessage(): BelongsTo
    {
        return $this->belongsTo(ChatbotMessage::class, 'incoming_message_id');
    }

    public function outgoingMessage(): BelongsTo
    {
        return $this->belongsTo(ChatbotMessage::class, 'outgoing_message_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function publishedRule(): BelongsTo
    {
        return $this->belongsTo(ChatbotTrainingRule::class, 'published_rule_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ChatbotTrainingRule::class);
    }
}
