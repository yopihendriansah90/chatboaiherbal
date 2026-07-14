<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_active' => 'boolean'];
    }
}
