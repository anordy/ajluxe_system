<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An order placed on the website. */
class Order extends Model
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const READY = 'ready';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    public const LABELS = [
        self::PENDING => 'Waiting for confirmation', self::CONFIRMED => 'Confirmed', self::READY => 'Ready',
        self::COMPLETED => 'Completed', self::CANCELLED => 'Cancelled',
    ];
    public const BADGES = [
        self::PENDING => 'badge-amber', self::CONFIRMED => 'badge-gray', self::READY => 'badge-green',
        self::COMPLETED => 'badge-dark', self::CANCELLED => 'badge-red',
    ];

    public const PAYMENT_METHODS = [
        'lipa_namba' => 'M-Pesa Lipa Namba',
        'bank'       => 'Bank transfer (CRDB)',
        'on_pickup'  => 'Pay when I collect / on delivery',
    ];
    public const FULFILMENT = ['pickup' => 'Collect from the shop', 'delivery' => 'Delivery'];

    protected $fillable = [
        'number', 'shop_customer_id', 'customer_id', 'status', 'fulfilment', 'delivery_address', 'contact_phone',
        'payment_method', 'payment_reference', 'notes', 'subtotal', 'discount', 'total', 'invoice_id', 'cancel_reason',
        'handled_by', 'confirmed_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2',
            'confirmed_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function shopCustomer(): BelongsTo
    {
        return $this->belongsTo(ShopCustomer::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function badge(): string
    {
        return self::BADGES[$this->status] ?? 'badge-gray';
    }

    public function readyLabel(): string
    {
        return $this->fulfilment === 'delivery' ? 'Out for delivery' : 'Ready for collection';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::CONFIRMED, self::READY], true);
    }
}
