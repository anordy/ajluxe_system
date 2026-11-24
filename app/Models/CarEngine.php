<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One engine of a car model, e.g. "3.0 Diesel (1KD-FTV)". Shown to staff only. */
class CarEngine extends Model
{
    public const FUELS = ['Petrol', 'Diesel', 'Hybrid', 'Electric'];

    protected $fillable = ['car_model_id', 'code', 'litres', 'fuel', 'year_from', 'year_to'];

    protected $casts = ['litres' => 'decimal:1', 'year_from' => 'integer', 'year_to' => 'integer'];

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    public function label(): string
    {
        $size = $this->litres !== null ? number_format((float) $this->litres, 1) : '';
        $years = $this->year_from ? ' '.$this->year_from.'–'.($this->year_to ?: '') : '';

        return trim($size.' '.$this->fuel).($this->code ? ' ('.$this->code.')' : '').$years;
    }

    /** "3.0 Diesel | 1KD-FTV | 2005-2015" lines (car list editor and CSV) → attributes */
    public static function parseLine(string $line): ?array
    {
        $parts = array_map('trim', explode('|', $line));
        if (($parts[0] ?? '') === '') {
            return null;
        }
        $litres = preg_match('/(\d+(?:[.,]\d)?)/', $parts[0], $m) ? (float) str_replace(',', '.', $m[1]) : null;
        $fuel = 'Petrol';
        foreach (['diesel' => 'Diesel', ' d' => 'Diesel', 'hybrid' => 'Hybrid', ' h' => 'Hybrid', 'electric' => 'Electric'] as $word => $f) {
            if (str_contains(' '.strtolower($parts[0]), $word)) {
                $fuel = $f;
                break;
            }
        }
        $years = [null, null];
        if (preg_match('/(\d{4})\s*[-–]\s*(\d{4})?/', $parts[2] ?? '', $y)) {
            $years = [(int) $y[1], isset($y[2]) && $y[2] !== '' ? (int) $y[2] : null];
        }

        return ['litres' => $litres, 'fuel' => $fuel, 'code' => strtoupper($parts[1] ?? '') ?: null, 'year_from' => $years[0], 'year_to' => $years[1]];
    }

    public function line(): string
    {
        return trim((($this->litres !== null) ? number_format((float) $this->litres, 1).' ' : '').$this->fuel)
            .' | '.($this->code ?? '').($this->year_from ? ' | '.$this->year_from.'-'.($this->year_to ?: '') : '');
    }
}
