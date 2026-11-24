<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A part fits this car model (and engine, when set). */
class PartVehicle extends Model
{
    protected $fillable = ['part_id', 'car_model_id', 'car_engine_id'];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    public function engine(): BelongsTo
    {
        return $this->belongsTo(CarEngine::class, 'car_engine_id');
    }

    public function label(): string
    {
        return ($this->carModel?->name() ?? '?').($this->engine ? ' – '.$this->engine->label() : ' – all engines');
    }
}
