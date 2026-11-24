<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'contact_person', 'phone', 'email', 'address', 'tin', 'opening_balance', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'opening_balance' => 'decimal:2'];
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
            ->orWhere('contact_person', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%"));
    }

    /** What the business still owes this supplier. */
    public function balance(): float
    {
        $pos = (float) DB::table('purchase_orders')->where('supplier_id', $this->id)
            ->whereIn('status', ['ordered', 'partial', 'received'])->sum('balance');

        return (float) $this->opening_balance + $pos;
    }

    public function deleteBlocker(): ?string
    {
        foreach (['purchase_orders' => 'purchase orders', 'supplier_payments' => 'payments'] as $table => $label) {
            if (DB::table($table)->where('supplier_id', $this->id)->exists()) {
                return "This supplier has {$label}. Deactivate it instead.";
            }
        }

        return null;
    }
}
