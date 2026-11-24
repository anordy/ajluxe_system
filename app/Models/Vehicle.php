<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id', 'reg_no', 'make', 'model', 'year', 'fuel_type', 'color',
        'vin', 'engine_no', 'odometer', 'notes',
    ];

    protected function casts(): array
    {
        return ['year' => 'integer', 'odometer' => 'integer'];
    }

    /** "t 123 abc" -> "T 123 ABC" (single spaces, upper case) */
    public static function normalizeReg(string $reg): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($reg)));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function title(): string
    {
        return trim($this->reg_no.' · '.trim($this->make.' '.$this->model), ' ·');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }
        $compact = str_replace(' ', '', $term);

        return $q->where(fn ($q) => $q
            ->where('reg_no', 'like', "%{$term}%")
            ->orWhereRaw("REPLACE(reg_no, ' ', '') like ?", ["%{$compact}%"])
            ->orWhere('make', 'like', "%{$term}%")
            ->orWhere('model', 'like', "%{$term}%")
            ->orWhere('vin', 'like', "%{$term}%")
            ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")));
    }

    public function deleteBlocker(): ?string
    {
        return DB::table('job_cards')->where('vehicle_id', $this->id)->exists()
            ? 'This vehicle has job cards and cannot be deleted.'
            : null;
    }
}
