<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Livewire\JobCards\Create;
use App\Livewire\JobCards\Show;
use App\Livewire\Payments\RecordForm;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\Mechanic;
use App\Models\Part;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobCardManager;
use App\Support\NumberWords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3 checks: job cards, stock, status rules, invoices and payments.
 */
class JobCardTest extends TestCase
{
    use RefreshDatabase;

    private JobCardManager $m;
    private int $seq = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->m = app(JobCardManager::class);
    }

    private function as(string $role): User
    {
        $u = User::factory()->role($role)->create();
        $this->actingAs($u);

        return $u;
    }

    private function vehicle(): Vehicle
    {
        $c = Customer::create(['code' => 'CUS-'.(++$this->seq), 'name' => 'Juma Hassan', 'type' => 'Individual', 'phone' => '0712000000']);

        return Vehicle::create(['customer_id' => $c->id, 'reg_no' => 'T '.(++$this->seq).' ABC', 'make' => 'Toyota', 'model' => 'IST']);
    }

    private function job(): JobCard
    {
        $v = $this->vehicle();

        return $this->m->create(['customer_id' => $v->customer_id, 'vehicle_id' => $v->id, 'complaints' => 'Service due', 'priority' => 'Normal']);
    }

    private function part(float $stock = 10, float $buy = 100000, float $sell = 140000): Part
    {
        $p = Part::create(['sku' => 'PRT-'.(++$this->seq), 'name' => 'Engine Oil 5W-30', 'unit' => 'Pcs',
            'purchase_price' => $buy, 'selling_price' => $sell, 'is_active' => true]);
        if ($stock) {
            StockMovement::record($p, 'opening', in: $stock, unitCost: $buy);
        }

        return $p->refresh();
    }

    public function test_pages_and_roles(): void
    {
        $job = (function () { $this->as(User::ADMIN); return $this->job(); })();

        $this->get('/job-cards')->assertOk()->assertSee($job->number);
        $this->get('/job-cards/new')->assertOk();
        $this->get('/job-cards/'.$job->id)->assertOk()->assertSee($job->vehicle->reg_no);
        $this->get('/invoices')->assertOk();
        $this->get('/print/job-card/'.$job->id)->assertOk()->assertSee('JOB CARD');

        $this->as(User::MECHANIC);
        $this->get('/job-cards')->assertOk();
        $this->get('/job-cards/new')->assertForbidden();
        $this->get('/invoices')->assertForbidden();
        $this->get('/print/proforma/'.$job->id)->assertForbidden();

        $this->as(User::STAFF);
        $this->get('/job-cards')->assertForbidden();
        $this->get('/invoices')->assertOk();
    }

    public function test_create_job_card_with_advance_through_the_screen(): void
    {
        $this->as(User::ADMIN);
        $v = $this->vehicle();
        $mech = Mechanic::create(['name' => 'Baraka', 'is_active' => true]);

        Livewire::test(Create::class)
            ->call('pickCustomer', $v->customer_id)
            ->assertSet('vehicle_id', $v->id) // only vehicle is auto-picked
            ->set('mechanic_id', $mech->id)
            ->set('complaints', 'Brakes noisy')
            ->set('odometer_in', '85000')
            ->set('advance', '50000')
            ->set('advance_mode', 'M-Pesa')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $job = JobCard::firstOrFail();
        $this->assertSame('JC-'.now()->year.'-0001', $job->number);
        $this->assertSame(JobCard::OPEN, $job->status);
        $this->assertEquals(0, (float) $job->tax_rate); // default tax is 0
        $this->assertEquals(85000, $v->fresh()->odometer);
        $this->assertEquals(50000, (float) Payment::where('job_card_id', $job->id)->whereNull('invoice_id')->sum('amount'));
        $this->assertSame(1, $job->history()->count());
    }

    public function test_parts_come_out_of_stock_and_go_back(): void
    {
        $this->as(User::ADMIN);
        $job = $this->job();
        $oil = $this->part(stock: 10);

        $item = $this->m->addItem($job, ['type' => 'part', 'part_id' => $oil->id, 'description' => 'Engine oil', 'qty' => 3, 'rate' => 160000]);
        $this->assertEquals(7, (float) $oil->fresh()->stock_qty);
        $this->assertEquals(420000, (float) $item->cost);       // garage cost = 3 × spare-parts selling price
        $this->assertEquals(300000, (float) $item->stock_cost); // stock cost = 3 × buying price
        $this->assertEquals(480000, (float) $job->fresh()->grand_total);

        $this->m->updateItem($item, ['qty' => 5]);
        $this->assertEquals(5, (float) $oil->fresh()->stock_qty);

        $this->m->updateItem($item->fresh(), ['qty' => 2]);
        $this->assertEquals(8, (float) $oil->fresh()->stock_qty);

        $this->expectException(BusinessRuleException::class);
        try {
            $this->m->addItem($job, ['type' => 'part', 'part_id' => $oil->id, 'description' => 'Engine oil', 'qty' => 50, 'rate' => 1]);
        } finally {
            $this->assertEquals(8, (float) $oil->fresh()->stock_qty); // nothing taken on failure

            $this->m->removeItem($item->fresh());
            $this->assertEquals(10, (float) $oil->fresh()->stock_qty);
            $this->assertEquals(0, (float) $job->fresh()->grand_total);
        }
    }

    public function test_totals_discount_tax_and_profit(): void
    {
        $this->as(User::ADMIN);
        $job = $this->job();
        $svc = Service::where('pricing', 'hourly')->first() ?? Service::first();
        $this->m->addItem($job, ['type' => 'service', 'service_id' => $svc->id, 'description' => 'Labour', 'qty' => 2, 'rate' => 50000]);
        $this->m->addItem($job, ['type' => 'part', 'part_id' => $this->part()->id, 'description' => 'Oil', 'qty' => 1, 'rate' => 160000]);
        $this->m->addItem($job, ['type' => 'other', 'description' => 'Towing', 'qty' => 1, 'rate' => 40000, 'cost' => 30000]);

        $this->m->setDiscountAndTax($job, 10, 18);                   // 10% discount, 18% VAT
        $job->refresh()->load('items');
        $this->assertEquals(300000, (float) $job->subtotal);
        $this->assertEquals(30000, (float) $job->discount);
        $this->assertEquals(48600, (float) $job->tax_amount);       // 18% of 270,000
        $this->assertEquals(318600, (float) $job->grand_total);
        $this->assertEquals(170000, $job->totalCost());              // 140,000 oil (shop price) + 30,000 towing
        $this->assertEquals(100000, $job->profit());                 // 270,000 − 170,000

        // the percentage stays when more items are added
        $this->m->addItem($job, ['type' => 'other', 'description' => 'Wash', 'qty' => 1, 'rate' => 100000]);
        $this->assertEquals(40000, (float) $job->fresh()->discount);

        $this->expectException(BusinessRuleException::class);
        $this->m->setDiscountAndTax($job, 150, 0);
    }

    public function test_status_rules(): void
    {
        $this->as(User::ADMIN);
        $job = $this->job();

        $this->m->changeStatus($job, JobCard::IN_PROGRESS);
        $this->assertSame(JobCard::IN_PROGRESS, $job->fresh()->status);

        foreach ([JobCard::READY, JobCard::DELIVERED] as $blocked) {
            try {
                $this->m->changeStatus($job->fresh(), $blocked);
                $this->fail("Empty job moved to {$blocked}");
            } catch (BusinessRuleException) {
            }
        }
        $this->m->addItem($job, ['type' => 'other', 'description' => 'Diagnosis', 'qty' => 1, 'rate' => 30000]);
        $this->m->changeStatus($job->fresh(), JobCard::READY);

        try {
            $this->m->changeStatus($job->fresh(), JobCard::DELIVERED);
            $this->fail('Delivered without invoice');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('invoice', $e->getMessage());
        }

        $this->m->generateInvoice($job->fresh());
        $this->m->changeStatus($job->fresh(), JobCard::DELIVERED);
        $job->refresh();
        $this->assertNotNull($job->delivered_at);
        $this->assertTrue($job->isClosed());

        $this->expectException(BusinessRuleException::class);
        $this->m->changeStatus($job, JobCard::IN_PROGRESS);
    }

    public function test_cancel_returns_parts_and_is_blocked_by_payments(): void
    {
        $this->as(User::ADMIN);
        $job = $this->job();
        $oil = $this->part(stock: 4);
        $this->m->addItem($job, ['type' => 'part', 'part_id' => $oil->id, 'description' => 'Oil', 'qty' => 4, 'rate' => 160000]);
        $this->assertEquals(0, (float) $oil->fresh()->stock_qty);

        $this->m->changeStatus($job->fresh(), JobCard::CANCELLED, 'Customer left');
        $this->assertSame(JobCard::CANCELLED, $job->fresh()->status);
        $this->assertEquals(4, (float) $oil->fresh()->stock_qty);

        $job2 = $this->job();
        $this->m->recordPayment($job2, null, 10000, 'Cash');
        $this->expectException(BusinessRuleException::class);
        $this->m->changeStatus($job2->fresh(), JobCard::CANCELLED);
    }

    public function test_invoice_takes_advance_and_payments_settle_it(): void
    {
        $this->as(User::ADMIN);
        $job = $this->job();
        $this->m->recordPayment($job, null, 100000, 'Cash');
        $this->m->addItem($job, ['type' => 'other', 'description' => 'Full service', 'qty' => 1, 'rate' => 364000]);

        $inv = $this->m->generateInvoice($job->fresh());
        $this->assertSame('INV-'.now()->year.'-0001', $inv->number);
        $this->assertEquals(100000, (float) $inv->paid);
        $this->assertEquals(264000, (float) $inv->balance);
        $this->assertSame(Invoice::PARTIAL, $inv->status);
        $this->assertSame(1, $inv->items()->count());

        // items locked, no second invoice
        foreach ([fn () => $this->m->addItem($job->fresh(), ['type' => 'other', 'description' => 'x', 'qty' => 1, 'rate' => 1]),
                  fn () => $this->m->generateInvoice($job->fresh())] as $fn) {
            try { $fn(); $this->fail('Should be blocked'); } catch (BusinessRuleException) {}
        }

        // overpaying is refused; paying the balance settles the invoice
        try { $this->m->recordPayment(null, $inv, 300000, 'Cash'); $this->fail('Overpayment accepted'); } catch (BusinessRuleException) {}

        Livewire::test(RecordForm::class)
            ->call('open', invoiceId: $inv->id)
            ->assertSet('amount', '264000')
            ->set('mode', 'M-Pesa')->set('reference', 'qhx12ab')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('payment-saved');

        $inv->refresh();
        $this->assertSame(Invoice::PAID, $inv->status);
        $this->assertEquals(0, (float) $inv->balance);
        $this->assertSame('QHX12AB', Payment::latest('id')->first()->reference);
        $this->assertEquals(0, $job->customer->balance());

        $this->get('/print/invoice/'.$inv->id.'?noprint')->assertOk()
            ->assertSee('TAX INVOICE')->assertSee('CRDB BANK')->assertSee('Three Hundred Sixty-Four Thousand Shillings Only')
            ->assertSee('Authorised signature', false);
        $this->get('/print/receipt/'.Payment::latest('id')->first()->id)->assertOk()->assertSee('PAYMENT RECEIPT');
    }

    public function test_proforma_has_no_signatures_and_empty_job_cannot_be_invoiced(): void
    {
        $this->as(User::ADMIN);
        $job = $this->job();

        Livewire::test(Show::class, ['jobCard' => $job])
            ->call('generateInvoice')
            ->assertDispatched('toast', type: 'error');
        $this->assertSame(0, Invoice::count());

        $this->m->addItem($job, ['type' => 'other', 'description' => 'Wheel alignment', 'qty' => 1, 'rate' => 45000]);
        $this->get('/print/proforma/'.$job->id.'?noprint')->assertOk()
            ->assertSee('PROFORMA INVOICE')->assertSee('Wheel alignment')->assertSee('LIPA NAMBA')
            ->assertDontSee('Authorised signature')->assertDontSee('subject to change');
    }

    public function test_screen_add_part_line_and_mechanic_limits(): void
    {
        $admin = $this->as(User::ADMIN);
        $job = $this->job();
        $oil = $this->part(stock: 5, buy: 100000, sell: 140000);

        $svc = Service::first();
        Livewire::test(Show::class, ['jobCard' => $job])
            ->call('openAdd', 'part')
            ->call('pickPart', $oil->id)
            ->assertSet('pending.0.rate', '140000')
            ->set('pending.0.rate', '160000')   // garage price above the shop price
            ->set('pending.0.qty', '2')
            ->set('itemType', 'service')         // switching tabs keeps the list
            ->call('pickService', $svc->id)
            ->set('pending.1.rate', '50000')->set('pending.1.qty', '1')
            ->set('itemType', 'other')
            ->set('other.description', 'Towing')->set('other.rate', '30000')->set('other.cost', '20000')
            ->call('addOther')
            ->assertCount('pending', 3)
            ->call('savePending')
            ->assertHasNoErrors()
            ->assertSet('pending', [])
            ->call('openAdd', 'part')->call('pickPart', $oil->id)->set('pending.0.qty', '9')
            ->call('openAdd', 'other')->set('other.description', 'Wash')->set('other.rate', '5000')->call('addOther')
            ->call('savePending')
            ->assertHasErrors('pending')       // not enough stock → nothing saved
            ->assertCount('pending', 2);

        $job->refresh()->load('items');
        $this->assertCount(3, $job->items);
        $this->assertEquals(320000 + 50000 + 30000, (float) $job->grand_total);
        $this->assertEquals(280000 + 20000, $job->totalCost());   // part cost defaults to the shop's selling price
        $this->assertEquals(3, (float) $oil->fresh()->stock_qty);

        // edit a line in its own dialog
        $partLine = $job->items->firstWhere('type', 'part');
        Livewire::test(Show::class, ['jobCard' => $job])
            ->call('editItem', $partLine->id)->set('line.qty', '1')->call('saveItem')->assertHasNoErrors();
        $this->assertEquals(4, (float) $oil->fresh()->stock_qty);
        $this->assertEquals(240000, (float) $job->fresh()->grand_total);

        $this->as(User::MECHANIC);
        Livewire::test(Show::class, ['jobCard' => $job])
            ->call('setStatus', JobCard::IN_PROGRESS)
            ->assertHasNoErrors()
            ->call('openAdd', 'other')->assertForbidden();
        $this->assertSame(JobCard::IN_PROGRESS, $job->fresh()->status);
    }

    public function test_number_words(): void
    {
        $this->assertSame('Three Hundred Sixty-Four Thousand Shillings Only', NumberWords::shillings(364000));
        $this->assertSame('One Million Two Hundred Five Thousand Shillings Only', NumberWords::shillings(1205000));
        $this->assertSame('Zero Shillings Only', NumberWords::shillings(0));
    }

    public function test_minimum_selling_price(): void
    {
        $this->as(User::MANAGER);
        $job = $this->job();
        $oil = $this->part(stock: 10);
        $oil->update(['min_selling_price' => 130000]);

        try {
            $this->m->addItem($job, ['type' => 'part', 'part_id' => $oil->id, 'description' => 'Oil', 'qty' => 1, 'rate' => 120000]);
            $this->fail('Sold below the minimum price');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('lowest price', $e->getMessage());
        }
        $this->assertEquals(10, (float) $oil->fresh()->stock_qty);
        $item = $this->m->addItem($job, ['type' => 'part', 'part_id' => $oil->id, 'description' => 'Oil', 'qty' => 1, 'rate' => 135000]);

        $this->as(User::ADMIN); // admins may go below
        $this->m->updateItem($item, ['rate' => 100000]);
        $this->assertEquals(100000, (float) $item->fresh()->rate);
    }

    public function test_new_job_card_finds_vehicle_by_plate(): void
    {
        $this->as(User::ADMIN);
        $v = $this->vehicle();
        $compact = str_replace(' ', '', strtolower($v->reg_no)); // e.g. "t101abc"

        Livewire::test(Create::class)
            ->set('customerSearch', $compact)
            ->assertSee($v->reg_no)
            ->call('pickPlate', $v->id)
            ->assertSet('customer_id', $v->customer_id)
            ->assertSet('vehicle_id', $v->id);
    }

    public function test_customer_page_lists_every_job_card_and_statuses_have_colours(): void
    {
        $admin = \App\Models\User::factory()->role(\App\Models\User::ADMIN)->create();
        $this->actingAs($admin);
        $c = \App\Models\Customer::create(['code' => 'CUS-9001', 'name' => 'AJ Garage', 'type' => 'Individual', 'phone' => '0754000000']);
        $v = \App\Models\Vehicle::create(['customer_id' => $c->id, 'reg_no' => 'T 1 AAA', 'make' => 'Toyota']);
        $m = app(\App\Services\JobCardManager::class);
        for ($i = 0; $i < 12; $i++) {
            $m->create(['customer_id' => $c->id, 'vehicle_id' => $v->id, 'complaints' => 'Job '.$i, 'priority' => 'Normal']);
        }
        $c->update(['name' => 'AJ']);   // renaming keeps every job card linked

        $page = $this->get(route('customers.show', $c))->assertOk();
        $this->assertSame(12, substr_count($page->getContent(), 'wire:key="cj'));
        $this->get(route('job-cards.index', ['customer' => $c->id, 'tab' => 'all']))->assertOk()->assertSee('Customer: AJ');
        \Livewire\Livewire::test(\App\Livewire\JobCards\Index::class, ['customer' => (string) $c->id])->set('tab', 'all')
            ->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 12);

        $this->assertStringContainsString('emerald', \App\Models\JobCard::BADGES[\App\Models\JobCard::DELIVERED]);
        $this->assertStringNotContainsString('ink-900', \App\Models\JobCard::BADGES[\App\Models\JobCard::DELIVERED]);
    }
}
