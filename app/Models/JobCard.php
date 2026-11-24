<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobCard extends Model
{
    use SoftDeletes;

    public const OPEN = 'Open';
    public const IN_PROGRESS = 'In Progress';
    public const WAITING_PARTS = 'Waiting for Parts';
    public const QUALITY_CHECK = 'Quality Check';
    public const READY = 'Ready for Delivery';
    public const DELIVERED = 'Delivered';
    public const CANCELLED = 'Cancelled';

    /** Workflow order (Cancelled is separate). */
    public const STATUSES = [self::OPEN, self::IN_PROGRESS, self::WAITING_PARTS, self::QUALITY_CHECK, self::READY, self::DELIVERED];
    public const CLOSED = [self::DELIVERED, self::CANCELLED];

    /** status => tailwind badge classes */
    /** Light badge per status: Open grey, In Progress blue, Waiting amber, QC violet, Ready teal, Delivered green, Cancelled red. */
    public const BADGES = [
        self::OPEN          => 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-200',
        self::IN_PROGRESS   => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
        self::WAITING_PARTS => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-200',
        self::QUALITY_CHECK => 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
        self::READY         => 'bg-teal-50 text-teal-700 ring-1 ring-inset ring-teal-200',
        self::DELIVERED     => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
        self::CANCELLED     => 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200',
    ];

    /** Solid version: the current step and the selected status tab. */
    public const SOLID = [
        self::OPEN          => 'bg-slate-600 text-white',
        self::IN_PROGRESS   => 'bg-blue-600 text-white',
        self::WAITING_PARTS => 'bg-amber-500 text-white',
        self::QUALITY_CHECK => 'bg-violet-600 text-white',
        self::READY         => 'bg-teal-600 text-white',
        self::DELIVERED     => 'bg-emerald-600 text-white',
        self::CANCELLED     => 'bg-red-600 text-white',
    ];

    /** Small colour dot beside a status name. */
    public const DOTS = [
        self::OPEN          => 'bg-slate-400',
        self::IN_PROGRESS   => 'bg-blue-500',
        self::WAITING_PARTS => 'bg-amber-500',
        self::QUALITY_CHECK => 'bg-violet-500',
        self::READY         => 'bg-teal-500',
        self::DELIVERED     => 'bg-emerald-500',
        self::CANCELLED     => 'bg-red-500',
    ];

    /** Hex colours for charts, same order of meaning. */
    public const HEX = [
        self::OPEN => '#64748b', self::IN_PROGRESS => '#2563eb', self::WAITING_PARTS => '#f59e0b',
        self::QUALITY_CHECK => '#7c3aed', self::READY => '#0d9488', self::DELIVERED => '#059669', self::CANCELLED => '#dc2626',
    ];

    protected $fillable = [
        'number', 'customer_id', 'vehicle_id', 'mechanic_id', 'status', 'priority', 'odometer_in', 'fuel_level',
        'complaints', 'inspection_notes', 'work_notes', 'received_at', 'promised_at', 'delivered_at',
        'subtotal', 'discount_rate', 'discount', 'tax_rate', 'tax_amount', 'grand_total', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at'  => 'datetime',
            'promised_at'  => 'datetime',
            'delivered_at' => 'datetime',
            'subtotal'     => 'decimal:2',
            'discount'     => 'decimal:2',
            'discount_rate'=> 'decimal:2',
            'tax_rate'     => 'decimal:2',
            'tax_amount'   => 'decimal:2',
            'grand_total'  => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JobCardItem::class)->orderBy('id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(JobStatusHistory::class)->latest('id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->where('status', '!=', Invoice::CANCELLED);
    }

    /** Advances paid before an invoice existed + payments on the invoice. */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED, true);
    }

    public function isInvoiced(): bool
    {
        return $this->invoice()->exists();
    }

    /** Items, discount and tax can only change while the job is open and not invoiced. */
    public function isEditable(): bool
    {
        return ! $this->isClosed() && ! $this->isInvoiced();
    }

    public function badge(): string
    {
        return self::BADGES[$this->status] ?? 'bg-ink-100 text-ink-700';
    }

    public function totalCost(): float
    {
        return (float) $this->items->sum('cost');
    }

    /** Garage profit excluding tax: (subtotal − discount) − cost of parts/outside work. */
    public function profit(): float
    {
        return (float) $this->subtotal - (float) $this->discount - $this->totalCost();
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function isOverdue(): bool
    {
        return ! $this->isClosed() && $this->promised_at && $this->promised_at->isPast();
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', self::CLOSED);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }
        $compact = str_replace(' ', '', $term);

        return $q->where(fn ($q) => $q
            ->where('number', 'like', "%{$term}%")
            ->orWhereHas('vehicle', fn ($v) => $v->where('reg_no', 'like', "%{$term}%")
                ->orWhereRaw("REPLACE(reg_no, ' ', '') like ?", ["%{$compact}%"])
                ->orWhere('model', 'like', "%{$term}%"))
            ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")));
    }
}
