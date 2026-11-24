<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Labour services. pricing = "fixed" (one price per job) or "hourly" (rate × hours worked).
 */
class Service extends Model
{
    use HasFactory;

    public const PRICING = ['fixed' => 'Fixed price', 'hourly' => 'Per hour'];

    protected $fillable = ['code', 'name', 'category', 'pricing', 'rate', 'est_hours', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'rate' => 'decimal:2', 'est_hours' => 'decimal:2'];
    }

    public function isHourly(): bool
    {
        return $this->pricing === 'hourly';
    }

    /** "TZS 25,000 / hr"  or  "TZS 80,000" */
    public function priceLabel(): string
    {
        return money($this->rate).($this->isHourly() ? ' / hr' : '');
    }

    /** Default charge when added to a job card: hourly = rate × estimated hours. */
    public function defaultAmount(): float
    {
        return $this->isHourly() ? (float) $this->rate * max((float) $this->est_hours, 1) : (float) $this->rate;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $q : $q->where(fn ($q) => $q
            ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")->orWhere('category', 'like', "%{$term}%"));
    }

    public function deleteBlocker(): ?string
    {
        foreach (['job_card_items', 'invoice_items'] as $table) {
            if (DB::table($table)->where('service_id', $this->id)->exists()) {
                return 'This service is used on job cards or invoices. Deactivate it instead.';
            }
        }

        return null;
    }
}
