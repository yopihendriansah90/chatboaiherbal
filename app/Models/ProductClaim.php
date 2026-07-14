<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductClaim extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['effective_from' => 'datetime', 'effective_until' => 'datetime', 'is_active' => 'boolean'];
    }
}
