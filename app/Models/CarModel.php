<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A car in the website's "find parts for your car" list. */
class CarModel extends Model
{
    protected $fillable = ['make', 'model', 'year_from', 'year_to', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'year_from' => 'integer', 'year_to' => 'integer'];

    public function engines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CarEngine::class)->orderBy('litres')->orderBy('fuel');
    }

    public function name(): string
    {
        return $this->make.' '.$this->model;
    }

    public function years(): string
    {
        return match (true) {
            $this->year_from && $this->year_to => $this->year_from.'–'.$this->year_to,
            (bool) $this->year_from            => $this->year_from.' on',
            (bool) $this->year_to              => 'up to '.$this->year_to,
            default                            => 'All years',
        };
    }
}
