<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DomainPack extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(BusinessProfile::class, 'business_domain_packs')
            ->withPivot(['priority', 'is_enabled', 'is_default', 'configuration'])
            ->withTimestamps();
    }
}
