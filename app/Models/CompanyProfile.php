<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyProfile extends Model
{
    protected $guarded = [];

    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class, 'business_profile_id');
    }
}
