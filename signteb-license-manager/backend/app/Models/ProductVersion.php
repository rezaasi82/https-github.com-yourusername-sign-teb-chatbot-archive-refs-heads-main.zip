<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVersion extends Model
{
    protected $fillable = [
        'product_id', 'version', 'channel', 'release_notes', 'min_php', 'min_wp',
        'artifact_path', 'artifact_sha256', 'download_count', 'released_at',
    ];

    protected function casts(): array
    {
        return ['released_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
