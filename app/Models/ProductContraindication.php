<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductContraindication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'is_active' => 'boolean'];
    }
}
