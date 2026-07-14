<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_category_product')
            ->withPivot(['priority', 'is_primary'])->withTimestamps();
    }

    public function recommendationRules(): HasMany
    {
        return $this->hasMany(ProductRecommendationRule::class);
    }
}
