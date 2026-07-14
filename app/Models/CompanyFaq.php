<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyFaq extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'is_active' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class, 'business_profile_id');
    }
}
