<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ConversationExport extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'scope',
        'format',
        'filename',
        'included_identity',
        'conversation_count',
        'filters',
        'exported_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $export): void {
            $export->uuid ??= (string) Str::uuid();
            $export->exported_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'included_identity' => 'boolean',
            'conversation_count' => 'integer',
            'filters' => 'array',
            'exported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
