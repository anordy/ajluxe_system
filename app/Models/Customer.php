<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'type', 'phone', 'alt_phone', 'email', 'address', 'tin',
        'credit_limit', 'opening_balance', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'credit_limit'    => 'decimal:2',
            'opening_balance' => 'decimal:2',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(JobCard::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $q : $q->where(fn ($q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('code', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('alt_phone', 'like', "%{$term}%")
            ->orWhere('tin', 'like', "%{$term}%"));
    }

    /** Amount the customer still owes (opening balance + unpaid invoices). */
    public function balance(): float
    {
        $invoices = (float) DB::table('invoices')->where('customer_id', $this->id)
            ->whereNull('deleted_at')->where('status', '!=', 'cancelled')->sum('balance');

        return (float) $this->opening_balance + $invoices;
    }

    /** Reason this customer cannot be deleted, or null if it can. */
    public function deleteBlocker(): ?string
    {
        if ($this->vehicles()->exists()) {
            return 'This customer has vehicles. Remove or move the vehicles first, or deactivate the customer instead.';
        }
        foreach (['job_cards' => 'job cards', 'invoices' => 'invoices', 'payments' => 'payments'] as $table => $label) {
            if (DB::table($table)->where('customer_id', $this->id)->exists()) {
                return "This customer has {$label}. Deactivate the customer instead.";
            }
        }

        return null;
    }
}
