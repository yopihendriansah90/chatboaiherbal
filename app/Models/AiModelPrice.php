<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModelPrice extends Model
{
    protected $fillable = [
        'ai_provider_id',
        'ai_model_id',
        'model',
        'input_price_per_million_usd',
        'cached_input_price_per_million_usd',
        'output_price_per_million_usd',
        'effective_at',
        'source_url',
        'is_active',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'input_price_per_million_usd' => 'decimal:8',
            'cached_input_price_per_million_usd' => 'decimal:8',
            'output_price_per_million_usd' => 'decimal:8',
            'effective_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }
}
