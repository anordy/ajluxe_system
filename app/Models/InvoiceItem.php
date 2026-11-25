<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = ['invoice_id', 'part_id', 'service_id', 'description', 'qty', 'rate', 'tax_rate', 'amount', 'cost', 'stock_cost', 'sourced_qty', 'source_supplier_id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2', 'cost' => 'decimal:2', 'stock_cost' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
