<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activation extends Model
{
    protected $fillable = [
        'license_id', 'domain', 'domain_hash', 'site_url', 'ip',
        'device_fingerprint', 'environment', 'sdk_version', 'wp_version',
        'php_version', 'is_active', 'activated_at', 'deactivated_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
