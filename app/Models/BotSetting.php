<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSetting extends Model
{
    protected $fillable = [
        'business_profile_id',
        'telegram_bot_token',
        'telegram_webhook_secret',
        'telegram_webhook_url',
        'telegram_timeout',
        'groq_api_key',
        'parser_provider',
        'renderer_provider',
        'parser_fallback_enabled',
        'parser_fallback_order',
        'parser_ai_model_id',
        'renderer_ai_model_id',
        'fallback_ai_model_ids',
        'parser_model',
        'renderer_model',
        'natural_renderer_enabled',
        'parser_timeout',
        'renderer_timeout',
        'renderer_max_words',
        'memory_ttl_hours',
        'history_limit',
        'allow_domain_switching',
        'ambiguous_domain_behavior',
        'chat_history_enabled',
        'chat_history_retention_days',
        'inactive_contact_days',
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
            'fallback_ai_model_ids' => 'array',
            'is_active' => 'boolean',
            'telegram_timeout' => 'integer',
            'parser_timeout' => 'integer',
            'renderer_timeout' => 'integer',
            'renderer_max_words' => 'integer',
            'memory_ttl_hours' => 'integer',
            'history_limit' => 'integer',
            'allow_domain_switching' => 'boolean',
            'chat_history_enabled' => 'boolean',
            'chat_history_retention_days' => 'integer',
            'inactive_contact_days' => 'integer',
        ];
    }

    public function parserModelSelection(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'parser_ai_model_id');
    }

    public function rendererModelSelection(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'renderer_ai_model_id');
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }
}
