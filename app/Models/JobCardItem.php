<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobCardItem extends Model
{
    public const TYPES = ['service' => 'Labour', 'part' => 'Part', 'other' => 'Other'];

    protected $fillable = ['job_card_id', 'type', 'service_id', 'part_id', 'description', 'qty', 'rate', 'amount', 'cost', 'stock_cost'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2', 'cost' => 'decimal:2', 'stock_cost' => 'decimal:2'];
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** "4 hrs" for hourly labour, "2 Pcs" for parts, "1" otherwise. */
    public function qtyLabel(): string
    {
        $q = rtrim(rtrim(number_format((float) $this->qty, 2), '0'), '.');
        if ($this->type === 'service' && $this->service?->isHourly()) {
            return $q.' hr'.((float) $this->qty == 1 ? '' : 's');
        }
        if ($this->type === 'part' && $this->part) {
            return $q.' '.$this->part->unit;
        }

        return $q;
    }
}
