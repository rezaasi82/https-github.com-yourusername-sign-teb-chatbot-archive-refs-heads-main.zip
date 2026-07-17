<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'uuid', 'name', 'slug', 'key_prefix', 'category', 'description',
        'status', 'signing_secret', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'signing_secret' => 'encrypted',
            'metadata' => 'array',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function latestVersion(string $channel = 'stable'): ?ProductVersion
    {
        return $this->versions()
            ->where('channel', $channel)
            ->whereNotNull('released_at')
            ->get()
            ->sortByDesc(fn (ProductVersion $v) => $v->version, SORT_NATURAL)
            ->first();
    }
}
