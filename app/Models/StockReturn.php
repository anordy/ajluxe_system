<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A return of goods ("Return" is a reserved word in PHP, hence the name).
 *  - type customer: goods come back from a customer (credit note, CRN)
 *  - type supplier: goods go back to a supplier (debit note, DBN)
 */
class StockReturn extends Model
{
    protected $table = 'returns';

    public const TYPES = ['customer' => 'Customer return', 'supplier' => 'Return to supplier'];

    protected $fillable = ['number', 'type', 'invoice_id', 'purchase_order_id', 'customer_id', 'supplier_id', 'date',
        'total', 'refund_amount', 'refund_mode', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'total' => 'decimal:2', 'refund_amount' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id')->orderBy('id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCustomer(): bool
    {
        return $this->type === 'customer';
    }

    public function partyName(): string
    {
        return $this->isCustomer()
            ? ($this->customer?->name ?? $this->invoice?->customerName() ?? 'Walk-in customer')
            : ($this->supplier?->name ?? '—');
    }
}
