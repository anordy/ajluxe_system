<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Can't find your car?" request sent from the website. */
class CarRequest extends Model
{
    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'added' => 'Car added', 'closed' => 'Closed'];

    protected $fillable = ['vin', 'make', 'model', 'year', 'parts', 'name', 'phone', 'email', 'wants_offers',
        'shop_customer_id', 'status', 'staff_note', 'handled_by', 'handled_at'];

    protected $casts = ['wants_offers' => 'boolean', 'handled_at' => 'datetime', 'year' => 'integer'];

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
        return trim($this->make.' '.$this->model.($this->year ? ' ('.$this->year.')' : ''));
    }
}
