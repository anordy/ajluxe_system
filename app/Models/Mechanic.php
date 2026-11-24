<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Mechanic extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'phone', 'specialization', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Optional login account (role "mechanic") so they can update their own job cards. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function openJobsCount(): int
    {
        return DB::table('job_cards')->where('mechanic_id', $this->id)->whereNull('deleted_at')
            ->whereNotIn('status', ['Delivered', 'Cancelled'])->count();
    }

    public function deleteBlocker(): ?string
    {
        return DB::table('job_cards')->where('mechanic_id', $this->id)->exists()
            ? 'This mechanic is assigned to job cards. Deactivate instead.'
            : null;
    }
}
