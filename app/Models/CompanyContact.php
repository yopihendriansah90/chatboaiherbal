<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyContact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_public' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class, 'business_profile_id');
    }
}
