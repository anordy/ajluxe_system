<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Orders\Index as StaffOrders;
use App\Livewire\Shop\Auth\Login as ShopLogin;
use App\Livewire\Shop\Auth\Register;
use App\Livewire\Shop\CartPage;
use App\Livewire\Shop\Catalog;
use App\Livewire\Shop\Checkout;
use App\Livewire\Shop\OrderShow;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Part;
use App\Models\ShopCustomer;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Online shop: website pages, customer accounts, cart, checkout and staff handling of orders.
 */
class ShopTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function part(array $attrs = [], float $stock = 10): Part
    {
        $p = Part::create($attrs + ['sku' => 'PRT-'.(++$this->seq), 'name' => 'Brake Pads '.$this->seq, 'unit' => 'Pcs',
            'purchase_price' => 40000, 'selling_price' => 70000, 'is_active' => true, 'category' => 'Brakes']);
        if ($stock) {
            StockMovement::record($p, 'opening', in: $stock, unitCost: 40000);
        }

        return $p->refresh();
    }

    private function customer(): ShopCustomer
    {
        $c = Customer::create(['code' => 'CUS-'.(++$this->seq), 'name' => 'Neema Joseph', 'phone' => '0754'.random_int(100000, 999999), 'type' => 'Individual']);

        return ShopCustomer::create(['customer_id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'password' => 'secret123']);
    }

    public function test_public_pages_show_only_website_parts_with_discounts(): void
    {
        $deal = $this->part(['name' => 'Oil Filter Deal', 'discount_percent' => 20]);           // 70,000 → 56,000
        $floor = $this->part(['name' => 'Floor Price Part', 'discount_percent' => 50, 'min_selling_price' => 60000]); // floored at 60,000
        $hidden = $this->part(['name' => 'Hidden Part', 'show_online' => false]);

        $this->assertEquals(56000, $deal->salePrice());
        $this->assertSame(20, $deal->discountShown());
        $this->assertEquals(60000, $floor->salePrice());   // never below the minimum selling price

        \App\Models\SiteBanner::query()->delete(); // no banners set up: the website makes offers from stock
        $this->get('/')->assertOk()->assertSee('Find parts for your car')->assertSee('Oil Filter Deal')->assertSee('Save 20% on Oil Filter Deal', false)
            ->assertDontSee('Hidden Part')->assertSee('Sign up')->assertSee('Log in')->assertDontSee('40,000'); // never the buying price
        $this->get('/shop?q=Hidden')->assertOk()->assertDontSee('Hidden Part');
        $this->get(route('shop.product', [$deal, $deal->slug()]))->assertOk()->assertSee('56,000');
        $this->get(route('shop.product', [$hidden, 'x']))->assertNotFound();

        Livewire::test(Catalog::class)->set('deals', true)->assertSee('Oil Filter Deal')->assertDontSee('Hidden Part');
    }

    public function test_cart_caps_quantity_at_stock(): void
    {
        $p = $this->part([], stock: 3);
        Livewire::test(Catalog::class)->call('addToCart', $p->id, 5)->assertDispatched('cart-updated');
        $this->assertSame(3, Cart::raw()[$p->id]);

        $out = $this->part([], stock: 0);
        $this->assertSame(0, Cart::add($out->id));

        // stock drops after adding → the cart lowers the quantity
        StockMovement::record($p, 'adjustment', out: 2);
        Livewire::test(CartPage::class)->assertSee('Go to checkout');
        $this->assertSame(1, Cart::raw()[$p->id]);
    }

    public function test_sign_up_links_existing_customer_and_logs_in_by_phone(): void
    {
        $existing = Customer::create(['code' => 'CUS-9001', 'name' => 'Asha', 'phone' => '0754111222', 'type' => 'Individual']);

        Livewire::test(Register::class)
            ->set('name', 'Asha Mollel')->set('phone', '+255 754 111 222')->set('password', 'secret123')->set('password_confirmation', 'secret123')
            ->call('register')->assertHasNoErrors()->assertRedirect(route('shop.account'));

        $acc = ShopCustomer::firstOrFail();
        $this->assertSame('0754111222', $acc->phone);
        $this->assertSame($existing->id, $acc->customer_id);
        $this->assertTrue(auth('shop')->check());
        $this->assertFalse(auth('web')->check());   // never a staff login

        auth('shop')->logout();
        Livewire::test(ShopLogin::class)->set('login', '754111222')->set('password', 'wrong')->call('signIn')->assertHasErrors('login');
        Livewire::test(ShopLogin::class)->set('login', '0754 111 222')->set('password', 'secret123')->call('signIn')->assertHasNoErrors();
        $this->assertTrue(auth('shop')->check());

        // duplicate phone refused
        auth('shop')->logout();
        Livewire::test(Register::class)->set('name', 'X')->set('phone', '0754111222')->set('password', 'secret123')->set('password_confirmation', 'secret123')
            ->call('register')->assertHasErrors('phone');
    }

    public function test_website_customer_cannot_open_the_management_system(): void
    {
        $this->actingAs($this->customer(), 'shop');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/orders')->assertRedirect('/login');
        $this->get('/account')->assertOk();
    }

    public function test_checkout_requires_login_and_places_order_without_taking_stock(): void
    {
        $p = $this->part(['discount_percent' => 10]); // 63,000
        Cart::add($p->id, 2);
        $this->get('/checkout')->assertRedirect(route('shop.login'));

        $me = $this->customer();
        $this->actingAs($me, 'shop');
        Livewire::test(Checkout::class)
            ->set('fulfilment', 'delivery')->call('place')->assertHasErrors('delivery_address')
            ->set('delivery_address', 'Sinza Mori, near the market')->set('payment_method', 'lipa_namba')->set('payment_reference', 'qkr7ab')
            ->call('place')->assertHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame('ORD-'.now()->year.'-0001', $order->number);
        $this->assertSame(Order::PENDING, $order->status);
        $this->assertEquals(126000, (float) $order->total);
        $this->assertEquals(14000, (float) $order->discount);
        $this->assertSame('QKR7AB', $order->payment_reference);
        $this->assertSame(0, Cart::count());
        $this->assertEquals(10, (float) $p->fresh()->stock_qty);  // not taken until staff confirm

        $this->get(route('shop.order', $order))->assertOk()->assertSee($order->number);
        // another customer cannot see it
        $this->actingAs($this->customer(), 'shop');
        $this->get(route('shop.order', $order))->assertNotFound();
    }

    public function test_staff_confirm_ready_complete_and_cancel(): void
    {
        $p = $this->part([], stock: 5);
        $me = $this->customer();
        $this->actingAs($me, 'shop');
        Cart::add($p->id, 2);
        $order = app(\App\Services\OrderManager::class)->place($me, ['fulfilment' => 'pickup', 'contact_phone' => $me->phone, 'payment_method' => 'on_pickup']);

        $staff = User::factory()->role(User::STAFF)->create();
        $this->actingAs($staff, 'web');
        $this->get('/orders')->assertOk()->assertSee($order->number);

        Livewire::test(StaffOrders::class)->call('open', $order->id)->set('paid', '50000')->set('mode', 'Cash')->call('confirm')->assertHasNoErrors();
        $order->refresh();
        $this->assertSame(Order::CONFIRMED, $order->status);
        $this->assertEquals(3, (float) $p->fresh()->stock_qty);
        $inv = $order->invoice;
        $this->assertSame('counter', $inv->type);
        $this->assertSame($me->customer_id, $inv->customer_id);
        $this->assertEquals(140000, (float) $inv->grand_total);
        $this->assertEquals(90000, (float) $inv->balance);

        // customer can no longer cancel; staff can, which voids the sale and returns stock
        $this->actingAs($me, 'shop');
        Livewire::test(OrderShow::class, ['order' => $order])->call('cancel')->assertDispatched('toast', type: 'error');
        $this->actingAs($staff, 'web');
        Livewire::test(StaffOrders::class)->call('open', $order->id)->call('ready')->assertHasNoErrors();
        $this->assertSame(Order::READY, $order->fresh()->status);
        Livewire::test(StaffOrders::class)->call('open', $order->id)->set('cancelReason', 'Customer did not collect')->call('cancel')->assertHasNoErrors();
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(Invoice::CANCELLED, $inv->fresh()->status);
        $this->assertEquals(5, (float) $p->fresh()->stock_qty);
    }

    public function test_customer_cancels_pending_and_stock_is_checked_at_confirm(): void
    {
        $p = $this->part([], stock: 2);
        $me = $this->customer();
        $this->actingAs($me, 'shop');
        Cart::add($p->id, 2);
        $m = app(\App\Services\OrderManager::class);
        $order = $m->place($me, ['fulfilment' => 'pickup', 'contact_phone' => $me->phone, 'payment_method' => 'on_pickup']);

        // someone bought them at the counter meanwhile → confirm is refused, nothing changes
        StockMovement::record($p, 'adjustment', out: 1);
        $this->actingAs(User::factory()->role(User::MANAGER)->create(), 'web');
        try {
            $m->confirm($order);
            $this->fail('Confirmed without stock');
        } catch (BusinessRuleException) {
        }
        $this->assertSame(Order::PENDING, $order->fresh()->status);

        $this->actingAs($me, 'shop');
        Livewire::test(OrderShow::class, ['order' => $order])->call('cancel')->assertDispatched('toast', type: 'success');
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
    }

    public function test_tariff_seeder_and_garage_page_without_prices(): void
    {
        $this->seed(\Database\Seeders\GarageTariffSeeder::class);
        $this->seed(\Database\Seeders\GarageTariffSeeder::class); // safe to run twice
        $this->assertSame(49, \App\Models\Service::where('code', 'like', 'PNT-%')->count());
        $this->assertEquals(25000, \App\Models\Service::where('code', 'PNT-FB-SE')->value('rate'));
        $this->assertEquals(100000, \App\Models\Service::where('code', 'PNT-FULL-SE')->value('rate'));

        $t = \App\Support\Tariff::load();
        $this->assertCount(8, $t['parts']);
        $this->assertEquals(10000, $t['from']);
        $this->assertEquals(30000, $t['jobs']['J24']['WI']);

        // the website shows what the garage does and booking, never prices (the tariff stays for staff job cards)
        $this->get('/garage')->assertOk()->assertSee('What we do')->assertSee('Book your car in')->assertSee('Auto electrical')
            ->assertDontSee('Paint price list')->assertDontSee('TZS')->assertDontSee('17000', false);
        \App\Models\SiteBanner::query()->delete();
        $this->get('/')->assertOk()->assertSee('Painting, panel beating and repairs')->assertDontSee('Spray booth painting from');
    }

    public function test_demo_stock_can_be_seeded_filtered_and_removed(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public'); // never touch the real picture folder
        $this->seed(\Database\Seeders\DemoShopSeeder::class);
        $demo = Part::where('sku', 'like', 'DEMO-%');
        $this->assertSame(58, (clone $demo)->count());
        $oil = Part::where('name', 'like', 'Quartz 9000%')->first();
        $this->assertSame('5W-40', $oil->specs['Viscosity']);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists($oil->image_path));

        // department, type, quick spec filter and brand filters
        Livewire::test(Catalog::class)->set('category', 'Lubricants')->assertSee('SAE viscosity grade')
            ->call('setSpec', '10W-40')->assertSee('Magnatec 10W-40')->assertSee('Super Leichtlauf')->assertDontSee('Quartz 9000');
        Livewire::test(Catalog::class)->set('category', 'Tyres')->call('setType', 'All-terrain')->assertSee('Dueler A/T')->assertDontSee('Primacy 4');
        Livewire::test(Catalog::class)->set('category', 'Filters')->set('brands', ['Bosch'])->assertSee('P3311')->assertDontSee('YZZE1');
        $this->get('/shop?make=Toyota&model=Prado')->assertOk()->assertSee('Air Filter C 26 003');
        $this->get(route('shop.product', [$oil, $oil->slug()]))->assertOk()->assertSee('Viscosity')->assertSee('Fully synthetic');

        // a demo part used in a test order is hidden, the rest deleted; real parts untouched
        $real = $this->part(['name' => 'My Real Part']);
        $used = (clone $demo)->first();
        $me = $this->customer();
        $this->actingAs($me, 'shop');
        Cart::add($used->id);
        app(\App\Services\OrderManager::class)->place($me, ['fulfilment' => 'pickup', 'contact_phone' => $me->phone, 'payment_method' => 'on_pickup']);

        $this->artisan('demo:remove', ['--force' => true])->assertSuccessful();
        $this->assertSame(1, Part::where('sku', 'like', 'DEMO-%')->count());
        $this->assertFalse((bool) $used->fresh()->show_online);
        $this->assertTrue(Part::whereKey($real->id)->exists());
        $this->assertEquals(0, \App\Models\Service::where('name', 'Wheel Alignment')->value('rate'));
    }

    public function test_catalog_brands_fitting_position_chips_and_reseed(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->seed(\Database\Seeders\DemoShopSeeder::class);

        // brand tiles toggle the brand filter; the rich spec table shows on each row
        Livewire::test(Catalog::class)->set('category', 'Car Parts')->call('setType', 'Spark Plugs')
            ->assertSee('Spark Plugs by brand')->assertSee('M 14 × 1.25, Spanner size: 16 mm')->assertSee('Electrode gap')
            ->call('toggleBrand', 'NGK')->assertSee('IFR6T11')->assertDontSee('Iridium Power')
            ->assertSee('Selected filters')->call('drop', 'brand', 'NGK')->assertSee('Iridium Power')
            ->call('toggleBrand', 'Denso')->call('resetFilters')->assertSet('brands', [])->assertSet('category', 'Car Parts');

        // fitting position is not shown on the website for now; a link with ?position= still narrows the list
        Livewire::test(Catalog::class)->set('category', 'Car Parts')->call('setType', 'Shock Absorbers')
            ->assertDontSee('Fitting position')->call('setPosition', 'Front left')
            ->assertSee('339193')->assertDontSee('339192')->assertDontSee('G8098')
            ->call('setPosition', 'Front left')->assertSet('position', '')->assertSee('G8098');
        $this->get('/shop?category=Car+Parts&position=Rear+axle')->assertOk()->assertSee('GDB3544')->assertDontSee('P83 152');

        // the header bar has the departments, so the catalogue page doesn't repeat them as buttons
        Livewire::test(Catalog::class)->set('category', 'Car Accessories')->assertDontSee('wire:click="setCategory', false);

        // seeding again refreshes details (renamed first-set parts too) without adding copies or touching stock
        $plug = Part::where('name', 'Laser Iridium Spark Plug IFR6T11')->first();
        $plug->update(['name' => 'Iridium Spark Plugs (Set of 4)', 'specs' => ['Old' => 'x']]);
        $before = $plug->fresh()->stock_qty;
        $this->seed(\Database\Seeders\DemoShopSeeder::class);
        $this->assertSame(58, Part::where('sku', 'like', 'DEMO-%')->count());
        $plug->refresh();
        $this->assertSame('Laser Iridium Spark Plug IFR6T11', $plug->name);
        $this->assertSame('16 mm', $plug->specs['Spanner size']);
        $this->assertEquals($before, $plug->stock_qty);
        $this->get(route('shop.product', [$plug, $plug->slug()]))->assertOk()->assertSee('IFR6T11')->assertSee('Thread size');
    }

    public function test_specs_text_round_trip(): void
    {
        $specs = Part::parseSpecs("Size: 265/65 R17\nLoad/speed: 112S\nnot a spec line\nEmpty:  ");
        $this->assertSame(['Size' => '265/65 R17', 'Load/speed' => '112S'], $specs);
        $p = $this->part(['specs' => $specs]);
        $this->assertSame("Size: 265/65 R17\nLoad/speed: 112S", $p->specsText());
    }
}
