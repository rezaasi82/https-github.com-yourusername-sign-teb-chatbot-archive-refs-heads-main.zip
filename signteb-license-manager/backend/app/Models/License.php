<?php

namespace App\Models;

use App\Domain\Licensing\LicenseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    protected $fillable = [
        'uuid', 'license_key', 'customer_id', 'product_id', 'subscription_id',
        'plan_id', 'status', 'activation_limit', 'expires_at', 'grace_ends_at',
        'transfers_used', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'expires_at' => 'datetime',
            'grace_ends_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(Activation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LicenseEvent::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }
}
