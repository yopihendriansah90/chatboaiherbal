<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelIntegration extends Model
{
    protected $fillable = [
        'key',
        'driver',
        'name',
        'description',
        'credentials',
        'settings',
        'is_enabled',
        'updated_by',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function identities(): HasMany
    {
        return $this->hasMany(ChatbotChannelIdentity::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatbotConversation::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
