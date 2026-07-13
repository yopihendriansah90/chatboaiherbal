<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotSetting extends Model
{
    protected $fillable = [
        'telegram_bot_token',
        'telegram_webhook_secret',
        'telegram_webhook_url',
        'telegram_timeout',
        'groq_api_key',
        'parser_provider',
        'renderer_provider',
        'parser_fallback_enabled',
        'parser_fallback_order',
        'parser_model',
        'renderer_model',
        'natural_renderer_enabled',
        'parser_timeout',
        'renderer_timeout',
        'renderer_max_words',
        'memory_ttl_hours',
        'history_limit',
        'is_active',
        'updated_by',
    ];

    protected $hidden = [
        'telegram_bot_token',
        'telegram_webhook_secret',
        'groq_api_key',
    ];

    protected function casts(): array
    {
        return [
            'telegram_bot_token' => 'encrypted',
            'telegram_webhook_secret' => 'encrypted',
            'groq_api_key' => 'encrypted',
            'natural_renderer_enabled' => 'boolean',
            'parser_fallback_enabled' => 'boolean',
            'parser_fallback_order' => 'array',
            'is_active' => 'boolean',
            'telegram_timeout' => 'integer',
            'parser_timeout' => 'integer',
            'renderer_timeout' => 'integer',
            'renderer_max_words' => 'integer',
            'memory_ttl_hours' => 'integer',
            'history_limit' => 'integer',
        ];
    }
}
