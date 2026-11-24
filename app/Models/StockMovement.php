<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class StockMovement extends Model
{
    public const TYPES = [
        'opening'      => 'Opening stock',
        'purchase'     => 'Purchase',
        'counter_sale' => 'Spare parts sale',
        'job'          => 'Used on job card',
        'return_in'    => 'Customer return',
        'return_out'   => 'Return to supplier',
        'adjustment'   => 'Adjustment',
    ];

    protected $fillable = [
        'part_id', 'date', 'type', 'qty_in', 'qty_out', 'balance_after', 'unit_cost',
        'reference_type', 'reference_id', 'reference_no', 'note', 'user_id',
    ];

    protected function casts(): array
    {
        return ['date' => 'datetime', 'qty_in' => 'decimal:2', 'qty_out' => 'decimal:2', 'balance_after' => 'decimal:2'];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The ONLY way stock changes. Locks the part row, updates stock_qty and writes the movement.
     *   StockMovement::record($part, 'opening', in: 10, unitCost: 25000, note: 'Opening stock');
     */
    public static function record(Part $part, string $type, float $in = 0, float $out = 0, float $unitCost = 0,
                                  ?Model $reference = null, ?string $referenceNo = null, ?string $note = null): self
    {
        return DB::transaction(function () use ($part, $type, $in, $out, $unitCost, $reference, $referenceNo, $note) {
            $locked = Part::whereKey($part->id)->lockForUpdate()->firstOrFail();
            $balance = round((float) $locked->stock_qty + $in - $out, 2);
            $locked->forceFill(['stock_qty' => $balance])->save();
            $part->stock_qty = $balance;

            return self::create([
                'part_id'        => $part->id,
                'date'           => now(),
                'type'           => $type,
                'qty_in'         => $in,
                'qty_out'        => $out,
                'balance_after'  => $balance,
                'unit_cost'      => $unitCost,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id'   => $reference?->getKey(),
                'reference_no'   => $referenceNo,
                'note'           => $note,
                'user_id'        => auth()->id(),
            ]);
        });
    }
}
