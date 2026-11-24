<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const DRAFT = 'draft';
    public const ORDERED = 'ordered';
    public const PARTIAL = 'partial';
    public const RECEIVED = 'received';
    public const CANCELLED = 'cancelled';

    public const LABELS = [self::DRAFT => 'Draft', self::ORDERED => 'Ordered', self::PARTIAL => 'Part received', self::RECEIVED => 'Received', self::CANCELLED => 'Cancelled'];
    public const BADGES = [self::DRAFT => 'badge-gray', self::ORDERED => 'badge-amber', self::PARTIAL => 'badge-amber', self::RECEIVED => 'badge-green', self::CANCELLED => 'badge-red'];

    /** Statuses where the business owes the supplier. */
    public const OWING = [self::ORDERED, self::PARTIAL, self::RECEIVED];

    protected $fillable = [
        'number', 'supplier_id', 'date', 'expected_date', 'status', 'supplier_invoice_no', 'subtotal', 'tax_amount',
        'other_charges', 'grand_total', 'credited', 'paid', 'balance', 'notes', 'received_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date', 'expected_date' => 'date', 'received_at' => 'datetime',
            'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'other_charges' => 'decimal:2',
            'grand_total' => 'decimal:2', 'credited' => 'decimal:2', 'paid' => 'decimal:2', 'balance' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class)->orderBy('id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StockReturn::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function badge(): string
    {
        return self::BADGES[$this->status] ?? 'badge-gray';
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function canReceive(): bool
    {
        return in_array($this->status, [self::ORDERED, self::PARTIAL], true);
    }

    public function taxRate(): float
    {
        return (float) ($this->items->first()->tax_rate ?? 0);
    }

    public function refreshPaid(): void
    {
        $paid = round((float) $this->payments()->sum('amount'), 2);
        $this->forceFill([
            'paid'    => $paid,
            'balance' => round((float) $this->grand_total - (float) $this->credited - $paid, 2),
        ])->save();
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $q : $q->where(fn ($q) => $q->where('number', 'like', "%{$term}%")
            ->orWhere('supplier_invoice_no', 'like', "%{$term}%")
            ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$term}%")));
    }
}
