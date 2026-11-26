<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\KitongaSupplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gas shock absorbers from Kitonga Spareparts' price list (rows 191–269).
 *
 * "Buy from supplier when sold" parts: on the website as normal stock, no stock and no
 * stock value here, bought from Kitonga only when they sell. Ours = Kitonga's selling
 * price + 15 %. All share one shock absorber photo (a picture already on a part is kept). Safe to run again (updates, no copies).
 *
 *   php artisan db:seed --class=KitongaShockAbsorberSeeder --force
 *
 * MBELE = front, NYUMA = rear. Most numbers come in pairs (left and right side).
 * Row 190 (339023, no car written, 55,000) is left out: 339023 is also row 234 (Camry, 35,000).
 */
class KitongaShockAbsorberSeeder extends Seeder
{
    use KitongaSupplier;

    /** [list row, part number, cars as on the list (cleaned), position, [[make, model], …] for the car finder, Kitonga buying, Kitonga selling] */
    private const ROWS = [
        [191, '332061', 'Nissan March', 'Front', [['Nissan', 'March']], 35000, 35000],
        [192, '332062', 'Nissan March', 'Front', [['Nissan', 'March']], 35000, 35000],
        [193, '333107', 'Toyota Carina old (1997)', 'Rear', [], 50000, 50000],
        [194, '333108', 'Toyota Carina old (1997)', 'Rear', [], 50000, 50000],
        [195, '333114', 'Toyota Corolla', 'Front', [['Toyota', 'Corolla']], 40000, 40000],
        [196, '333115', 'Toyota Corolla', 'Front', [['Toyota', 'Corolla']], 40000, 40000],
        [197, '333116', 'Toyota Corolla', 'Rear', [['Toyota', 'Corolla']], 40000, 40000],
        [198, '333117', 'Toyota Corolla', 'Rear', [['Toyota', 'Corolla']], 40000, 40000],
        [199, '333197', 'Toyota Carina, Premio old', 'Front', [['Toyota', 'Premio']], 50000, 50000],
        [200, '333198', 'Toyota Carina, Premio old', 'Front', [['Toyota', 'Premio']], 50000, 50000],
        [201, '333209', 'Toyota Raum, Starlet', 'Front', [['Toyota', 'Raum']], 40000, 40000],
        [202, '333210', 'Toyota Raum, Starlet', 'Front', [['Toyota', 'Raum']], 40000, 40000],
        [203, '333396', 'Nissan March', 'Front', [['Nissan', 'March']], 40000, 40000],
        [204, '333397', 'Nissan March', 'Front', [['Nissan', 'March']], 40000, 40000],
        [205, '333425', 'Suzuki Swift', null, [['Suzuki', 'Swift']], 35000, 35000],
        [206, '333426', 'Suzuki Swift', null, [['Suzuki', 'Swift']], 35000, 35000],
        [207, '334189', 'Subaru', 'Front', [], 60000, 60000],
        [208, '334190', 'Subaru', 'Front', [], 60000, 60000],
        [209, '334191', 'Subaru', 'Rear', [], 60000, 60000],
        [210, '334192', 'Subaru', 'Rear', [], 60000, 60000],
        [211, '334241', 'Toyota RAV4 old', 'Front', [['Toyota', 'RAV4']], 55000, 55000],
        [212, '334242', 'Toyota RAV4 old', 'Front', [['Toyota', 'RAV4']], 55000, 55000],
        [213, '334261', 'Toyota Harrier old', 'Front', [['Toyota', 'Harrier']], 50000, 50000],
        [214, '334262', 'Toyota Harrier old', 'Front', [['Toyota', 'Harrier']], 50000, 50000],
        [215, '334263', 'Toyota Harrier old', 'Rear', [['Toyota', 'Harrier']], 55000, 55000],
        [216, '334264', 'Toyota Harrier old', 'Rear', [['Toyota', 'Harrier']], 55000, 55000],
        [217, '334269', 'Toyota Harrier (chogo) 2WD', null, [['Toyota', 'Harrier']], 60000, 60000],
        [218, '334270', 'Toyota Harrier (chogo) 2WD', null, [['Toyota', 'Harrier']], 60000, 60000],
        [219, '334319', 'Toyota Noah, Voxy', null, [['Toyota', 'Noah'], ['Toyota', 'Voxy']], 50000, 50000],
        [220, '334320', 'Toyota Noah, Voxy', null, [['Toyota', 'Noah'], ['Toyota', 'Voxy']], 50000, 50000],
        [221, '334323', 'Toyota Corolla, Polis, RunX, Allion, Fielder, Allex, Premio and more', null, [['Toyota', 'Corolla'], ['Toyota', 'Allion'], ['Toyota', 'Premio']], 60000, 60000],
        [222, '334324', 'Toyota Corolla, Polis, RunX, Allion, Fielder, Allex, Premio and more', null, [['Toyota', 'Corolla'], ['Toyota', 'Allion'], ['Toyota', 'Premio']], 60000, 60000],
        [223, '334331', 'Toyota RAV4 (kill time)', null, [['Toyota', 'RAV4']], 50000, 50000],
        [224, '334332', 'Toyota RAV4 (kill time)', null, [['Toyota', 'RAV4']], 50000, 50000],
        [225, '334360', 'Nissan X-Trail', 'Front', [['Nissan', 'X-Trail']], 50000, 50000],
        [226, '334361', 'Nissan X-Trail', 'Front', [['Nissan', 'X-Trail']], 50000, 50000],
        [227, '334362', 'Nissan X-Trail', 'Rear', [['Nissan', 'X-Trail']], 50000, 50000],
        [228, '334363', 'Nissan X-Trail', 'Rear', [['Nissan', 'X-Trail']], 50000, 50000],
        [229, '334394', 'Toyota Harrier (chogo) 4WD', null, [['Toyota', 'Harrier']], 60000, 60000],
        [230, '334395', 'Toyota Harrier (chogo) 4WD', null, [['Toyota', 'Harrier']], 60000, 60000],
        [231, '334399', 'Toyota Harrier', 'Front', [['Toyota', 'Harrier']], 65000, 65000],
        [232, '334400', 'Toyota Harrier', 'Front', [['Toyota', 'Harrier']], 65000, 65000],
        [233, '339024', 'Toyota Camry', null, [['Toyota', 'Camry']], 35000, 35000],
        [234, '339023', 'Toyota Camry', null, [['Toyota', 'Camry']], 35000, 35000],
        [235, '339031', 'Toyota RAV4 old (2005–2013)', 'Front', [['Toyota', 'RAV4']], 55000, 55000],
        [236, '339032', 'Toyota RAV4 old (2005–2013)', 'Front', [['Toyota', 'RAV4']], 55000, 55000],
        [237, '339064', 'Toyota Vitz new, Ractis, IST, Porte', 'Front', [['Toyota', 'Vitz'], ['Toyota', 'IST'], ['Toyota', 'Porte']], 45000, 45000],
        [238, '339065', 'Toyota Vitz new, Ractis, IST, Porte', 'Front', [['Toyota', 'Vitz'], ['Toyota', 'IST'], ['Toyota', 'Porte']], 45000, 45000],
        [239, '339199', 'Nissan Dualis', null, [['Nissan', 'Dualis']], 60000, 60000],
        [240, '339198', 'Nissan Dualis', null, [['Nissan', 'Dualis']], 60000, 60000],
        [241, '341191', 'Toyota Raum old, Starlet', 'Rear', [['Toyota', 'Raum']], 35000, 30000],
        [242, '341288', 'Toyota Mark II old, Mark X', null, [['Toyota', 'Mark II'], ['Toyota', 'Mark X']], 35000, 35000],
        [243, '341340', 'Toyota 4Runner', 'Front', [], 30000, 30000],
        [244, '341397', 'Toyota Hilux Vigo, Innova', 'Front', [['Toyota', 'Hilux']], 30000, 30000],
        [245, '341398', 'Toyota Hilux Vigo, Innova', 'Front', [['Toyota', 'Hilux']], 30000, 30000],
        [246, '343272', 'Toyota RAV4 old (kill time)', 'Rear', [['Toyota', 'RAV4']], 30000, 30000],
        [247, '343295', 'Toyota IST, Vitz', 'Rear', [['Toyota', 'IST'], ['Toyota', 'Vitz']], 30000, 30000],
        [248, '343357', 'Toyota Noah', null, [['Toyota', 'Noah']], 30000, 30000],
        [249, '343358', 'Toyota Noah', null, [['Toyota', 'Noah']], 30000, 30000],
        [250, '343442', 'Toyota Yaris', 'Rear', [], 30000, 30000],
        [251, '343464', 'Suzuki Swift', 'Rear', [['Suzuki', 'Swift']], 35000, 35000],
        [252, '344410', 'Toyota FJ Cruiser, 4Runner', 'Rear', [], 35000, 35000],
        [253, '345007', 'Nissan Patrol', 'Front', [['Nissan', 'Patrol']], 30000, 30000],
        [254, '345010', 'Toyota Land Cruiser, Fuso', null, [['Toyota', 'Land Cruiser']], 40000, 40000],
        [255, '345020', 'Toyota Land Cruiser, Fuso', null, [['Toyota', 'Land Cruiser']], 40000, 40000],
        [256, '349078', 'Nissan Dualis, X-Trail, Qashqai', 'Rear', [['Nissan', 'Dualis'], ['Nissan', 'X-Trail']], 40000, 40000],
        [257, '345022', 'Toyota Land Cruiser old (2007)', null, [['Toyota', 'Land Cruiser']], 30000, 30000],
        [258, '345023', 'Toyota Land Cruiser old (2007)', null, [['Toyota', 'Land Cruiser']], 30000, 30000],
        [259, '345044', 'Toyota Land Cruiser old (2002)', null, [['Toyota', 'Land Cruiser']], 30000, 30000],
        [260, '349023', 'Toyota Hilux Vigo, Innova', null, [['Toyota', 'Hilux']], 30000, 30000],
        [261, '444104', 'Toyota Hiace', 'Front', [['Toyota', 'Hiace']], 30000, 30000],
        [262, '444107', 'Toyota Land Cruiser, Mitsubishi Canter', 'Rear', [['Toyota', 'Land Cruiser'], ['Mitsubishi', 'Canter']], 35000, 35000],
        [263, '444114', 'Toyota Land Cruiser, Mitsubishi Canter', 'Rear', [['Toyota', 'Land Cruiser'], ['Mitsubishi', 'Canter']], 35000, 35000],
        [264, '444123', 'Toyota Hiace (front shock up, nyuma)', null, [['Toyota', 'Hiace']], 30000, 35000],
        [265, '444178', 'Toyota Coaster', 'Rear', [['Toyota', 'Coaster']], 35000, 35000],
        [266, '445063', 'Toyota Coaster', 'Front', [['Toyota', 'Coaster']], 40000, 40000],
        [267, '551112', 'Toyota Mark X, Crown, Altezza', 'Front', [['Toyota', 'Mark X'], ['Toyota', 'Crown']], 50000, 50000],
        [268, '551113', 'Toyota Mark X, Crown, Altezza', 'Front', [['Toyota', 'Mark X'], ['Toyota', 'Crown']], 50000, 50000],
        [269, '551120', 'Toyota Mark X, Crown, Altezza', 'Front', [['Toyota', 'Mark X'], ['Toyota', 'Crown']], 50000, 50000],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $supplier = $this->kitonga();
            $photo = $this->photo('kitonga-shock-absorber.jpg');   // one photo for all of them
            foreach (self::ROWS as [$row, $no, $fits, $pos, $cars, $buy, $sell]) {
                $makes = collect($cars)->pluck(0)->unique();
                $firstMake = strtok($fits, ' ');
                $this->upsert($supplier, $no, [
                    'name' => "Gas Shock Absorber {$no}".($pos ? " ({$pos})" : '').' – '.$fits,
                    'brand' => null, 'category' => 'Car Parts', 'subcategory' => 'Shock Absorbers',
                    'purchase_price' => $buy, 'selling_price' => $this->ourPrice($buy, $sell),
                    'vehicle_make' => $makes->count() === 1 ? $makes->first() : (in_array($firstMake, ['Toyota', 'Nissan', 'Subaru', 'Suzuki'], true) ? $firstMake : null),
                    'vehicle_model' => $fits,
                    'image_path' => $photo,                 // only when the part has no picture yet
                    'specs' => array_filter(['Part number' => $no, 'Type' => 'Gas', 'Fitting position' => $pos, 'Fits' => $fits]),
                ], $cars);
            }
            $this->command?->info('Kitonga Spareparts: '.count(self::ROWS).' shock absorbers added or updated (no stock added, price +15%).');
        });
    }
}
