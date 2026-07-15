<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ProductPrice extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $price): void {
            if (! $price->is_active) {
                return;
            }
            if ($price->effective_from && $price->effective_until && $price->effective_until <= $price->effective_from) {
                throw ValidationException::withMessages(['effective_until' => 'Masa berlaku akhir harus setelah waktu mulai.']);
            }

            $overlaps = self::query()
                ->where('product_id', $price->product_id)
                ->where('currency', strtoupper((string) $price->currency))
                ->where('is_active', true)
                ->when($price->exists, fn ($query) => $query->whereKeyNot($price->getKey()))
                ->when($price->effective_from, fn ($query) => $query
                    ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $price->effective_from)))
                ->when($price->effective_until, fn ($query) => $query
                    ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<', $price->effective_until)))
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages(['effective_from' => 'Masa berlaku harga bertumpang tindih dengan harga aktif lainnya.']);
            }

            $price->currency = strtoupper((string) $price->currency);
        });
    }

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'effective_from' => 'datetime', 'effective_until' => 'datetime', 'is_active' => 'boolean'];
    }
}
