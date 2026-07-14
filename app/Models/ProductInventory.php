<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductInventory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['track_stock' => 'boolean'];
    }
}
