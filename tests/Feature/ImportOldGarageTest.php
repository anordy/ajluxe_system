<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\JobCardItem;
use App\Models\JobStatusHistory;
use App\Models\Part;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Numbering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The old garage workbook merged into the system after the spare parts workbook. */
class ImportOldGarageTest extends TestCase
{
    use RefreshDatabase;

    public function test_garage_workbook_merges_customers_and_rebuilds_job_cards(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->seed();
        $this->artisan('import:old-system', ['file' => base_path('tests/Fixtures/old-system.xlsx'), '--commit' => true, '--fresh' => true, '--no-interaction' => true])->assertSuccessful();
        $file = base_path('tests/Fixtures/old-garage.xlsx');

        $this->artisan('import:old-garage', ['file' => $file])
            ->expectsOutputToContain('Every job card and invoice adds up')
            ->expectsOutputToContain('CUS-001 AJ → existing CUS-005 AJ Garage')
            ->expectsOutputToContain('Nothing was saved')->assertSuccessful();
        $this->assertSame(0, JobCard::count());

        $this->artisan('import:old-garage', ['file' => $file, '--commit' => true])->expectsOutputToContain('Every invoice balance matches')->assertSuccessful();

        // merged customers: one record with both histories
        $this->assertSame(11, Customer::count());   // 5 from the shop + 6 new from the garage
        $chalonge = Customer::where('code', 'CUS-003')->first();
        $this->assertSame(23, Invoice::where('customer_id', $chalonge->id)->count());
        $this->assertSame('0744019669', Customer::where('name', 'Shemela')->value('alt_phone'));

        // job cards, lines, history, invoices
        $this->assertSame(101, JobCard::count());
        $job = JobCard::where('number', 'AJ-2026-0001')->first();
        $this->assertSame('Delivered', $job->status);
        $this->assertEquals(100000, $job->grand_total);
        $this->assertSame('T 239 EAM', $job->vehicle->reg_no);
        $this->assertSame(5, JobStatusHistory::where('job_card_id', $job->id)->count());
        $this->assertSame(75, Invoice::where('type', 'garage')->count());
        $this->assertEquals(330000, Invoice::where('type', 'garage')->sum('balance'));

        // vehicles: duplicates merged, made-up plates kept apart
        $this->assertSame(1, Vehicle::where('reg_no', 'T 287 EDL')->count());
        $this->assertSame(27, Vehicle::where('reg_no', 'like', 'NO PLATE VEH-%')->count());

        // garage oil: linked to the shop's part, stock unchanged
        $oil = Part::where('sku', 'PRT-0001')->first();
        $this->assertEquals(17, $oil->stock_qty);
        $this->assertSame(1, JobCardItem::where('part_id', $oil->id)->count());

        // numbering continues after the old numbers
        $this->assertSame('AJ-2026-0102', Numbering::next('job_card'));
        $this->assertSame('INV-2026-0076', Numbering::next('invoice'));

        // a second run gives the same result
        $this->artisan('import:old-garage', ['file' => $file, '--commit' => true])->assertSuccessful();
        $this->assertSame(101, JobCard::count());
        $this->assertSame(11, Customer::count());
        $this->assertSame(98, Vehicle::count());

        // screens open
        $this->actingAs(User::factory()->role(User::ADMIN)->create(), 'web');
        $this->get('/job-cards')->assertOk();
        $this->get(route('job-cards.show', JobCard::where('number', 'AJ-2026-0001')->first()))->assertOk()->assertSee('AJ-2026-0001');
        $this->get(route('dashboard'))->assertOk();
    }
}
