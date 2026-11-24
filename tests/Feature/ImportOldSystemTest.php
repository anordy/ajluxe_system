<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The whole old AJ AUTO PARTS DB workbook: parts, suppliers, customers, purchases, sales, payments, stock history. */
class ImportOldSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_import_deletes_all_test_data_and_keeps_setup(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->seed();
        $this->seed(\Database\Seeders\DemoShopSeeder::class);
        $admin = User::factory()->role(User::ADMIN)->create();
        $this->actingAs($admin, 'web');

        // test data made while trying the system: a garage customer, vehicle, job card, a counter sale, an online order account
        $cust = Customer::create(['code' => 'CUS-9999', 'name' => 'Test Customer', 'phone' => '0700000001', 'type' => 'Individual']);
        $veh = \App\Models\Vehicle::create(['customer_id' => $cust->id, 'reg_no' => 'T 999 TST', 'make' => 'Toyota', 'model' => 'IST']);
        $demo = Part::where('sku', 'like', 'DEMO-%')->where('stock_qty', '>', 0)->first();
        app(\App\Services\SalesManager::class)->createCounterSale([['part_id' => $demo->id, 'qty' => 1, 'rate' => $demo->selling_price]], ['paid' => $demo->selling_price]);
        \App\Models\ShopCustomer::create(['name' => 'Web Test', 'phone' => '0700000002', 'password' => 'secret123']);
        $services = \App\Models\Service::count();
        $cars = \App\Models\CarModel::count();

        $this->artisan('import:old-system', ['file' => base_path('tests/Fixtures/old-system.xlsx'), '--commit' => true, '--fresh' => true])
            ->expectsQuestion('--fresh deletes ALL parts, customers, job cards, sales and purchases now in the system, then imports. Type DELETE to continue', 'DELETE')
            ->expectsOutputToContain('Backup written')->expectsOutputToContain('match the old system')->assertSuccessful();

        $this->assertSame(0, Part::where('sku', 'like', 'DEMO-%')->withTrashed()->count());          // deleted, not hidden
        $this->assertSame(48, Part::withTrashed()->count());                                           // only the real parts
        $this->assertSame(0, Customer::withTrashed()->where('name', 'Test Customer')->count());
        $this->assertSame(0, \App\Models\Vehicle::count());
        $this->assertSame(0, \App\Models\ShopCustomer::count());
        $this->assertSame(26, Invoice::withTrashed()->count());                                        // only the old system's sales
        $this->assertSame(5, Customer::count());
        $this->assertSame(5, Supplier::count());
        // setup kept
        $this->assertTrue(User::whereKey($admin->id)->exists());
        $this->assertSame($services, \App\Models\Service::count());
        $this->assertSame($cars <= \App\Models\CarModel::count(), true);
        $this->assertNotEmpty(Storage::disk('local')->files('backups'));

        // without typing DELETE nothing happens
        $this->artisan('data:clear')->expectsQuestion('This cannot be undone (a backup file is made first). Type DELETE to continue', 'no')
            ->expectsOutputToContain('Nothing was deleted')->assertFailed();
        $this->assertSame(48, Part::count());
    }

    public function test_full_workbook_import_rebuilds_history_and_matches_old_balances(): void
    {
        Storage::fake('public');
        $this->seed();
        $this->seed(\Database\Seeders\DemoShopSeeder::class);
        $file = base_path('tests/Fixtures/old-system.xlsx');

        $this->artisan('import:old-system', ['file' => $file])
            ->expectsOutputToContain('Every part adds up')
            ->expectsOutputToContain('CPAY-0016')->expectsOutputToContain('SPAY-0002')
            ->expectsOutputToContain('Nothing was saved')->assertSuccessful();
        $this->assertSame(0, Invoice::where('number', 'INV-1001')->count());

        $this->artisan('import:old-system', ['file' => $file, '--commit' => true, '--remove-demo' => true])
            ->expectsOutputToContain('Stock and payments match the old system')->assertSuccessful();

        // PRT-0002 history exactly as in the old system
        $p = Part::where('sku', 'PRT-0002')->first();
        $this->assertEquals(16, $p->stock_qty);
        $moves = StockMovement::where('part_id', $p->id)->orderBy('date')->orderBy('id')->get();
        $this->assertSame(['opening', 'purchase', 'counter_sale', 'counter_sale'], $moves->pluck('type')->all());
        $this->assertSame([null, 'PO-501', 'INV-1002', 'INV-1020'], $moves->pluck('reference_no')->all());
        $this->assertSame(['12', '24', '20', '16'], $moves->map(fn ($m) => (string) (float) $m->balance_after)->all());

        // every part ends at the old CurrentStock (checked by the importer); spot checks
        $this->assertEquals(35, Part::where('sku', 'PRT-0013')->value('stock_qty'));   // includes adjustment ADJ-0001 (+10)

        // customers, suppliers and money
        $chalonge = Customer::where('code', 'CUS-003')->first();
        $this->assertSame('0655715265', $chalonge->phone);
        $this->assertEquals(239000, Invoice::where('customer_id', $chalonge->id)->whereIn('status', ['unpaid', 'partial'])->sum('balance'));
        $this->assertSame('partial', Invoice::where('number', 'INV-1012')->value('status'));
        $this->assertSame(['INV-1003', 'INV-1011', 'INV-1015', 'INV-1022'], Invoice::where('status', 'cancelled')->where('number', 'like', 'INV-1%')->orderBy('number')->pluck('number')->all());
        $this->assertEquals(0, Payment::where('invoice_id', Invoice::where('number', 'INV-1011')->value('id'))->sum('amount'));   // paid, then refunded
        $this->assertSame(0, Payment::where('number', 'CPAY-0016')->count());
        $this->assertSame(7, PurchaseOrder::where('number', 'like', 'PO-5%')->where('status', 'received')->count());
        $this->assertEquals(0, PurchaseOrder::where('number', 'like', 'PO-5%')->sum('balance'));
        $this->assertSame('Atlantic HQ', Supplier::where('code', 'SUP-001')->value('name'));

        // running again rebuilds the same result (no doubled stock, invoices or payments)
        $this->artisan('import:old-system', ['file' => $file, '--commit' => true])->expectsOutputToContain('match the old system')->assertSuccessful();
        $this->assertEquals(16, $p->fresh()->stock_qty);
        $this->assertSame(4, StockMovement::where('part_id', $p->id)->count());
        $this->assertSame(26, Invoice::withTrashed()->where('number', 'like', 'INV-1%')->count());
        $this->assertSame(23, Payment::where('number', 'like', 'CPAY-%')->count());   // 20 payments + 3 refunds of cancelled sales

        // the screens open with the imported data
        $this->actingAs(User::factory()->role(User::ADMIN)->create(), 'web');
        $this->get(route('dashboard'))->assertOk();
        $this->get('/invoices')->assertOk()->assertSee('INV-1017');
        $this->get('/purchase-orders?status=received')->assertOk()->assertSee('PO-507');

        // a part sold in the new system blocks a re-import instead of being counted wrongly
        StockMovement::record($p, 'adjustment', out: 1, referenceNo: 'ADJ-NEW');
        $this->artisan('import:old-system', ['file' => $file, '--commit' => true])->expectsOutputToContain('ADJ-NEW')->assertFailed();
        $this->assertEquals(15, $p->fresh()->stock_qty);
    }
}
