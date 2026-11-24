<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Editable dropdown lists (vehicle makes, payment modes, ...). Managed in Admin -> Lists.
 */
class ListOption extends Model
{
    protected $fillable = ['list', 'value', 'sort_order'];

    /** list key => label shown on the Lists page */
    public const LISTS = [
        'vehicle_make'     => 'Vehicle Makes',
        'fuel_type'        => 'Fuel Types',
        'fuel_level'       => 'Fuel Levels',
        'service_category' => 'Service Categories',
        'part_category'    => 'Part Categories',
        'part_brand'       => 'Part Brands',
        'unit'             => 'Units',
        'payment_mode'     => 'Payment Modes',
        'customer_type'    => 'Customer Types',
        'priority'         => 'Job Priorities',
        'specialization'   => 'Mechanic Specializations',
    ];

    protected static function booted(): void
    {
        static::saved(fn ($o) => Cache::forget('list.'.$o->list));
        static::deleted(fn ($o) => Cache::forget('list.'.$o->list));
    }

    public static function valuesFor(string $list): array
    {
        return Cache::rememberForever('list.'.$list, fn () => static::where('list', $list)
            ->orderBy('sort_order')->orderBy('value')->pluck('value')->all());
    }
}
