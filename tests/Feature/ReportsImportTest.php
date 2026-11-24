<?php

namespace Tests\Feature;

use App\Livewire\Import\Index as ImportScreen;
use App\Livewire\Reports\Index as ReportsScreen;
use App\Models\Customer;
use App\Models\Part;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Importer;
use App\Services\JobCardManager;
use App\Services\Reports;
use App\Services\ReturnManager;
use App\Services\SalesManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5 checks: report figures, CSV export, dashboard, import.
 */
class ReportsImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function as(string $role): User
    {
        $u = User::factory()->role($role)->create();
        $this->actingAs($u);

        return $u;
    }

    /** One garage job (labour 50k + part 160k cost 100k) and one counter sale (2 × 25k, cost 2 × 15k). */
    private function business(): array
    {
        $c = Customer::create(['code' => 'CUS-0001', 'name' => 'Juma', 'type' => 'Individual', 'phone' => '0712000000']);
        $v = Vehicle::create(['customer_id' => $c->id, 'reg_no' => 'T 100 AAA', 'make' => 'Toyota']);
        $oil = Part::create(['sku' => 'P1', 'name' => 'Oil', 'unit' => 'Pcs', 'purchase_price' => 100000, 'selling_price' => 140000, 'is_active' => true]);
        $filter = Part::create(['sku' => 'P2', 'name' => 'Filter', 'unit' => 'Pcs', 'purchase_price' => 15000, 'selling_price' => 25000, 'is_active' => true, 'reorder_level' => 5]);
        StockMovement::record($oil, 'opening', in: 5, unitCost: 100000);
        StockMovement::record($filter, 'opening', in: 6, unitCost: 15000);

        $m = app(JobCardManager::class);
        $job = $m->create(['customer_id' => $c->id, 'vehicle_id' => $v->id, 'complaints' => 'Service', 'priority' => 'Normal']);
        $m->addItem($job, ['type' => 'service', 'service_id' => Service::first()->id, 'description' => 'Labour', 'qty' => 1, 'rate' => 50000]);
        $m->addItem($job, ['type' => 'part', 'part_id' => $oil->id, 'description' => 'Oil', 'qty' => 1, 'rate' => 160000]);
        $inv = $m->generateInvoice($job->fresh());
        $m->recordPayment(null, $inv, 110000, 'M-Pesa');

        $sale = app(SalesManager::class)->createCounterSale([['part_id' => $filter->id, 'qty' => 2, 'rate' => 25000]], ['paid' => 50000, 'mode' => 'Cash']);

        return compact('c', 'inv', 'sale', 'filter', 'oil');
    }

    public function test_report_figures(): void
    {
        $this->as(User::ADMIN);
        $b = $this->business();
        $r = app(Reports::class);
        $today = today()->toDateString();

        $sales = $r->sales($today, $today);
        $this->assertEquals(210000, $sales['garage']['total']);
        $this->assertEquals(50000, $sales['garage']['labour']);
        $this->assertEquals(160000, $sales['garage']['parts']);
        $this->assertEquals(50000, $sales['counter']['total']);
        $this->assertEquals(260000, $sales['total']);
        $this->assertEquals(100000, $sales['unpaid']);
        $this->assertEquals(260000, collect($sales['daily'])->sum(fn ($d) => $d['garage'] + $d['counter']));

        $profit = $r->profit($today, $today);
        $this->assertEquals(70000, $profit['rows']['garage']['profit']);     // 210k − 140k (oil at the shop's price)
        $this->assertEquals(40000, $profit['rows']['to_garage']['profit']);  // shop: 140k − 100k buying price
        $this->assertEquals(20000, $profit['rows']['counter']['profit']);    // 50k − 30k
        $this->assertEquals(130000, $profit['profit']);                      // whole business: counted once
        $this->assertEquals(60000, $profit['sparesProfit']);

        $pay = $r->payments($today, $today);
        $this->assertEquals(160000, $pay['totalIn']);
        $this->assertEquals(110000, collect($pay['in'])->firstWhere('mode', 'M-Pesa')['net']);

        $rec = $r->receivables();
        $this->assertEquals(100000, $rec['totals']['total']);
        $this->assertEquals(100000, $rec['totals']['d30']);
        $this->assertSame('Juma', $rec['rows'][0]['name']);

        // a return lowers sales and profit
        app(ReturnManager::class)->customerReturn($b['sale'], [$b['filter']->id => 1], 'Wrong');
        $profit = $r->profit($today, $today);
        $this->assertEquals(130000 - (25000 - 15000), $profit['profit']);

        $stock = $r->stock($today, $today);
        $this->assertSame('Oil', $stock['top'][0]['name']);
        $this->assertCount(1, $stock['low']); // filter: 5 left, re-order at 5

        $mech = $r->mechanics($today, $today);
        $this->assertSame(1, $mech[0]['jobs']);
        $this->assertEquals(50000, $mech[0]['labour']);
    }

    /** Profit is booked when the money comes in, in proportion to what was paid. */
    public function test_profit_is_counted_when_paid(): void
    {
        $this->as(User::ADMIN);
        $this->business();
        $today = today()->toDateString();
        $a = app(\App\Services\Analytics::class)->summary($today, $today);

        $this->assertEquals(160000, $a['money']);
        $this->assertEquals(['garage' => 110000, 'shop' => 50000, 'online' => 0], $a['moneyBy']);
        // garage: 210k invoice, 110k profit at buying price, 110k of it paid → 110k × 110/210
        $this->assertEqualsWithDelta(57619.05, $a['profitBy']['garage'], 0.01);
        $this->assertEquals(20000, $a['profitBy']['shop']);           // fully paid counter sale
        $this->assertEqualsWithDelta(77619.05, $a['profit'], 0.01);
        $this->assertEqualsWithDelta(83809.52, $a['partsBy']['garage'], 0.01); // oil fitted, paid share
        $this->assertEquals(50000, $a['partsBy']['shop']);
        $this->assertEquals(100000, $a['owed']['garage']);
        $this->assertEqualsWithDelta(52380.95, $a['owedProfit'], 0.01);  // the rest arrives with the balance
        $this->assertEqualsWithDelta(110000 + 20000, $a['profit'] + $a['owedProfit'], 0.01);

        // the two businesses on their own: garage pays the shop's price (140k) for the oil
        $g = app(\App\Services\Analytics::class)->summary($today, $today, 'garage');
        $this->assertEquals(110000, $g['money']);
        $this->assertEqualsWithDelta(70000 * 110 / 210, $g['profit'], 0.01);          // 210k − 140k, paid share
        $sp = app(\App\Services\Analytics::class)->summary($today, $today, 'spares');
        $this->assertEqualsWithDelta(50000 + 140000 * 110 / 210, $sp['money'], 0.01);  // counter + oil supplied (paid share)
        $this->assertEqualsWithDelta(20000 + 40000 * 110 / 210, $sp['profit'], 0.01);  // counter + shop margin on the oil
        $this->assertEqualsWithDelta($a['profit'], $g['profit'] + $sp['profit'], 0.01); // together = whole business
        $this->assertSame(['shop', 'online'], array_keys($sp['owed']));
        $this->assertEquals(100000, $g['owedTotal']);

        foreach (['garage' => 'Garage profit collected', 'spares' => 'Spare parts profit collected', 'all' => 'Profit collected'] as $b => $text) {
            $this->get('/dashboard?business='.$b)->assertOk()->assertSee($text);
        }
        $this->get('/dashboard?business=garage')->assertDontSee('Top-selling spare parts');
        $this->get('/dashboard?business=spares')->assertDontSee('Most common garage jobs');

        // nothing paid yesterday → nothing counted
        $this->assertEquals(0, app(\App\Services\Analytics::class)->summary(today()->subDay()->toDateString(), today()->subDay()->toDateString())['profit']);

        foreach (array_keys(\App\Livewire\Dashboard::RANGES) as $range) {
            $this->get('/dashboard?range='.$range)->assertOk()->assertSee('Profit collected');
        }
    }

    public function test_screens_export_and_roles(): void
    {
        $this->as(User::MANAGER);
        $this->business();

        foreach (array_keys(Reports::REPORTS) as $report) {
            Livewire::test(ReportsScreen::class)->set('report', $report)->assertOk();
            $res = $this->get(route('reports.export', ['report' => $report]));
            $res->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        }
        Livewire::test(ReportsScreen::class)->set('range', 'last_month')
            ->assertSet('from', today()->subMonthNoOverflow()->startOfMonth()->toDateString());

        $csv = $this->get(route('reports.export', ['report' => 'profit']))->streamedContent();
        $this->assertStringContainsString('Garage jobs', $csv);

        $this->get('/dashboard')->assertOk()->assertSee('Money collected by channel')->assertSee('Profit collected by channel')
            ->assertSee('Where spare parts are sold')->assertSee('Most common garage jobs')->assertSee('Top-selling spare parts')
            ->assertDontSee('In the Workshop')->assertDontSee('Getting Started');
        $this->get('/reports')->assertOk();
        $this->get('/import')->assertForbidden();

        $this->as(User::STAFF);
        $this->get('/reports')->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertSee('Shop sales today')->assertDontSee('Profit collected');
    }

    /** Upload the CSV the way the browser does (plain form post), then open the import screen. */
    private function upload(string $type, string $name, string $content)
    {
        $this->post(route('import.upload'), ['type' => $type, 'file' => UploadedFile::fake()->createWithContent($name, $content)])
            ->assertRedirect(route('import.index', ['type' => $type]));

        return Livewire::test(ImportScreen::class)->set('type', $type);
    }

    public function test_import_rejects_non_csv(): void
    {
        $this->as(User::ADMIN);
        $this->post(route('import.upload'), ['type' => 'parts', 'file' => UploadedFile::fake()->create('photo.jpg', 10)])->assertRedirect();
        Livewire::test(ImportScreen::class)->assertHasErrors('file')->assertSet('path', null);
    }

    public function test_part_photo_and_logo_are_saved_from_browser_data(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->as(User::ADMIN);
        $img = imagecreatetruecolor(20, 20);
        ob_start(); imagejpeg($img); $jpg = 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
        ob_start(); imagepng($img); $png = 'data:image/png;base64,'.base64_encode(ob_get_clean());

        Livewire::test(\App\Livewire\Parts\Index::class)
            ->call('create')->set('form.name', 'Photo Part')->set('photoData', $jpg)->call('save')->assertHasNoErrors();
        $part = Part::where('name', 'Photo Part')->first();
        $this->assertNotNull($part->image_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($part->image_path);

        // a fake "image" is refused and nothing is saved
        Livewire::test(\App\Livewire\Parts\Index::class)
            ->call('create')->set('form.name', 'Bad Photo')->set('photoData', 'data:image/png;base64,'.base64_encode('<?php echo 1;'))
            ->call('save')->assertHasErrors('photoData');
        $this->assertFalse(Part::where('name', 'Bad Photo')->exists());

        Livewire::test(\App\Livewire\Settings\Edit::class)->set('logoData', $png)->call('save')->assertHasNoErrors();
        $this->assertStringStartsWith('branding/', setting('logo_path'));
    }

    public function test_import_parses_and_maps_google_sheets_csv(): void
    {
        $imp = app(Importer::class);
        $this->assertSame(364000.0, $imp->number('TZS 364,000'));
        $this->assertSame(1234.5, $imp->number('1.234,50'));
        $this->assertNull($imp->number(''));

        $map = $imp->guessMapping('parts', ['Item Name', 'Part No', 'Cost Price', 'Selling Price', 'Qty', 'Rack']);
        $this->assertSame(0, $map['name']);
        $this->assertSame(1, $map['sku']);
        $this->assertSame(2, $map['purchase_price']);
        $this->assertSame(3, $map['selling_price']);
        $this->assertSame(4, $map['stock']);
        $this->assertSame(5, $map['rack_location']);
    }

    public function test_import_through_the_screen(): void
    {
        $this->as(User::ADMIN);

        $customers = "\xEF\xBB\xBFCustomer Name,Phone,Address,Balance\nAsha Mollel,712345678,Sinza,\"50,000\"\nBaraka Said,+255 754 111 222,Mbezi,\n,0700000000,No name,\n";
        $this->upload('customers', 'customers.csv', $customers)
            ->assertSet('rowCount', 3)
            ->assertSet('mapping.name', 0)
            ->assertSet('mapping.opening_balance', 3)
            ->call('run')
            ->assertSet('result.created', 2)
            ->assertSet('result.errors', [4 => 'Name is empty.']);

        $asha = Customer::where('name', 'Asha Mollel')->first();
        $this->assertSame('0712345678', $asha->phone);       // leading zero restored
        $this->assertEquals(50000, (float) $asha->opening_balance);
        $this->assertSame('0754111222', Customer::where('name', 'Baraka Said')->value('phone'));

        // vehicles: owner found by phone, or created
        $vehicles = "Reg No,Owner,Phone,Make,Model\nt 555 xyz,Asha Mollel,0712345678,Toyota,IST\nT 777 ABC,New Owner,0788000000,Nissan,March\n";
        $this->upload('vehicles', 'vehicles.csv', $vehicles)
            ->call('run')
            ->assertSet('result.created', 2);
        $this->assertSame($asha->id, Vehicle::where('reg_no', 'T 555 XYZ')->value('customer_id'));
        $this->assertTrue(Customer::where('name', 'New Owner')->exists());

        // parts with opening stock; importing again skips, or updates prices without touching stock
        $parts = "Item Name,Brand,Cost Price,Selling Price,Qty\nBrake Pads,Toyota,\"45,000\",\"70,000\",8\n";
        $run = function (bool $update) use (&$parts) {
            return $this->upload('parts', 'parts.csv', $parts)->set('updateExisting', $update)->call('run');
        };
        $run(false)->assertSet('result.created', 1);
        $pads = Part::where('name', 'Brake Pads')->first();
        $this->assertEquals(8, (float) $pads->stock_qty);
        $this->assertSame('opening', StockMovement::where('part_id', $pads->id)->value('type'));
        $run(false)->assertSet('result.skipped', 1);
        $parts = "Item Name,Brand,Cost Price,Selling Price,Qty\nBrake Pads,Toyota,\"46,000\",\"75,000\",99\n";
        $run(true)->assertSet('result.updated', 1);
        $this->assertEquals(75000, (float) $pads->fresh()->selling_price);
        $this->assertEquals(8, (float) $pads->fresh()->stock_qty);

        // services: seeded names get their prices
        $name = Service::first()->name;
        $this->upload('services', 'services.csv', "Service,Price\n{$name},55000\n")
            ->set('updateExisting', true)->call('run')->assertSet('result.updated', 1);
        $this->assertEquals(55000, (float) Service::where('name', $name)->value('rate'));

        // suppliers + template download
        $this->upload('suppliers', 's.csv', "Supplier,Phone\nKariakoo Spares,0713000000\n")
            ->call('run')->assertSet('result.created', 1);
        $this->assertTrue(Supplier::where('name', 'Kariakoo Spares')->exists());
        $this->get(route('import.template', 'parts'))->assertOk();
    }
}
