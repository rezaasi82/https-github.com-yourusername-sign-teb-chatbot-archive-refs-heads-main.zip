<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit log. Never update or delete rows; the production MySQL
 * user has no UPDATE/DELETE grant on this table.
 */
class LicenseEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['license_id', 'event', 'actor_type', 'actor_id', 'ip', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
