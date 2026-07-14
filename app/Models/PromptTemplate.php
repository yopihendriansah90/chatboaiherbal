<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PromptTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_protected' => 'boolean', 'is_active' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class, 'business_profile_id');
    }

    public function domainPack(): BelongsTo
    {
        return $this->belongsTo(DomainPack::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PromptVersion::class);
    }

    public function publishedVersion(): HasOne
    {
        return $this->hasOne(PromptVersion::class)
            ->where('status', 'published')
            ->latestOfMany('version');
    }

    public function latestDraft(): HasOne
    {
        return $this->hasOne(PromptVersion::class)
            ->where('status', 'draft')
            ->latestOfMany('version');
    }
}
