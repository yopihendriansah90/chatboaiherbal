<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'input_tokens' => 'integer',
            'cached_input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'total_tokens' => 'integer',
            'latency_ms' => 'integer',
            'input_price_per_million_usd' => 'decimal:8',
            'cached_input_price_per_million_usd' => 'decimal:8',
            'output_price_per_million_usd' => 'decimal:8',
            'usd_idr_rate' => 'decimal:6',
            'input_cost_usd' => 'decimal:10',
            'output_cost_usd' => 'decimal:10',
            'total_cost_usd' => 'decimal:10',
            'total_cost_idr' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function modelPrice(): BelongsTo
    {
        return $this->belongsTo(AiModelPrice::class, 'ai_model_price_id');
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }

    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }

    public function chatbotConversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class);
    }

    public function chatbotMessage(): BelongsTo
    {
        return $this->belongsTo(ChatbotMessage::class);
    }
}
