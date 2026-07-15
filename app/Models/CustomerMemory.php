<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMemory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'encrypted', 'consented_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
