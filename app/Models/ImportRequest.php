<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Import for you": a customer asks us to bring in an engine, gearbox or parts from abroad. */
class ImportRequest extends Model
{
    public const STATUSES = [
        'new'       => 'New',
        'quoted'    => 'Quote sent',
        'confirmed' => 'Deposit paid',
        'ordered'   => 'Ordered from supplier',
        'shipping'  => 'On the way',
        'arrived'   => 'Arrived in Dar',
        'completed' => 'Collected / fitted',
        'cancelled' => 'Cancelled',
    ];

    /** What customers see in their account for each status. */
    public const CUSTOMER_TEXT = [
        'new'       => 'We are looking for it and will send you a price.',
        'quoted'    => 'We sent you a price. Reply or call us to go ahead.',
        'confirmed' => 'Thank you, your deposit is in. We are placing the order.',
        'ordered'   => 'Ordered from our supplier.',
        'shipping'  => 'On its way to Dar es Salaam.',
        'arrived'   => 'It has arrived. We will call you to collect or fit it.',
        'completed' => 'Done. Thank you for importing with us.',
        'cancelled' => 'This request was cancelled.',
    ];

    public const ITEM_TYPES = ['Engine', 'Gearbox / transmission', 'Engine parts', 'Suspension & steering', 'Body parts & lights',
        'Electrical & ECU', 'Wheels & tyres', 'Other'];

    public const CONDITIONS = ['Used (tested, ex-Japan / UK)', 'New (genuine or OEM)', 'Reconditioned', 'Any – best price'];

    protected $fillable = ['ref', 'item_type', 'description', 'make', 'model', 'year', 'engine_code', 'vin', 'source_country', 'condition',
        'quantity', 'budget', 'name', 'phone', 'email', 'shop_customer_id', 'status', 'quote_amount', 'deposit_paid', 'eta',
        'customer_message', 'staff_note', 'handled_by'];

    protected $casts = ['eta' => 'date', 'year' => 'integer', 'quantity' => 'integer', 'budget' => 'decimal:2', 'quote_amount' => 'decimal:2', 'deposit_paid' => 'decimal:2'];

    protected static function booted(): void
    {
        static::created(fn (self $r) => $r->ref ?: $r->updateQuietly(['ref' => 'IMP-'.str_pad((string) $r->id, 4, '0', STR_PAD_LEFT)]));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(ShopCustomer::class, 'shop_customer_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function car(): string
    {
        return trim($this->make.' '.$this->model.($this->year ? ' ('.$this->year.')' : '')) ?: '—';
    }

    /** Countries offered on the form, from Admin › Website texts ("Japan | text" lines). */
    public static function countries(): array
    {
        return array_column(\App\Support\Settings::pairs('web_import_countries'), 0);
    }

    public function balance(): ?float
    {
        return $this->quote_amount !== null ? max(0, (float) $this->quote_amount - (float) $this->deposit_paid) : null;
    }
}
