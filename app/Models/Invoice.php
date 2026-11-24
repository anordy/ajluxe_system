<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Garage invoices (type "garage") and, from Phase 4, parts-counter sales (type "counter"). */
class Invoice extends Model
{
    use SoftDeletes;

    public const UNPAID = 'unpaid';
    public const PARTIAL = 'partial';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [self::UNPAID => 'Unpaid', self::PARTIAL => 'Part paid', self::PAID => 'Paid', self::CANCELLED => 'Cancelled'];
    public const STATUS_BADGES = [self::UNPAID => 'badge-red', self::PARTIAL => 'badge-amber', self::PAID => 'badge-green', self::CANCELLED => 'badge-gray'];

    protected $fillable = [
        'number', 'type', 'job_card_id', 'customer_id', 'walk_in_name', 'date', 'subtotal', 'discount',
        'tax_amount', 'other_charges', 'grand_total', 'credited', 'paid', 'balance', 'status', 'notes', 'void_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date'        => 'date',
            'subtotal'    => 'decimal:2',
            'discount'    => 'decimal:2',
            'tax_amount'  => 'decimal:2',
            'grand_total' => 'decimal:2',
            'credited'    => 'decimal:2',
            'paid'        => 'decimal:2',
            'balance'     => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StockReturn::class);
    }

    public function isCounter(): bool
    {
        return $this->type === 'counter';
    }

    /** Net amount the customer must pay after returns. */
    public function netTotal(): float
    {
        return round((float) $this->grand_total - (float) $this->credited, 2);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customerName(): string
    {
        return $this->customer?->name ?? ($this->walk_in_name ?: 'Walk-in customer');
    }

    /** Recalculate paid / balance / status from the payments table. */
    public function refreshPaid(): void
    {
        if ($this->status === self::CANCELLED) {
            return;
        }
        $paid = round((float) $this->payments()->sum('amount'), 2);
        $balance = round((float) $this->grand_total - (float) $this->credited - $paid, 2);
        $this->forceFill([
            'paid'    => $paid,
            'balance' => $balance,
            'status'  => $balance <= 0.009 ? self::PAID : ($paid > 0 ? self::PARTIAL : self::UNPAID),
        ])->save();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', '!=', self::CANCELLED);
    }
}
