<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** A customer's website login. Kept separate from staff users: it can never open the management system. */
class ShopCustomer extends Authenticatable
{
    protected $fillable = ['customer_id', 'name', 'phone', 'email', 'google_id', 'password', 'last_login_at'];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'last_login_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('id');
    }

    public function firstName(): string
    {
        return strtok($this->name, ' ') ?: $this->name;
    }
}
