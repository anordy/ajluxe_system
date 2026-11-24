<?php

namespace Database\Seeders;

use App\Models\CarEngine;
use App\Models\CarModel;
use Illuminate\Database\Seeder;

/**
 * Starter engine list for the cars most common in Tanzania (make → model → engines).
 * Written for AJ LUXE AUTO; add or correct engines in Admin › Website › Car list, or import a full list by CSV.
 * Safe to run again: it only adds what is missing.
 */
class VehicleSeeder extends Seeder
{
    /** "size fuel | engine code"  (fuel letter: D diesel, H hybrid, none = petrol) */
    private const ENGINES = [
        'Toyota' => [
            'IST' => ['1.3 | 2NZ-FE', '1.5 | 1NZ-FE'],
            'Vitz' => ['1.0 | 1KR-FE', '1.3 | 2NZ-FE', '1.3 | 1NR-FE', '1.5 | 1NZ-FE'],
            'Passo' => ['1.0 | 1KR-FE', '1.3 | K3-VE'],
            'Spacio' => ['1.5 | 1NZ-FE', '1.8 | 1ZZ-FE'],
            'Probox' => ['1.3 | 2NZ-FE', '1.5 | 1NZ-FE', '1.4 D | 1ND-TV'],
            'Corolla' => ['1.5 | 1NZ-FE', '1.8 | 1ZZ-FE', '1.8 | 2ZR-FE'],
            'Premio' => ['1.5 | 1NZ-FE', '1.8 | 1ZZ-FE', '1.8 | 2ZR-FE', '2.0 | 1AZ-FSE', '2.0 | 3ZR-FAE'],
            'Allion' => ['1.5 | 1NZ-FE', '1.8 | 1ZZ-FE', '1.8 | 2ZR-FE', '2.0 | 1AZ-FSE', '2.0 | 3ZR-FAE'],
            'Mark X' => ['2.5 | 4GR-FSE', '3.0 | 3GR-FSE'],
            'Crown' => ['2.5 | 4GR-FSE', '3.0 | 3GR-FSE'],
            'Noah' => ['2.0 | 3S-FE', '2.0 | 1AZ-FSE', '2.0 | 3ZR-FAE'],
            'Voxy' => ['2.0 | 3S-FE', '2.0 | 1AZ-FSE', '2.0 | 3ZR-FAE'],
            'Alphard' => ['2.4 | 2AZ-FE', '3.0 | 1MZ-FE', '3.5 | 2GR-FE'],
            'Harrier' => ['2.0 | 3ZR-FAE', '2.4 | 2AZ-FE', '3.0 | 1MZ-FE', '3.5 | 2GR-FE'],
            'RAV4' => ['2.0 | 1AZ-FE', '2.4 | 2AZ-FE', '2.0 | 3ZR-FAE'],
            'Rush' => ['1.5 | 3SZ-VE', '1.5 | 2NR-VE'],
            'Fortuner' => ['2.7 | 2TR-FE', '2.4 D | 2GD-FTV', '2.8 D | 1GD-FTV', '3.0 D | 1KD-FTV'],
            'Hilux' => ['2.7 | 2TR-FE', '2.5 D | 2KD-FTV', '3.0 D | 1KD-FTV', '2.4 D | 2GD-FTV', '2.8 D | 1GD-FTV'],
            'Hiace' => ['2.7 | 2TR-FE', '2.5 D | 2KD-FTV', '3.0 D | 1KD-FTV', '3.0 D | 5L-E'],
            'Land Cruiser Prado' => ['2.7 | 2TR-FE', '4.0 | 1GR-FE', '3.0 D | 1KD-FTV', '2.8 D | 1GD-FTV'],
            'Land Cruiser' => ['4.0 | 1GR-FE', '4.7 | 2UZ-FE', '4.2 D | 1HZ', '4.2 D | 1HD-FTE', '4.5 D | 1VD-FTV'],
            'Coaster' => ['4.2 D | 1HZ', '4.0 D | N04C'],
        ],
        'Nissan' => [
            'March' => ['1.2 | CR12DE', '1.2 | HR12DE'],
            'Note' => ['1.2 | HR12DE', '1.5 | HR15DE'],
            'Tiida' => ['1.5 | HR15DE', '1.8 | MR18DE'],
            'Wingroad' => ['1.5 | HR15DE', '1.8 | MR18DE'],
            'X-Trail' => ['2.0 | QR20DE', '2.0 | MR20DD', '2.5 | QR25DE', '2.0 D | M9R'],
            'Dualis' => ['2.0 | MR20DE'],
            'Murano' => ['3.5 | VQ35DE'],
            'Navara' => ['2.5 D | YD25DDTi'],
            'Patrol' => ['4.8 | TB48DE', '3.0 D | ZD30DDTi', '4.2 D | TD42'],
            'Caravan' => ['2.0 | QR20DE', '3.0 D | ZD30DD'],
        ],
        'Honda' => [
            'Fit' => ['1.3 | L13A', '1.5 | L15A'],
            'Insight' => ['1.3 H | LDA'],
            'CR-V' => ['2.0 | R20A', '2.4 | K24A'],
            'Vezel' => ['1.5 | L15B', '1.5 H | LEB'],
            'Stream' => ['1.7 | D17A', '2.0 | K20A'],
        ],
        'Subaru' => [
            'Impreza' => ['1.5 | EL15', '2.0 | EJ20'],
            'Legacy' => ['2.0 | EJ20', '2.5 | EJ25'],
            'Forester' => ['2.0 | EJ20', '2.0 | FB20', '2.5 | EJ25'],
            'Outback' => ['2.5 | EJ25', '2.5 | FB25'],
        ],
        'Mitsubishi' => [
            'Colt' => ['1.3 | 4A90', '1.5 | 4A91'],
            'Outlander' => ['2.0 | 4B11', '2.4 | 4B12'],
            'RVR' => ['1.8 | 4B10'],
            'Pajero' => ['3.0 | 6G72', '3.5 | 6G74', '2.8 D | 4M40', '3.2 D | 4M41'],
            'Canter' => ['4.2 D | 4D33', '4.9 D | 4D34'],
        ],
        'Mazda' => [
            'Demio' => ['1.3 | ZJ-VE', '1.5 | ZY-VE'],
            'Axela' => ['1.5 | ZY-VE', '2.0 | LF-VE'],
            'Atenza' => ['2.0 | LF-VE', '2.3 | L3-VE'],
            'CX-5' => ['2.0 | PE-VPS', '2.5 | PY-VPS', '2.2 D | SH-VPTS'],
        ],
        'Suzuki' => [
            'Swift' => ['1.2 | K12B', '1.3 | M13A'],
            'Escudo' => ['2.0 | J20A', '2.4 | J24B'],
        ],
        'Isuzu' => [
            'D-Max' => ['2.5 D | 4JK1', '3.0 D | 4JJ1'],
            'Elf' => ['3.0 D | 4JJ1', '4.8 D | 4HG1'],
        ],
        'Mercedes-Benz' => [
            'C-Class' => ['1.8 | M271', '3.0 | M272'],
            'E-Class' => ['1.8 | M271', '3.5 | M272', '2.1 D | OM651'],
            'ML' => ['3.5 | M272', '3.0 D | OM642'],
        ],
        'BMW' => [
            '3 Series' => ['2.0 | N46B20', '2.5 | N52B25', '2.0 D | N47D20'],
            'X3' => ['2.5 | N52B25', '2.0 D | N47D20'],
            'X5' => ['3.0 | N52B30', '4.8 | N62B48', '3.0 D | N57D30'],
        ],
        'Volkswagen' => [
            'Golf' => ['1.4 | CAXA', '1.6 | BSE', '2.0 D | CBAA'],
            'Polo' => ['1.2 | CGPA', '1.4 | CGGB'],
            'Touareg' => ['3.6 | BHK', '3.0 D | CASA'],
        ],
        'Land Rover' => [
            'Discovery' => ['2.7 D | 276DT', '3.0 D | 306DT'],
            'Range Rover Sport' => ['3.0 D | 306DT', '5.0 | 508PS'],
            'Defender' => ['2.4 D | PUMA', '2.2 D | 224DT'],
        ],
    ];

    public function run(): void
    {
        foreach (self::ENGINES as $make => $models) {
            foreach ($models as $model => $engines) {
                $car = CarModel::firstOrCreate(['make' => $make, 'model' => $model], ['is_active' => true]);
                foreach ($engines as $line) {
                    $e = CarEngine::parseLine($line);
                    CarEngine::firstOrCreate(['car_model_id' => $car->id, 'code' => $e['code'], 'litres' => $e['litres'], 'fuel' => $e['fuel']], $e);
                }
            }
        }
    }
}
