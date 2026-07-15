<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotPersona extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['tone_rules' => 'array', 'is_active' => 'boolean'];
    }
}
