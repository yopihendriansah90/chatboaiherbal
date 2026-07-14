<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function domainPacks(): BelongsToMany
    {
        return $this->belongsToMany(DomainPack::class, 'business_domain_packs')
            ->withPivot(['priority', 'is_enabled', 'is_default', 'configuration'])
            ->withTimestamps();
    }

    public function companyProfile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(CompanyLocation::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(CompanyFaq::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
