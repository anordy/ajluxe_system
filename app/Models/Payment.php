<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money received from a customer: an advance on a job card, or a payment on an invoice. */
class Payment extends Model
{
    protected $fillable = ['number', 'invoice_id', 'job_card_id', 'customer_id', 'date', 'amount', 'mode', 'reference', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAdvance(): bool
    {
        return $this->invoice_id === null;
    }
}
