<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLink extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(function (self $link): void {
            if ($link->is_primary && $link->is_active) {
                self::query()
                    ->where('product_id', $link->product_id)
                    ->whereKeyNot($link->getKey())
                    ->update(['is_primary' => false]);
            }
        });
    }

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_active' => 'boolean'];
    }
}
