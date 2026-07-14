<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    protected $guarded = [];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['amount', 'unit', 'main_content', 'symptom_context', 'approved_narrative', 'legacy_warning'])
            ->withTimestamps();
    }
}
