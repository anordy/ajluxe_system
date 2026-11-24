<?php

namespace Database\Seeders;

use App\Models\CarModel;
use App\Models\SiteBanner;
use App\Support\ProductArt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Starter website content: banners and the car list. Safe to run again: it only fills empty tables
 * and adds missing cars. Edit everything afterwards in Admin › Website.
 */
class WebsiteSeeder extends Seeder
{
    private const CARS = [
        'Toyota' => ['IST', 'Vitz', 'Passo', 'Spacio', 'Probox', 'Corolla', 'Premio', 'Allion', 'Mark X', 'Crown', 'Noah', 'Voxy', 'Alphard',
            'Harrier', 'RAV4', 'Rush', 'Fortuner', 'Hilux', 'Hiace', 'Land Cruiser Prado', 'Land Cruiser', 'Coaster'],
        'Nissan' => ['March', 'Note', 'Tiida', 'Wingroad', 'X-Trail', 'Dualis', 'Murano', 'Navara', 'Patrol', 'Caravan'],
        'Honda' => ['Fit', 'Insight', 'CR-V', 'Vezel', 'Stream'],
        'Subaru' => ['Impreza', 'Legacy', 'Forester', 'Outback'],
        'Mitsubishi' => ['Colt', 'Outlander', 'RVR', 'Pajero', 'Canter'],
        'Mazda' => ['Demio', 'Axela', 'Atenza', 'CX-5'],
        'Suzuki' => ['Swift', 'Escudo', 'Carry'],
        'Isuzu' => ['D-Max', 'Elf'],
        'Mercedes-Benz' => ['C-Class', 'E-Class', 'ML'],
        'BMW' => ['3 Series', 'X3', 'X5'],
        'Volkswagen' => ['Golf', 'Polo', 'Touareg'],
        'Land Rover' => ['Discovery', 'Range Rover Sport', 'Defender'],
    ];

    public function run(): void
    {
        foreach (self::CARS as $make => $models) {
            foreach ($models as $model) {
                CarModel::firstOrCreate(['make' => $make, 'model' => $model], ['is_active' => true]);
            }
        }

        $this->call(VehicleSeeder::class); // engines for the car list

        if (SiteBanner::exists()) {
            return; // the shop owner has banners already
        }
        $banners = [
            ['tone' => 'red', 'kicker' => 'Tyres', 'title' => 'New tyres for every car', 'kicker_sw' => 'Matairi', 'title_sw' => 'Matairi mapya kwa kila gari', 'text_sw' => 'Saizi za magari madogo, SUV na all-terrain zipo. Tunayafunga na kuyafanyia balancing gereji.', 'button_label_sw' => 'Tafuta saizi yako',
                'text' => 'Passenger, SUV and all-terrain sizes in stock. We fit and balance them at the garage.', 'button_label' => 'Find your size',
                'link_url' => '/shop?category=Tyres', 'art' => ['tyre', ['tread' => 'at']]],
            ['tone' => 'ink', 'kicker' => 'Garage & paint', 'title' => 'Spray booth painting, panel by panel', 'kicker_sw' => 'Gereji na rangi', 'title_sw' => 'Kupaka rangi kwenye chumba maalum, sehemu kwa sehemu', 'text_sw' => 'Bampa, milango, boneti au gari zima. Leta fundi wako wa rangi au tumia wetu.', 'button_label_sw' => 'Weka miadi ya gari lako',
                'text' => 'Bumpers, doors, bonnets or the full car. Bring your own painter or use ours.', 'button_label' => 'Book your car in',
                'link_url' => '/garage#book', 'art' => ['paint', ['color' => '#E31B23']]],
            ['tone' => 'grey', 'kicker' => 'Lubricants', 'title' => 'The right oil for your engine', 'kicker_sw' => 'Vilainishi', 'title_sw' => 'Oili sahihi kwa injini yako', 'text_sw' => 'Oili za synthetic, semi-synthetic na dizeli pamoja na vichujio vinavyoendana.', 'button_label_sw' => 'Nunua vilainishi',
                'text' => 'Synthetic, semi-synthetic and diesel oils with filters to match.', 'button_label' => 'Shop lubricants',
                'link_url' => '/shop?category=Lubricants', 'art' => ['oil', ['label' => '5W-30', 'color' => '#E31B23']]],
            ['tone' => 'ink', 'kicker' => 'Order online', 'title' => 'Order online, collect today', 'kicker_sw' => 'Agiza mtandaoni', 'title_sw' => 'Agiza mtandaoni, chukua leo', 'text_sw' => 'Tunakuwekea vipuri vyako na kukupigia vikiwa tayari. Lipa kwa M-Pesa, benki au taslimu.', 'button_label_sw' => 'Anza kununua',
                'text' => 'We put your parts aside and call you when they are ready. Pay by Lipa Namba, bank or cash.', 'button_label' => 'Start shopping',
                'link_url' => '/shop', 'art' => ['phone_holder', []]],
        ];
        foreach ($banners as $i => $b) {
            $path = 'banners/starter-'.($i + 1).'.svg';
            Storage::disk('public')->put($path, ProductArt::make(...$b['art']));
            unset($b['art']);
            SiteBanner::create($b + ['image_path' => $path, 'sort_order' => $i + 1, 'is_active' => true]);
        }
    }
}
