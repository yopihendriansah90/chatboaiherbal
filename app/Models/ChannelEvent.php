<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChannelEvent extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
            $event->correlation_id ??= (string) Str::uuid();
            $event->available_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'available_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
