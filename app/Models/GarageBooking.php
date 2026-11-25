<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A car booked in from the website's garage page. */
class GarageBooking extends Model
{
    public const STATUSES = ['new' => 'New', 'confirmed' => 'Confirmed', 'booked_in' => 'Job card opened', 'cancelled' => 'Cancelled'];

    public const BADGES = ['new' => 'badge-red', 'confirmed' => 'badge-amber', 'booked_in' => 'badge-green', 'cancelled' => 'badge-gray'];

    protected $fillable = ['name', 'phone', 'car', 'plate', 'work', 'preferred_date', 'status', 'shop_customer_id',
        'customer_id', 'job_card_id', 'staff_note', 'handled_by', 'handled_at'];

    protected $casts = ['preferred_date' => 'date', 'handled_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** The garage customer with this phone number (last 9 digits match), if any. */
    public function matchCustomer(): ?Customer
    {
        $digits = substr(preg_replace('/\D+/', '', $this->phone), -9);
        if (strlen($digits) < 9) {
            return null;
        }

        return Customer::where(fn ($q) => $q->where('phone', 'like', "%{$digits}")->orWhere('alt_phone', 'like', "%{$digits}")
            ->orWhereRaw("REPLACE(phone, ' ', '') like ?", ["%{$digits}"]))->first();
    }
}
