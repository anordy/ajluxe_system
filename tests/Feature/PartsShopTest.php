<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Pos\Index as Pos;
use App\Livewire\PurchaseOrders\Form as PoForm;
use App\Livewire\PurchaseOrders\Show as PoShow;
use App\Livewire\Returns\Index as Returns;
use App\Livewire\Stock\Index as Stock;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseManager;
use App\Services\ReturnManager;
use App\Services\SalesManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 checks: POS, stock adjustments, purchase orders, returns.
 */
class PartsShopTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 100;

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

    private function part(float $stock = 10, float $buy = 15000, float $sell = 25000): Part
    {
        $p = Part::create(['sku' => 'PRT-'.(++$this->seq), 'name' => 'Oil Filter '.$this->seq, 'unit' => 'Pcs',
            'purchase_price' => $buy, 'selling_price' => $sell, 'is_active' => true, 'reorder_level' => 3]);
        if ($stock) {
            StockMovement::record($p, 'opening', in: $stock, unitCost: $buy);
        }

        return $p->refresh();
    }

    private function customer(): Customer
    {
        return Customer::create(['code' => 'CUS-'.(++$this->seq), 'name' => 'Asha Mollel', 'type' => 'Individual']);
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['code' => 'SUP-'.(++$this->seq), 'name' => 'Kariakoo Spares Ltd', 'is_active' => true]);
    }

    public function test_pages_and_roles(): void
    {
        $this->as(User::ADMIN);
        foreach (['/pos', '/stock', '/purchase-orders', '/purchase-orders/new', '/returns'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->as(User::STAFF);
        $this->get('/pos')->assertOk();
        $this->get('/stock')->assertOk();
        $this->get('/purchase-orders')->assertForbidden();
        $this->get('/returns')->assertForbidden();
        $this->as(User::MECHANIC);
        $this->get('/pos')->assertForbidden();
    }

    public function test_pos_sale_through_the_screen(): void
    {
        $this->as(User::STAFF);
        $filter = $this->part(stock: 5);

        Livewire::test(Pos::class)
            ->call('add', $filter->id)->call('add', $filter->id)
            ->assertSet("cart.{$filter->id}.qty", 2)
            ->call('setQty', (string) $filter->id, 9)              // capped at stock
            ->assertSet("cart.{$filter->id}.qty", 5.0)
            ->call('setQty', (string) $filter->id, 3)
            ->set('walk_in_name', 'Hamisi')
            ->set('discount', '5000')                                // staff cannot discount → ignored
            ->call('checkout')
            ->assertSet('cart', [])
            ->assertDispatched('toast', type: 'success');

        $inv = Invoice::firstOrFail();
        $this->assertSame('counter', $inv->type);
        $this->assertSame('Hamisi', $inv->customerName());
        $this->assertEquals(75000, (float) $inv->grand_total);
        $this->assertSame(Invoice::PAID, $inv->status);
        $this->assertEquals(2, (float) $filter->fresh()->stock_qty);
        $this->assertEquals(45000, (float) $inv->items()->sum('cost'));

        $this->get('/print/sale/'.$inv->id.'?noprint')->assertOk()->assertSee('SALES RECEIPT')->assertSee('75,000');
        $this->get('/print/invoice/'.$inv->id.'?noprint')->assertOk()->assertSee('TAX INVOICE');
    }

    public function test_pos_credit_rules_and_void(): void
    {
        $this->as(User::MANAGER);
        $p = $this->part(stock: 4);
        $sales = app(SalesManager::class);

        try {
            $sales->createCounterSale([['part_id' => $p->id, 'qty' => 1, 'rate' => 25000]], ['paid' => 10000]);
            $this->fail('Credit to walk-in accepted');
        } catch (BusinessRuleException) {
        }
        try {
            $sales->createCounterSale([['part_id' => $p->id, 'qty' => 5, 'rate' => 25000]], ['paid' => 125000]);
            $this->fail('Sold more than stock');
        } catch (BusinessRuleException) {
        }

        $c = $this->customer();
        $inv = $sales->createCounterSale([['part_id' => $p->id, 'qty' => 2, 'rate' => 25000]], ['customer_id' => $c->id, 'paid' => 20000, 'mode' => 'M-Pesa']);
        $this->assertSame(Invoice::PARTIAL, $inv->status);
        $this->assertEquals(30000, $c->balance());

        $sales->voidCounterSale($inv, 'Wrong part');
        $inv->refresh();
        $this->assertSame(Invoice::CANCELLED, $inv->status);
        $this->assertEquals(4, (float) $p->fresh()->stock_qty);
        $this->assertEquals(0, (float) Payment::where('invoice_id', $inv->id)->sum('amount')); // refund recorded
        $this->assertEquals(0, $c->balance());
    }

    public function test_stock_adjustment(): void
    {
        $this->as(User::MANAGER);
        $p = $this->part(stock: 10);

        Livewire::test(Stock::class)
            ->call('openAdjust', $p->id)
            ->set('adjustMode', 'count')->set('adjustQty', '7')->set('adjustReason', 'Stock count')
            ->call('saveAdjust')->assertHasNoErrors();
        $this->assertEquals(7, (float) $p->fresh()->stock_qty);

        Livewire::test(Stock::class)
            ->call('openAdjust', $p->id)
            ->set('adjustMode', 'remove')->set('adjustQty', '20')->set('adjustReason', 'Damaged')
            ->call('saveAdjust')->assertHasErrors('adjustQty');
        $this->assertEquals(7, (float) $p->fresh()->stock_qty);
        $this->assertSame('adjustment', StockMovement::latest('id')->first()->type);

        $this->as(User::STAFF);
        Livewire::test(Stock::class)->call('openAdjust', $p->id)->assertForbidden();
    }

    public function test_purchase_order_flow(): void
    {
        $this->as(User::MANAGER);
        $s = $this->supplier();
        $oil = $this->part(stock: 0, buy: 90000);
        $filter = $this->part(stock: 2, buy: 15000);

        Livewire::test(PoForm::class)
            ->set('supplier_id', $s->id)
            ->call('addPart', $oil->id)->call('addPart', $filter->id)
            ->set('lines.0.qty', '10')->set('lines.0.rate', '95000')
            ->set('lines.1.qty', '6')->set('lines.1.rate', '14000')
            ->set('other_charges', '20000')
            ->call('save', true)
            ->assertRedirect();

        $po = PurchaseOrder::firstOrFail();
        $this->assertSame(PurchaseOrder::ORDERED, $po->status);
        $this->assertEquals(950000 + 84000 + 20000, (float) $po->grand_total);
        $this->assertEquals((float) $po->grand_total, $s->balance());

        $items = $po->items->keyBy('part_id');
        Livewire::test(PoShow::class, ['purchaseOrder' => $po])
            ->call('openReceive')
            ->set('receive.'.$items[$oil->id]->id, '4')
            ->set('receive.'.$items[$filter->id]->id, '6')
            ->set('supplierInvoiceNo', 'KS-889')
            ->call('saveReceive');

        $po->refresh();
        $this->assertSame(PurchaseOrder::PARTIAL, $po->status);
        $this->assertEquals(4, (float) $oil->fresh()->stock_qty);
        $this->assertEquals(95000, (float) $oil->fresh()->purchase_price);   // buying price updated
        $this->assertEquals(8, (float) $filter->fresh()->stock_qty);

        try {
            app(PurchaseManager::class)->receive($po, [$items[$oil->id]->id => 7]);
            $this->fail('Over-received');
        } catch (BusinessRuleException) {
        }
        app(PurchaseManager::class)->receive($po->fresh(), [$items[$oil->id]->id => 6]);
        $this->assertSame(PurchaseOrder::RECEIVED, $po->fresh()->status);

        Livewire::test(PoShow::class, ['purchaseOrder' => $po->fresh()])
            ->call('openPay')->set('payAmount', '500000')->call('savePay')->assertHasNoErrors();
        $this->assertEquals(554000, (float) $po->fresh()->balance);

        $this->get('/print/purchase-order/'.$po->id.'?noprint')->assertOk()->assertSee('PURCHASE ORDER')->assertSee('Kariakoo Spares Ltd');

        try {
            app(PurchaseManager::class)->cancel($po->fresh(), 'test');
            $this->fail('Cancelled a received PO');
        } catch (BusinessRuleException) {
        }
    }

    public function test_customer_return_credits_or_refunds(): void
    {
        $this->as(User::MANAGER);
        $p = $this->part(stock: 10);
        $c = $this->customer();
        $sales = app(SalesManager::class);

        // fully paid sale → refund
        $paidSale = $sales->createCounterSale([['part_id' => $p->id, 'qty' => 4, 'rate' => 25000]], ['customer_id' => $c->id, 'paid' => 100000]);
        Livewire::test(Returns::class)
            ->call('open', 'customer', $paidSale->number)
            ->assertSet('docId', $paidSale->id)
            ->set("qty.{$p->id}", '1')->set('reason', 'Wrong size')->set('refundMode', 'Cash')
            ->call('save')->assertHasNoErrors();

        $paidSale->refresh();
        $this->assertEquals(25000, (float) $paidSale->credited);
        $this->assertEquals(75000, (float) $paidSale->paid);       // 100,000 − 25,000 refund
        $this->assertEquals(0, (float) $paidSale->balance);
        $this->assertEquals(7, (float) $p->fresh()->stock_qty);

        // unpaid sale → credit reduces what is owed, no refund
        $credit = $sales->createCounterSale([['part_id' => $p->id, 'qty' => 2, 'rate' => 25000]], ['customer_id' => $c->id, 'paid' => 0]);
        $ret = app(ReturnManager::class)->customerReturn($credit, [$p->id => 2], 'Not needed');
        $this->assertEquals(0, (float) $ret->refund_amount);
        $this->assertSame(Invoice::PAID, $credit->fresh()->status);
        $this->assertEquals(0, $c->balance());

        // cannot return more than was sold
        $this->expectException(BusinessRuleException::class);
        app(ReturnManager::class)->customerReturn($paidSale->fresh(), [$p->id => 4], 'Again');
    }

    public function test_supplier_return(): void
    {
        $this->as(User::MANAGER);
        $s = $this->supplier();
        $p = $this->part(stock: 0, buy: 10000);
        $pm = app(PurchaseManager::class);
        $po = $pm->saveDraft(null, ['supplier_id' => $s->id, 'date' => today()->toDateString(), 'expected_date' => null], [['part_id' => $p->id, 'qty' => 5, 'rate' => 10000]]);
        $pm->markOrdered($po);
        $pm->receive($po->fresh(), [$po->items->first()->id => 5]);
        $pm->recordPayment($po->fresh(), 50000, 'Cash');

        $ret = app(ReturnManager::class)->supplierReturn($po->fresh(), [$p->id => 2], 'Faulty', 'Cash');
        $this->assertSame('DBN-'.now()->year.'-0001', $ret->number);
        $this->assertEquals(3, (float) $p->fresh()->stock_qty);
        $this->assertEquals(20000, (float) $ret->refund_amount);   // already paid in full → supplier refunds
        $this->assertEquals(0, (float) $po->fresh()->balance);

        $this->get('/print/return/'.$ret->id.'?noprint')->assertOk()->assertSee('DEBIT NOTE');
    }

    public function test_pos_respects_minimum_price(): void
    {
        $this->as(User::MANAGER);
        $p = $this->part(stock: 5);
        $p->update(['min_selling_price' => 22000]);

        Livewire::test(Pos::class)
            ->call('add', $p->id)
            ->call('setRate', (string) $p->id, 18000)
            ->assertSet("cart.{$p->id}.rate", 22000.0)
            ->assertDispatched('toast', type: 'error');

        $this->expectException(BusinessRuleException::class);
        app(SalesManager::class)->createCounterSale([['part_id' => $p->id, 'qty' => 1, 'rate' => 20000]], ['paid' => 20000]);
    }
}
