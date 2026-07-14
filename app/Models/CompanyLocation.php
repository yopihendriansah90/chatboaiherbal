<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyLocation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_active' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class, 'business_profile_id');
    }
}
