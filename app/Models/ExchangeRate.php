<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeRate extends Model
{
    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate',
        'rate_date',
        'source_name',
        'source_url',
        'notes',
        'is_active',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'rate_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }

    public static function current(): ?self
    {
        return static::query()
            ->where('base_currency', 'USD')
            ->where('quote_currency', 'IDR')
            ->where('is_active', true)
            ->where('rate_date', '<=', today())
            ->latest('rate_date')
            ->latest('id')
            ->first();
    }
}
