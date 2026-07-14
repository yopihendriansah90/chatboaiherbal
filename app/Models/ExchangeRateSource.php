<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRateSource extends Model
{
    protected $fillable = [
        'provider',
        'name',
        'api_key',
        'endpoint',
        'is_enabled',
        'auto_sync',
        'warning_percent',
        'last_attempted_at',
        'last_success_at',
        'last_error_code',
        'last_response_at',
        'updated_by',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'auto_sync' => 'boolean',
            'warning_percent' => 'decimal:2',
            'last_attempted_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_response_at' => 'datetime',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
