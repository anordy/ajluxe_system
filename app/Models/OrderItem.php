<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'part_id', 'name', 'qty', 'list_price', 'discount_percent', 'price', 'amount'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2', 'list_price' => 'decimal:2', 'discount_percent' => 'decimal:2', 'price' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    public function qtyLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->qty, 2), '0'), '.');
    }
}
