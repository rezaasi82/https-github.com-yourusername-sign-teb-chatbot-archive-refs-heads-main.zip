<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid', 'type', 'name', 'company_name', 'email', 'phone', 'country',
        'city', 'locale', 'billing_address', 'tax_id', 'password', 'notes', 'is_blocked',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'billing_address' => 'array',
            'is_blocked' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
