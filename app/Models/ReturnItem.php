<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    protected $fillable = ['return_id', 'part_id', 'qty', 'rate', 'amount'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function stockReturn(): BelongsTo
    {
        return $this->belongsTo(StockReturn::class, 'return_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }
}
