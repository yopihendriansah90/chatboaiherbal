<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    public const TYPES = ['groq', 'gemini', 'openai'];

    protected $fillable = [
        'provider',
        'name',
        'api_key',
        'parser_model',
        'renderer_model',
        'parser_timeout',
        'renderer_timeout',
        'is_enabled',
        'priority',
        'last_test_status',
        'last_error_code',
        'last_latency_ms',
        'last_tested_at',
        'updated_by',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'parser_timeout' => 'integer',
            'renderer_timeout' => 'integer',
            'priority' => 'integer',
            'last_latency_ms' => 'integer',
            'last_tested_at' => 'datetime',
        ];
    }
}
