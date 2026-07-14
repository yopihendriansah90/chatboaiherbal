<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['tested_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'prompt_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
