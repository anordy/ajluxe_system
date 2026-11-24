<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'part_id', 'qty', 'received_qty', 'rate', 'tax_rate', 'amount'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2', 'received_qty' => 'decimal:2', 'rate' => 'decimal:2', 'tax_rate' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    public function remaining(): float
    {
        return max(0, round((float) $this->qty - (float) $this->received_qty, 2));
    }
}
