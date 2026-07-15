<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotTrainingRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'patterns' => 'array',
            'response_template' => 'encrypted',
            'requires_health_context' => 'boolean',
            'tested_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ChatbotTrainingCandidate::class, 'chatbot_training_candidate_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
