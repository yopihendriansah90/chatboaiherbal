<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class, 'business_profile_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_product')
            ->withPivot(['priority', 'is_primary'])->withTimestamps();
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class)
            ->withPivot(['amount', 'unit', 'main_content', 'symptom_context', 'approved_narrative', 'legacy_warning'])
            ->withTimestamps();
    }

    public function claims(): HasMany
    {
        return $this->hasMany(ProductClaim::class);
    }

    public function contraindications(): HasMany
    {
        return $this->hasMany(ProductContraindication::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(ProductInventory::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ProductLink::class);
    }

    public function recommendationRules(): HasMany
    {
        return $this->hasMany(ProductRecommendationRule::class);
    }
}
