<?php

namespace Tests\Feature;

use App\Livewire\Orders\Index as StaffOrders;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\ShopCustomer;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Analytics;
use App\Services\SalesManager;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Parts bought from a supplier only when sold: on the website as normal stock, bought from that shop
 * only when sold, and shown apart on the dashboard.
 */
class SupplierPartsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function supplierPart(Supplier $s, float $own = 0): Part
    {
        $p = Part::create(['sku' => 'NB-1', 'name' => 'Shock Absorber Hilux', 'category' => 'Car Parts', 'unit' => 'Pcs',
            'purchase_price' => 60000, 'selling_price' => 100000, 'is_active' => true, 'show_online' => true,
            'sourced' => true, 'source_supplier_id' => $s->id]);
        if ($own) {
            StockMovement::record($p, 'opening', in: $own, unitCost: 60000);
        }

        return $p->refresh();
    }

    public function test_website_treats_it_as_in_stock_and_order_buys_it_from_the_supplier(): void
    {
        $sup = Supplier::create(['code' => 'SUP-9', 'name' => 'Jirani Spares', 'is_active' => true]);
        $p = $this->supplierPart($sup);

        // website: no "out of stock", no hint that it is someone else's
        $this->get(route('shop.product', [$p, $p->slug()]))->assertOk()->assertDontSee('Out of stock')->assertDontSee('Jirani');
        $this->get(route('shop.catalog', ['instock' => 1]))->assertSee('Shock Absorber Hilux');

        // stock value / out-of-stock counts ignore it
        $this->assertSame(0, Part::lowStock()->count());

        $c = Customer::create(['code' => 'CUS-1', 'name' => 'Asha', 'phone' => '0754000111', 'type' => 'Individual']);
        $me = ShopCustomer::create(['customer_id' => $c->id, 'name' => 'Asha', 'phone' => '0754000111', 'password' => 'secret123']);
        $this->actingAs($me, 'shop');
        $this->assertSame(2, Cart::add($p->id, 2));
        $order = app(\App\Services\OrderManager::class)->place($me, ['fulfilment' => 'pickup', 'contact_phone' => $me->phone, 'payment_method' => 'on_pickup']);

        $this->actingAs(User::factory()->role(User::MANAGER)->create(), 'web');
        Livewire::test(StaffOrders::class)->call('open', $order->id)->set('paid', '200000')->set('mode', 'Cash')->call('confirm')->assertHasNoErrors();
        $this->assertSame(Order::CONFIRMED, $order->fresh()->status);

        // bought 2 from the supplier (received PO, owed 120,000), then sold: shelf back to 0
        $po = PurchaseOrder::where('supplier_id', $sup->id)->firstOrFail();
        $this->assertSame(PurchaseOrder::RECEIVED, $po->status);
        $this->assertEquals(120000, (float) $po->balance);
        $this->assertStringContainsString($order->fresh()->invoice->number, $po->notes);
        $this->assertEquals(0, (float) $p->fresh()->stock_qty);
        $this->assertEquals(2, (float) $order->fresh()->invoice->items->first()->sourced_qty);
        $this->assertTrue($p->fresh()->sourced);                               // buying for a sale keeps the tag

        // dashboard: 200,000 sold from suppliers, 80,000 profit; nothing from our own stock
        $sum = app(Analytics::class)->summary(today()->toDateString(), today()->toDateString());
        $this->assertEquals(200000, $sum['supply']['suppliers']['sales']);
        $this->assertEquals(80000, $sum['supply']['suppliers']['profit']);
        $this->assertEquals(0, $sum['supply']['own']['sales']);
        $this->assertSame('Jirani Spares', $sum['supply']['bySupplier'][0]['name']);
        $this->assertEquals(200000, $sum['supply']['bySupplier'][0]['sales']);
        $this->get(route('dashboard'))->assertOk()->assertSee('Our stock vs suppliers');
    }

    public function test_pos_sells_own_stock_first_then_buys_the_rest(): void
    {
        $sup = Supplier::create(['code' => 'SUP-8', 'name' => 'Jirani Spares', 'is_active' => true]);
        $p = $this->supplierPart($sup, own: 1);
        $this->actingAs(User::factory()->role(User::STAFF)->create());

        $inv = app(SalesManager::class)->createCounterSale([['part_id' => $p->id, 'qty' => 3, 'rate' => 100000]], ['paid' => 300000, 'mode' => 'Cash']);

        $this->assertEquals(2, (float) $inv->items->first()->sourced_qty);           // 1 from our shelf, 2 bought
        $this->assertEquals(2, (float) PurchaseOrder::firstOrFail()->items->first()->qty);
        $this->assertEquals(0, (float) $p->fresh()->stock_qty);

        $sum = app(Analytics::class)->summary(today()->toDateString(), today()->toDateString());
        $this->assertEquals(100000, $sum['supply']['own']['sales']);
        $this->assertEquals(200000, $sum['supply']['suppliers']['sales']);
    }

    public function test_normal_part_still_refuses_without_stock(): void
    {
        $this->actingAs(User::factory()->role(User::STAFF)->create());
        $p = Part::create(['sku' => 'N-1', 'name' => 'Normal Part', 'unit' => 'Pcs', 'purchase_price' => 1, 'selling_price' => 2, 'is_active' => true]);
        $this->expectException(\App\Exceptions\BusinessRuleException::class);
        app(SalesManager::class)->createCounterSale([['part_id' => $p->id, 'qty' => 1, 'rate' => 2]], ['paid' => 2, 'mode' => 'Cash']);
    }

    public function test_parts_form_saves_the_supplier(): void
    {
        $sup = Supplier::create(['code' => 'SUP-7', 'name' => 'Jirani Spares', 'is_active' => true]);
        $this->actingAs(User::factory()->role(User::MANAGER)->create());
        Livewire::test(\App\Livewire\Parts\Index::class)->call('create')
            ->set('form.name', 'Radiator Cap')->set('form.category', 'Car Parts')->set('form.purchase_price', '5000')->set('form.selling_price', '9000')
            ->set('form.sourced', true)->call('save')->assertHasErrors('form.source_supplier_id')
            ->set('form.source_supplier_id', (string) $sup->id)->call('save')->assertHasNoErrors();
        $p = Part::where('name', 'Radiator Cap')->firstOrFail();
        $this->assertTrue($p->sourced);
        $this->assertSame($sup->id, $p->source_supplier_id);
        $this->get(route('parts.index', ['stock' => 'sourced']))->assertSee('Radiator Cap')->assertSee('Jirani Spares');
    }

    public function test_no_opening_stock_and_manual_purchase_removes_the_tag(): void
    {
        $sup = Supplier::create(['code' => 'SUP-6', 'name' => 'Jirani Spares', 'is_active' => true]);
        $this->actingAs(User::factory()->role(User::MANAGER)->create());
        Livewire::test(\App\Livewire\Parts\Index::class)->call('create')
            ->set('form.name', 'CV Joint')->set('form.category', 'Car Parts')->set('form.purchase_price', '20000')->set('form.selling_price', '35000')
            ->set('form.sourced', true)->set('form.source_supplier_id', (string) $sup->id)->set('opening_qty', '10')->call('save')->assertHasNoErrors();
        $p = Part::where('name', 'CV Joint')->firstOrFail();
        $this->assertEquals(0, (float) $p->stock_qty);                       // no stock added

        // buy for the shelf ourselves → it becomes our own stock
        $m = app(\App\Services\PurchaseManager::class);
        $po = $m->saveDraft(null, ['supplier_id' => $sup->id, 'date' => today()->toDateString(), 'expected_date' => null, 'notes' => null], [['part_id' => $p->id, 'qty' => 3, 'rate' => 20000]]);
        $m->markOrdered($po);
        $m->receive($po, $po->items()->pluck('qty', 'id')->all());
        $p->refresh();
        $this->assertFalse($p->sourced);
        $this->assertEquals(3, (float) $p->stock_qty);
    }
}
