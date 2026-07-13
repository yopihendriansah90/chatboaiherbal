<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModel extends Model
{
    public const STATUSES = [
        'recommended' => 'Direkomendasikan',
        'active' => 'Aktif',
        'archived' => 'Diarsipkan',
    ];

    protected $fillable = [
        'ai_provider_id',
        'model_id',
        'display_name',
        'can_parse',
        'can_render',
        'supports_structured_output',
        'context_window',
        'status',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'can_parse' => 'boolean',
            'can_render' => 'boolean',
            'supports_structured_output' => 'boolean',
            'context_window' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(AiModelPrice::class);
    }

    public function currentPrice(): ?AiModelPrice
    {
        return $this->prices()
            ->where('is_active', true)
            ->where('effective_at', '<=', now())
            ->latest('effective_at')
            ->latest('id')
            ->first();
    }

    public function isAvailable(): bool
    {
        return $this->status !== 'archived' && (bool) $this->provider?->is_enabled;
    }

    public function optionLabel(): string
    {
        return ($this->provider?->name ?? 'Provider').' — '.$this->display_name;
    }
}
