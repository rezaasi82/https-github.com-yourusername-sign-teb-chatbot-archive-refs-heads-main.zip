<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'license_id', 'service', 'tokens_used', 'request_count', 'period', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
