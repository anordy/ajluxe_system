<?php

namespace Database\Seeders;

use App\Models\ListOption;
use App\Models\Part;
use App\Models\Service;
use App\Models\StockMovement;
use App\Support\Settings;
use App\Support\ProductArt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DEMO stock for trying the website — about 45 parts with pictures, stock, specs and some discounts.
 * Every demo part has a SKU starting with "DEMO-". Remove everything with:  php artisan demo:remove
 *
 *   php artisan db:seed --class=DemoShopSeeder
 */
class DemoShopSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GarageTariffSeeder::class);

        foreach (Part::DEPARTMENTS as $i => $dep) {
            if (! ListOption::where('list', 'part_category')->where('value', $dep)->exists()) {
                ListOption::create(['list' => 'part_category', 'value' => $dep, 'sort_order' => $i + 1]);
            }
        }

        // demo parts already in the database, by name (seeding again refreshes them)
        $seeded = Part::withTrashed()->where('sku', 'like', 'DEMO-%')->get()->keyBy('name');
        foreach (self::RENAMED as $old => $new) {
            if ($seeded->has($old) && ! $seeded->has($new)) {
                $seeded->put($new, $seeded->pull($old));
            }
        }
        $n = (int) $seeded->map(fn ($p) => (int) substr($p->sku, 5))->max();
        foreach ($this->parts() as $row) {
            [$cat, $sub, $brand, $name, $fits, $buy, $sell, $disc, $stock, $specs, $art, $artOpts] = $row + [11 => []];
            [$make, $model] = array_pad(explode('|', $fits), 2, null);
            if ($existing = $seeded->get($name)) {
                // seeded before: refresh the details, prices and picture, never the stock
                $existing->update(['name' => $name, 'unit' => $specs['Sold per'] ?? 'Pcs', 'purchase_price' => $buy, 'selling_price' => $sell,
                    'min_selling_price' => round($buy * 1.08, -2), 'discount_percent' => $disc, 'brand' => $brand, 'category' => $cat, 'subcategory' => $sub,
                    'vehicle_make' => $make ?: null, 'vehicle_model' => $model ?: null, 'specs' => array_diff_key($specs, ['Sold per' => 1])]);
                Storage::disk('public')->put($existing->image_path ?: 'demo-parts/'.Str::slug($existing->sku.'-'.$name).'.svg', ProductArt::make($art, $artOpts));

                continue;
            }

            $sku = 'DEMO-'.str_pad((string) ++$n, 3, '0', STR_PAD_LEFT);
            $path = 'demo-parts/'.Str::slug($sku.'-'.$name).'.svg';
            Storage::disk('public')->put($path, ProductArt::make($art, $artOpts));

            $part = Part::create([
                'sku' => $sku, 'name' => $name, 'brand' => $brand, 'category' => $cat, 'subcategory' => $sub,
                'unit' => $specs['Sold per'] ?? 'Pcs', 'vehicle_make' => $make ?: null, 'vehicle_model' => $model ?: null,
                'purchase_price' => $buy, 'selling_price' => $sell, 'min_selling_price' => round($buy * 1.08, -2),
                'discount_percent' => $disc, 'reorder_level' => 3, 'rack_location' => 'DEMO',
                'is_active' => true, 'show_online' => true, 'image_path' => $path,
                'specs' => array_diff_key($specs, ['Sold per' => 1]),
                'web_description' => 'Demo product for trying the website. Replace it with your real stock.',
            ]);
            if ($stock > 0) {
                StockMovement::record($part, 'opening', in: $stock, unitCost: $buy, note: 'Demo opening stock');
            }
        }

        // other pack sizes of some oils: shown as size buttons on the 4L product
        foreach (self::SIZES as [$mainName, $name, $capacity, $buy, $sell, $disc, $stock, $label]) {
            $main = Part::where('sku', 'like', 'DEMO-%')->where('name', $mainName)->first();
            if (! $main) {
                continue;
            }
            $specs = array_merge((array) $main->specs, ['Capacity' => $capacity]);
            $art = ['oil', ['label' => $main->specs['Viscosity'] ?? 'ATF', 'size' => $label, 'color' => self::OIL_COLOURS[$main->brand] ?? '#E31B23']];
            $names = [$name, ...array_keys(self::RENAMED, $name, true)];
            if ($existing = Part::withTrashed()->where('sku', 'like', 'DEMO-%')->whereIn('name', $names)->first()) {
                $existing->update(['name' => $name, 'brand' => $main->brand, 'specs' => $specs, 'variant_of' => $main->id,
                    'selling_price' => $sell, 'purchase_price' => $buy, 'discount_percent' => $disc]);
                Storage::disk('public')->put($existing->image_path ?: 'demo-parts/'.Str::slug($existing->sku.'-'.$name).'.svg', ProductArt::make(...$art));

                continue;
            }
            $sku = 'DEMO-'.str_pad((string) ++$n, 3, '0', STR_PAD_LEFT);
            $path = 'demo-parts/'.Str::slug($sku.'-'.$name).'.svg';
            Storage::disk('public')->put($path, ProductArt::make(...$art));
            $part = Part::create([
                'sku' => $sku, 'name' => $name, 'brand' => $main->brand, 'category' => $main->category, 'subcategory' => $main->subcategory,
                'unit' => 'Pcs', 'purchase_price' => $buy, 'selling_price' => $sell, 'min_selling_price' => round($buy * 1.08, -2),
                'discount_percent' => $disc, 'reorder_level' => 3, 'rack_location' => 'DEMO', 'is_active' => true, 'show_online' => true,
                'image_path' => $path, 'specs' => $specs, 'variant_of' => $main->id,
                'web_description' => 'Demo product for trying the website. Replace it with your real stock.',
            ]);
            if ($stock > 0) {
                StockMovement::record($part, 'opening', in: $stock, unitCost: $buy, note: 'Demo opening stock');
            }
        }

        // demo "from" prices for the starter garage services that have no price yet (reset by demo:remove)
        $demoRates = [
            'General Service (Small Car)' => 60000, 'General Service (SUV)' => 90000, 'Engine Oil & Filter Change' => 20000,
            'Engine Diagnostic Scan' => 30000, 'Brake Pad Replacement' => 25000, 'Wheel Alignment' => 35000,
            'Wheel Balancing (per wheel)' => 5000, 'AC Gas Top-up & Service' => 60000, 'Auto Electrical Repair' => 30000,
            'Full Wash & Interior Detailing' => 25000, 'Brake Disc Skimming' => 20000, 'Clutch Overhaul' => 150000,
        ];
        $changed = [];
        foreach ($demoRates as $name => $rate) {
            $s = Service::where('name', $name)->where('rate', 0)->first();
            if ($s) {
                $s->update(['rate' => $s->isHourly() ? round($rate / max(1, (float) $s->est_hours), -3) : $rate]);
                $changed[] = $s->id;
            }
        }
        if ($changed) {
            $old = json_decode((string) setting('demo_service_ids', '[]'), true) ?: [];
            Settings::set(['demo_service_ids' => json_encode(array_values(array_unique(array_merge($old, $changed))))]);
        }

        $this->command?->info("Demo stock ready: {$n} parts. Remove it any time with: php artisan demo:remove");
    }

    /** [main product, name, capacity, buy, sell, website discount %, stock, label on the picture] */
    private const SIZES = [
        ['Quartz 9000 5W-40 (4L)', 'Quartz 9000 5W-40 (1L)', '1 L', 20000, 28000, 10, 30, '1L'],
        ['Quartz 9000 5W-40 (4L)', 'Quartz 9000 5W-40 (5L)', '5 L', 88000, 118000, 15, 12, '5L'],
        ['8100 X-clean 5W-30 (4L)', '8100 X-clean 5W-30 (1L)', '1 L', 21000, 30000, 0, 24, '1L'],
        ['8100 X-clean 5W-30 (4L)', '8100 X-clean 5W-30 (5L)', '5 L', 92000, 125000, 0, 8, '5L'],
        ['Super Leichtlauf 10W-40 (4L)', 'Super Leichtlauf 10W-40 (1L)', '1 L', 16000, 22000, 0, 30, '1L'],
        ['Magnatec 10W-40 (4L)', 'Magnatec 10W-40 (1L)', '1 L', 16000, 23000, 0, 40, '1L'],
        ['Genuine Motor Oil 0W-20 (4L)', 'Genuine Motor Oil 0W-20 (1L)', '1 L', 19000, 27000, 0, 20, '1L'],
    ];

    private const OIL_COLOURS = ['Total' => '#E31B23', 'Motul' => '#B91C1C', 'Castrol' => '#15803D', 'Toyota Genuine' => '#374151', 'Liqui Moly' => '#1E3A8A'];

    /** Names used by the first demo set, so seeding again updates those parts instead of adding copies. */
    private const RENAMED = [
        // second demo set: oils moved to the shop's brands
        'Super 3000 X1 5W-30 (4L)' => '8100 X-clean 5W-30 (4L)',
        'Super 3000 X1 5W-30 (1L)' => '8100 X-clean 5W-30 (1L)',
        'Super 3000 X1 5W-30 (5L)' => '8100 X-clean 5W-30 (5L)',
        'Helix HX7 10W-40 (4L)' => 'Super Leichtlauf 10W-40 (4L)',
        'GTX 20W-50 (5L)' => 'Engine Oil 20W-50 (5L)',
        'Rubia TIR 7400 15W-40 (5L)' => 'Diesel Engine Oil 15W-40 (5L)',
        'Front Brake Pads Set' => 'Front Brake Pads Set P83 152',
        'Rear Brake Pads Set' => 'Rear Brake Pads Set GDB3544',
        'Front Brake Discs (Pair)' => 'Front Brake Discs Vented (Pair)',
        'Front Shock Absorber Excel-G' => 'Front Shock Absorber Excel-G 339193',
        'Rear Shock Absorber' => 'Rear Shock Absorber OESpectrum G8098',
        'Iridium Spark Plugs (Set of 4)' => 'Laser Iridium Spark Plug IFR6T11',
        'Spark Plugs (Set of 4)' => 'Iridium Power Spark Plug IK20',
        'Car Battery 100Ah N100' => 'Car Battery 100Ah N100 S4',
        'Aerotwin Wiper Blade 22"' => 'Aerotwin Wiper Blade 550 mm',
        'Wiper Blades Pair 24" + 16"' => 'Silencio Wiper Blade Set 600 + 400 mm',
        'H4 Headlight Bulb X-tremeVision' => 'H4 Headlight Bulb X-tremeVision Pro150',
        'H7 Headlight Bulb Night Breaker' => 'H7 Headlight Bulb Night Breaker 200',
        'Drive Belt 7PK1530' => 'Micro-V Drive Belt 7PK1530',
        'SP Touring 195/65 R15' => 'SP Touring R1 195/65 R15',
        'Kinergy Eco 185/65 R14' => 'Kinergy Eco 2 185/65 R14',
        'Rubia TIR 15W-40 Diesel (5L)' => 'Rubia TIR 7400 15W-40 (5L)',
        'Dash Cam 1080p' => 'Dash Cam A400 1440p',
        'Jump Starter 1000A' => 'Boost Sport Jump Starter GB20',
    ];

    /**
     * [department, type, brand, name, "Make|Model", buy, sell, website discount %, stock, specs, picture, picture options]
     * "Fitting position" uses: Front axle, Rear axle, Front left, Front right, Rear left, Rear right, Left, Right.
     */
    private function parts(): array
    {
        return [
            // ------------------------------------------------------------ Car parts
            ['Car Parts', 'Brake Pads', 'Brembo', 'Front Brake Pads Set P83 152', 'Toyota|Land Cruiser Prado 150', 62000, 95000, 15, 12, [
                'Fitting position' => 'Front axle', 'Brake system' => 'Advics', 'Width' => '127.5 mm', 'Height' => '58.5 mm', 'Thickness' => '17.5 mm',
                'Wear warning contact' => 'Acoustic wear warning', 'Pieces' => '4 (one axle)', 'Part number' => 'P 83 152', 'Sold per' => 'Set'], 'brake_pads'],
            ['Car Parts', 'Brake Pads', 'TRW', 'Rear Brake Pads Set GDB3544', 'Toyota|Hilux Vigo', 38000, 60000, 0, 9, [
                'Fitting position' => 'Rear axle', 'Width' => '94.4 mm', 'Height' => '43.6 mm', 'Thickness' => '15 mm',
                'Wear warning contact' => 'Not prepared for wear indicator', 'Pieces' => '4 (one axle)', 'Part number' => 'GDB3544', 'Sold per' => 'Set'], 'brake_pads'],
            ['Car Parts', 'Brake Discs', 'Brembo', 'Front Brake Discs Vented (Pair)', 'Toyota|Hilux Revo', 140000, 210000, 10, 4, [
                'Fitting position' => 'Front axle', 'Brake disc type' => 'Internally vented', 'Diameter' => '319 mm', 'Thickness' => '28 mm',
                'Minimum thickness' => '26 mm', 'Number of holes' => '6', 'Centre bore' => '106 mm', 'Surface' => 'Coated', 'Part number' => '09.D560.11', 'Sold per' => 'Pair'], 'brake_disc'],
            ['Car Parts', 'Shock Absorbers', 'KYB', 'Front Shock Absorber Excel-G 339193', 'Toyota|RAV4', 85000, 130000, 20, 6, [
                'Fitting position' => 'Front left', 'Shock absorber type' => 'Gas pressure', 'Shock absorber design' => 'Suspension strut', 'Length' => '520 mm',
                'Upper fastening' => 'Threaded pin', 'Lower fastening' => 'Bracket', 'Part number' => '339193', 'Sold per' => 'Pcs'], 'shock', ['color' => '#E31B23']],
            ['Car Parts', 'Shock Absorbers', 'KYB', 'Front Shock Absorber Excel-G 339192', 'Toyota|RAV4', 85000, 130000, 20, 5, [
                'Fitting position' => 'Front right', 'Shock absorber type' => 'Gas pressure', 'Shock absorber design' => 'Suspension strut', 'Length' => '520 mm',
                'Upper fastening' => 'Threaded pin', 'Lower fastening' => 'Bracket', 'Part number' => '339192', 'Sold per' => 'Pcs'], 'shock', ['color' => '#E31B23']],
            ['Car Parts', 'Shock Absorbers', 'Monroe', 'Rear Shock Absorber OESpectrum G8098', 'Nissan|X-Trail', 70000, 110000, 0, 5, [
                'Fitting position' => 'Rear axle', 'Shock absorber type' => 'Gas pressure', 'Shock absorber design' => 'Telescopic shock absorber',
                'Length' => '395 mm', 'Stroke' => '150 mm', 'Upper fastening' => 'Eye', 'Lower fastening' => 'Eye', 'Part number' => 'G8098', 'Sold per' => 'Pcs'], 'shock', ['color' => '#1D4ED8']],
            ['Car Parts', 'Spark Plugs', 'NGK', 'Laser Iridium Spark Plug IFR6T11', 'Toyota|IST / Vitz / Probox', 12000, 18000, 10, 40, [
                'Product line' => 'Laser Iridium', 'Spark plug' => 'Iridium centre electrode', 'Electrode gap' => '1.1 mm', 'Spanner size' => '16 mm',
                'Thread size' => 'M 14 × 1.25', 'Thread length' => '26.5 mm', 'Heat range' => '6', 'Number of earth electrodes' => '1', 'Part number' => 'IFR6T11', 'Sold per' => 'Pcs'], 'spark_plug'],
            ['Car Parts', 'Spark Plugs', 'Denso', 'Iridium Power Spark Plug IK20', 'Nissan|Note / Tiida', 10000, 15000, 0, 30, [
                'Product line' => 'Iridium Power', 'Spark plug' => 'Iridium centre electrode', 'Electrode gap' => '1.1 mm', 'Spanner size' => '16 mm',
                'Thread size' => 'M 14 × 1.25', 'Thread length' => '19 mm', 'Heat range' => '20', 'Part number' => 'IK20', 'Sold per' => 'Pcs'], 'spark_plug'],
            ['Car Parts', 'Spark Plugs', 'Bosch', 'Super Plus Spark Plug FR7DC+', '', 5000, 8500, 15, 50, [
                'Product line' => 'Super Plus', 'Spark plug' => 'Nickel centre electrode', 'Electrode gap' => '0.8 mm', 'Spanner size' => '16 mm',
                'Thread size' => 'M 14 × 1.25', 'Thread length' => '19 mm', 'Heat range' => '7', 'Part number' => '0 242 235 666', 'Sold per' => 'Pcs'], 'spark_plug'],
            ['Car Parts', 'Batteries', 'Chloride Exide', 'Car Battery 70Ah N70', '', 180000, 245000, 0, 8, [
                'Battery capacity' => '70 Ah', 'Voltage' => '12 V', 'Cold cranking current' => '580 A', 'Terminal layout' => 'Right +',
                'Length' => '306 mm', 'Width' => '173 mm', 'Height' => '225 mm', 'Battery type' => 'Lead-acid', 'Warranty' => '12 months'], 'battery', ['color' => '#E31B23', 'label' => 'N70']],
            ['Car Parts', 'Batteries', 'Bosch', 'Car Battery 100Ah N100 S4', '', 270000, 360000, 8, 4, [
                'Battery capacity' => '100 Ah', 'Voltage' => '12 V', 'Cold cranking current' => '830 A', 'Terminal layout' => 'Left +',
                'Length' => '410 mm', 'Width' => '176 mm', 'Height' => '233 mm', 'Battery type' => 'Maintenance free', 'Warranty' => '18 months'], 'battery', ['color' => '#1D4ED8', 'label' => 'N100']],
            ['Car Parts', 'Wiper Blades', 'Bosch', 'Aerotwin Wiper Blade 550 mm', '', 14000, 25000, 0, 30, [
                'Fitting position' => 'Front axle', 'Wiper blade type' => 'Flat wiper blade', 'Length' => '550 mm (22")', 'Adapter' => 'Hook arm',
                'Driver / passenger side' => 'Driver side', 'Part number' => '3 397 008 538'], 'wiper'],
            ['Car Parts', 'Wiper Blades', 'Valeo', 'Silencio Wiper Blade Set 600 + 400 mm', '', 20000, 35000, 15, 18, [
                'Fitting position' => 'Front axle', 'Wiper blade type' => 'Flat wiper blade', 'Length' => '600 mm + 400 mm', 'Adapter' => 'Hook arm',
                'Pieces' => '2', 'Part number' => 'VM449', 'Sold per' => 'Pair'], 'wiper'],
            ['Car Parts', 'Bulbs', 'Philips', 'H4 Headlight Bulb X-tremeVision Pro150', '', 18000, 30000, 0, 25, [
                'Fitting position' => 'Front axle', 'Bulb type' => 'H4', 'Voltage' => '12 V', 'Rated power' => '60/55 W', 'Socket type' => 'P43t',
                'Light colour' => 'Warm white', 'Pieces' => '2', 'Part number' => '12342XVPS2', 'Sold per' => 'Set'], 'bulb', ['label' => 'H4']],
            ['Car Parts', 'Bulbs', 'Osram', 'H7 Headlight Bulb Night Breaker 200', '', 16000, 28000, 10, 22, [
                'Fitting position' => 'Front axle', 'Bulb type' => 'H7', 'Voltage' => '12 V', 'Rated power' => '55 W', 'Socket type' => 'PX26d',
                'Light colour' => 'White', 'Pieces' => '2', 'Part number' => '64210NB200-HCB', 'Sold per' => 'Set'], 'bulb', ['label' => 'H7']],
            ['Car Parts', 'Headlights', 'Depo', 'Headlight Left (driver side)', 'Toyota|Hilux Revo', 230000, 320000, 0, 2, [
                'Fitting position' => 'Left', 'Bulb type' => 'H4', 'Adjustment' => 'Electric', 'Light function' => 'Low / high beam', 'For right-hand traffic' => 'No', 'Part number' => '212-11X5L'], 'bulb', ['label' => 'L']],
            ['Car Parts', 'Headlights', 'Depo', 'Headlight Right (passenger side)', 'Toyota|Hilux Revo', 230000, 320000, 0, 2, [
                'Fitting position' => 'Right', 'Bulb type' => 'H4', 'Adjustment' => 'Electric', 'Light function' => 'Low / high beam', 'For right-hand traffic' => 'No', 'Part number' => '212-11X5R'], 'bulb', ['label' => 'R']],
            ['Car Parts', 'Belts', 'Gates', 'Micro-V Drive Belt 7PK1530', 'Toyota|Hiace / Hilux 2KD', 24000, 40000, 0, 10, [
                'Belt type' => 'V-ribbed belt', 'Number of ribs' => '7', 'Length' => '1530 mm', 'Width' => '24.9 mm', 'Engine code' => '2KD-FTV', 'Part number' => '7PK1530'], 'belt'],
            ['Car Parts', 'Coolant', 'Toyota', 'Super Long Life Coolant 4L', 'Toyota|All models', 26000, 38000, 0, 16, [
                'Colour' => 'Pink', 'Capacity' => '4 L', 'Mixing' => 'Ready to use (50/50)', 'Antifreeze protection' => '−34 °C',
                'Coolant type' => 'Long-life, silicate free', 'Part number' => '08889-80070', 'Sold per' => 'Pcs'], 'coolant', ['color' => '#EC4899', 'label' => 'COOLANT']],

            // ------------------------------------------------------------ Tyres
            ['Tyres', 'All-terrain', 'Bridgestone', 'Dueler A/T 001 265/65 R17', 'Toyota|Prado / Hilux / Fortuner', 380000, 480000, 10, 16, [
                'Size' => '265/65 R17', 'Width' => '265 mm', 'Profile' => '65', 'Rim diameter' => '17"', 'Load index' => '112 (1120 kg)',
                'Speed rating' => 'S (180 km/h)', 'Season' => 'All season', 'Tyre type' => 'All-terrain', 'Tubeless' => 'Yes'], 'tyre', ['label' => '265/65 R17']],
            ['Tyres', 'All-terrain', 'BFGoodrich', 'All-Terrain T/A KO2 265/70 R16', 'Toyota|Land Cruiser / Hilux', 420000, 540000, 0, 8, [
                'Size' => '265/70 R16', 'Width' => '265 mm', 'Profile' => '70', 'Rim diameter' => '16"', 'Load index' => '121/118 (1450 kg)',
                'Speed rating' => 'S (180 km/h)', 'Season' => 'All season', 'Tyre type' => 'All-terrain', 'Reinforced' => 'Yes (LT)'], 'tyre', ['label' => '265/70 R16']],
            ['Tyres', 'SUV', 'Yokohama', 'Geolandar G055 225/65 R17', 'Toyota|RAV4 / Harrier', 260000, 330000, 12, 12, [
                'Size' => '225/65 R17', 'Width' => '225 mm', 'Profile' => '65', 'Rim diameter' => '17"', 'Load index' => '102 (850 kg)',
                'Speed rating' => 'H (210 km/h)', 'Season' => 'Summer', 'Tyre type' => 'Highway SUV'], 'tyre', ['label' => '225/65 R17', 'tread' => 'road']],
            ['Tyres', 'Passenger', 'Michelin', 'Primacy 4 205/55 R16', 'Toyota|Corolla / Mark X', 230000, 290000, 0, 10, [
                'Size' => '205/55 R16', 'Season' => 'Summer', 'Width' => '205 mm', 'Profile' => '55', 'Rim diameter' => '16"', 'Load index' => '91 (615 kg)',
                'Speed rating' => 'V (240 km/h)', 'Tyre type' => 'Passenger', 'Wet grip' => 'A'], 'tyre', ['label' => '205/55 R16', 'tread' => 'road']],
            ['Tyres', 'Passenger', 'Hankook', 'Ventus Prime 4 205/55 R16', 'Toyota|Corolla / Wish', 175000, 225000, 10, 14, [
                'Size' => '205/55 R16', 'Season' => 'Summer', 'Width' => '205 mm', 'Profile' => '55', 'Rim diameter' => '16"', 'Load index' => '91 (615 kg)',
                'Speed rating' => 'V (240 km/h)', 'Tyre type' => 'Passenger', 'Wet grip' => 'A', 'Fuel efficiency' => 'C', 'Noise' => '71 dB'], 'tyre', ['label' => '205/55 R16', 'tread' => 'road']],
            ['Tyres', 'All-terrain', 'Dunlop', 'Grandtrek AT5 265/65 R17', 'Toyota|Prado / Hilux / Fortuner', 330000, 420000, 15, 10, [
                'Size' => '265/65 R17', 'Season' => 'All season', 'Width' => '265 mm', 'Profile' => '65', 'Rim diameter' => '17"', 'Load index' => '112 (1120 kg)',
                'Speed rating' => 'S (180 km/h)', 'Tyre type' => 'All-terrain', 'Tubeless' => 'Yes'], 'tyre', ['label' => '265/65 R17']],
            ['Tyres', 'Passenger', 'Dunlop', 'SP Touring R1 195/65 R15', 'Toyota|Premio / Allion', 140000, 185000, 10, 20, [
                'Size' => '195/65 R15', 'Season' => 'Summer', 'Width' => '195 mm', 'Profile' => '65', 'Rim diameter' => '15"', 'Load index' => '91 (615 kg)',
                'Speed rating' => 'H (210 km/h)', 'Tyre type' => 'Passenger'], 'tyre', ['label' => '195/65 R15', 'tread' => 'road']],
            ['Tyres', 'Passenger', 'Hankook', 'Kinergy Eco 2 185/65 R14', 'Toyota|IST / Spacio', 105000, 140000, 0, 24, [
                'Size' => '185/65 R14', 'Season' => 'Summer', 'Width' => '185 mm', 'Profile' => '65', 'Rim diameter' => '14"', 'Load index' => '86 (530 kg)',
                'Speed rating' => 'H (210 km/h)', 'Tyre type' => 'Passenger', 'Fuel efficiency' => 'B'], 'tyre', ['label' => '185/65 R14', 'tread' => 'road']],
            ['Tyres', 'Passenger', 'Triangle', 'TR928 175/70 R13', 'Toyota|Vitz / Passo', 75000, 98000, 15, 28, [
                'Size' => '175/70 R13', 'Season' => 'Summer', 'Width' => '175 mm', 'Profile' => '70', 'Rim diameter' => '13"', 'Load index' => '82 (475 kg)',
                'Speed rating' => 'T (190 km/h)', 'Tyre type' => 'Passenger'], 'tyre', ['label' => '175/70 R13', 'tread' => 'road']],
            ['Tyres', 'Light truck', 'Goodyear', 'Wrangler 215/70 R15C', 'Toyota|Hiace', 190000, 245000, 0, 0, [
                'Size' => '215/70 R15C', 'Season' => 'All season', 'Width' => '215 mm', 'Profile' => '70', 'Rim diameter' => '15"', 'Load index' => '109/107 (1030 kg)',
                'Speed rating' => 'S (180 km/h)', 'Tyre type' => 'Commercial (C)', 'Reinforced' => 'Yes'], 'tyre', ['label' => '215/70 R15C']],

            // ------------------------------------------------------------ Engine oil
            ['Lubricants', 'Synthetic', 'Total', 'Quartz 9000 5W-40 (4L)', '', 72000, 98000, 18, 24, [
                'Viscosity' => '5W-40', 'Capacity' => '4 L', 'Oil type' => 'Fully synthetic', 'API' => 'SN / CF', 'ACEA' => 'A3/B4',
                'Manufacturer approvals' => 'MB 229.5, VW 502 00 / 505 00', 'Engine' => 'Petrol & diesel', 'Sold per' => 'Pcs'], 'oil', ['color' => '#E31B23', 'label' => '5W-40']],
            ['Lubricants', 'Synthetic', 'Motul', '8100 X-clean 5W-30 (4L)', '', 76000, 105000, 0, 18, [
                'Viscosity' => '5W-30', 'Capacity' => '4 L', 'Oil type' => 'Fully synthetic', 'API' => 'SP', 'ACEA' => 'C3',
                'Manufacturer approvals' => 'VW 504 00 / 507 00, MB 229.51, BMW LL-04', 'Engine' => 'Petrol & diesel', 'Sold per' => 'Pcs'], 'oil', ['color' => '#B91C1C', 'label' => '5W-30']],
            ['Lubricants', 'Synthetic', 'Toyota Genuine', 'Genuine Motor Oil 0W-20 (4L)', 'Toyota|Hybrid & petrol', 70000, 95000, 10, 14, [
                'Viscosity' => '0W-20', 'Capacity' => '4 L', 'Oil type' => 'Fully synthetic', 'API' => 'SP', 'ILSAC' => 'GF-6A',
                'Engine' => 'Petrol & hybrid', 'Part number' => '08880-13205', 'Sold per' => 'Pcs'], 'oil', ['color' => '#374151', 'label' => '0W-20']],
            ['Lubricants', 'Semi-synthetic', 'Castrol', 'Magnatec 10W-40 (4L)', '', 58000, 80000, 0, 30, [
                'Viscosity' => '10W-40', 'Capacity' => '4 L', 'Oil type' => 'Semi-synthetic', 'API' => 'SN / CF', 'ACEA' => 'A3/B4',
                'Manufacturer approvals' => 'MB 229.3, VW 502 00 / 505 00', 'Engine' => 'Petrol & diesel', 'Sold per' => 'Pcs'], 'oil', ['color' => '#15803D', 'label' => '10W-40']],
            ['Lubricants', 'Semi-synthetic', 'Liqui Moly', 'Super Leichtlauf 10W-40 (4L)', '', 56000, 78000, 12, 26, [
                'Viscosity' => '10W-40', 'Capacity' => '4 L', 'Oil type' => 'Semi-synthetic', 'API' => 'SN', 'ACEA' => 'A3/B3, A3/B4',
                'Manufacturer approvals' => 'MB 229.3, Renault RN0700', 'Engine' => 'Petrol & diesel', 'Sold per' => 'Pcs'], 'oil', ['color' => '#1E3A8A', 'label' => '10W-40']],
            ['Lubricants', 'Mineral', 'Puma', 'Engine Oil 20W-50 (5L)', '', 52000, 72000, 0, 22, [
                'Viscosity' => '20W-50', 'Capacity' => '5 L', 'Oil type' => 'Mineral', 'API' => 'SL / CF', 'ACEA' => 'A3/B3',
                'Engine' => 'Older petrol engines, high mileage', 'Sold per' => 'Pcs'], 'oil', ['color' => '#B91C1C', 'label' => '20W-50', 'size' => '5L']],
            ['Lubricants', 'Diesel', 'Atlantic Oil', 'Diesel Engine Oil 15W-40 (5L)', '', 55000, 76000, 10, 20, [
                'Viscosity' => '15W-40', 'Capacity' => '5 L', 'Oil type' => 'Mineral', 'API' => 'CI-4 / SL', 'ACEA' => 'E7',
                'Manufacturer approvals' => 'Volvo VDS-3, Mack EO-N', 'Engine' => 'Diesel (trucks, pick-ups)', 'Sold per' => 'Pcs'], 'oil', ['color' => '#0F766E', 'label' => '15W-40', 'size' => '5L']],
            ['Lubricants', 'Transmission', 'Castrol', 'Transmax ATF Dexron III (1L)', '', 14000, 22000, 0, 40, [
                'Oil type' => 'Automatic transmission fluid', 'Capacity' => '1 L', 'Specification' => 'Dexron III H, Mercon',
                'Colour' => 'Red', 'Use' => 'Automatic gearboxes, power steering', 'Sold per' => 'Pcs'], 'oil', ['color' => '#7C2D12', 'label' => 'ATF', 'size' => '1L']],
            ['Lubricants', 'Transmission', 'Toyota Genuine', 'CVT Fluid TC (4L)', 'Toyota|Corolla / Wish / Premio (CVT)', 62000, 88000, 0, 12, [
                'Oil type' => 'CVT fluid', 'Capacity' => '4 L', 'Specification' => 'Toyota CVT Fluid TC', 'Use' => 'Toyota CVT gearboxes',
                'Part number' => '08886-02105', 'Sold per' => 'Pcs'], 'oil', ['color' => '#374151', 'label' => 'CVT TC']],
            ['Lubricants', 'Transmission', 'Toyota Genuine', 'ATF Type T-IV (4L)', 'Toyota|Hilux / Prado / Hiace (automatic)', 58000, 82000, 5, 16, [
                'Oil type' => 'Automatic transmission fluid', 'Capacity' => '4 L', 'Specification' => 'Toyota ATF Type T-IV', 'Colour' => 'Red',
                'Use' => 'Toyota automatic gearboxes', 'Part number' => '08886-81015', 'Sold per' => 'Pcs'], 'oil', ['color' => '#374151', 'label' => 'T-IV']],

            // ------------------------------------------------------------ Filters
            ['Filters', 'Oil Filter', 'Toyota', 'Oil Filter 90915-YZZE1', 'Toyota|Corolla / RAV4 / Noah', 6500, 12000, 0, 60, [
                'Filter type' => 'Spin-on filter', 'Height' => '84 mm', 'Outer diameter' => '76 mm', 'Thread size' => '3/4"-16 UNF',
                'With non-return valve' => 'Yes', 'Part number' => '90915-YZZE1'], 'oil_filter', ['color' => '#E31B23']],
            ['Filters', 'Oil Filter', 'Bosch', 'Oil Filter P3311', 'Nissan|X-Trail / Note', 7000, 13000, 10, 35, [
                'Filter type' => 'Spin-on filter', 'Height' => '76 mm', 'Outer diameter' => '66 mm', 'Thread size' => 'M 20 × 1.5',
                'With non-return valve' => 'Yes', 'Part number' => '0 451 103 311'], 'oil_filter', ['color' => '#1D4ED8']],
            ['Filters', 'Air Filter', 'MANN-FILTER', 'Air Filter C 26 003', 'Toyota|Land Cruiser Prado 150', 22000, 38000, 15, 14, [
                'Filter type' => 'Filter insert', 'Length' => '307 mm', 'Width' => '220 mm', 'Height' => '60 mm', 'Shape' => 'Rectangular',
                'Part number' => 'C 26 003'], 'air_filter'],
            ['Filters', 'Air Filter', 'Sakura', 'Air Filter A-1153', 'Toyota|IST / Vitz', 9000, 18000, 0, 25, [
                'Filter type' => 'Filter insert', 'Length' => '236 mm', 'Width' => '166 mm', 'Height' => '49 mm', 'Shape' => 'Rectangular',
                'Part number' => 'A-1153'], 'air_filter', ['frame' => '#E31B23']],
            ['Filters', 'Cabin Filter', 'Denso', 'Cabin Air Filter DCC-1009', 'Toyota|Most models 2005+', 12000, 22000, 0, 30, [
                'Filter type' => 'Activated carbon filter', 'Length' => '215 mm', 'Width' => '197 mm', 'Height' => '18 mm',
                'Removes' => 'Dust, pollen, odours', 'Part number' => 'DCC-1009'], 'cabin_filter'],
            ['Filters', 'Fuel Filter', 'Toyota', 'Fuel Filter 23390-0L041', 'Toyota|Hilux / Fortuner diesel', 28000, 45000, 10, 10, [
                'Filter type' => 'Filter insert', 'Fuel type' => 'Diesel', 'Height' => '111 mm', 'Outer diameter' => '75 mm',
                'With water separator' => 'Yes', 'Part number' => '23390-0L041'], 'fuel_filter', ['color' => '#1F2937']],

            // ------------------------------------------------------------ Car accessories
            ['Car Accessories', 'Floor Mats', 'AJ Select', 'Rubber Floor Mats (4 pieces)', '', 28000, 45000, 20, 15, [
                'Material' => 'Heavy rubber', 'Colour' => 'Black', 'Pieces' => '4', 'Fit' => 'Universal, trim to size',
                'Anti-slip back' => 'Yes', 'Washable' => 'Yes', 'Sold per' => 'Set'], 'mats'],
            ['Car Accessories', 'Seat Covers', 'AJ Select', 'Leather-look Seat Covers (5 seats)', '', 95000, 150000, 15, 6, [
                'Material' => 'PU leather', 'Colour' => 'Black / red', 'Seats' => '5 (front + rear bench)', 'Airbag compatible' => 'Yes, side seams open',
                'Fitting' => 'Elastic straps and hooks', 'Sold per' => 'Set'], 'seat_cover', ['color' => '#E31B23']],
            ['Car Accessories', 'Phone Holders', 'Baseus', 'Dashboard Phone Holder', '', 15000, 28000, 0, 20, [
                'Mount' => 'Dashboard or windscreen (suction)', 'Phone size' => '4.7" – 6.8"', 'Rotation' => '360°', 'Arm' => 'Telescopic'], 'phone_holder'],
            ['Car Accessories', 'Tools', 'AJ Select', 'Hydraulic Floor Jack 2 Ton', '', 85000, 130000, 10, 5, [
                'Lifting capacity' => '2 t', 'Minimum height' => '135 mm', 'Maximum height' => '330 mm', 'Weight' => '8.5 kg', 'Wheels' => '4 steel castors'], 'jack', ['color' => '#E31B23']],
            ['Car Accessories', 'Electronics', '70mai', 'Dash Cam A400 1440p', '', 95000, 145000, 0, 7, [
                'Resolution' => '1440p Quad HD', 'Screen' => '2"', 'Viewing angle' => '145°', 'Night vision' => 'Yes', 'Storage' => 'microSD up to 128 GB',
                'Parking monitor' => 'Yes (with hardwire kit)'], 'dashcam'],
            ['Car Accessories', 'Electronics', 'NOCO', 'Boost Sport Jump Starter GB20', '', 160000, 230000, 12, 4, [
                'Peak current' => '500 A', 'Engines' => 'Petrol up to 4 L', 'Battery' => 'Lithium-ion', 'USB charging' => 'Yes',
                'Built-in torch' => 'Yes', 'Protection' => 'Spark-proof, reverse polarity'], 'jump_starter', ['color' => '#CA8A04', 'label' => '500A']],
        ];
    }
}
