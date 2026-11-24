<?php

namespace Tests\Feature;

use App\Livewire\Imports\Index as ImportOrders;
use App\Livewire\Parts\Index as PartsIndex;
use App\Livewire\Shop\Catalog;
use App\Livewire\Shop\ImportOrder;
use App\Models\ImportRequest;
use App\Models\Part;
use App\Models\ShopCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Oil pack sizes, specification filters, the tyre finder, and "Import for you". */
class TyresOilImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed();
        $this->seed(\Database\Seeders\DemoShopSeeder::class);
    }

    public function test_oil_sizes_share_one_row(): void
    {
        $main = Part::where('name', 'Quartz 9000 5W-40 (4L)')->firstOrFail();
        $one = Part::where('name', 'Quartz 9000 5W-40 (1L)')->firstOrFail();
        $this->assertSame($main->id, $one->variant_of);
        $this->assertSame(1.0, $one->litres());
        $this->assertSame('4L', $main->packLabel());
        $this->assertEquals(round($main->salePrice() / 4), $main->pricePerLitre());
        $this->assertSame(['1L', '4L', '5L'], $main->family()->map->packLabel()->all());

        // one row for the product, with the three sizes as buttons
        $page = Livewire::test(Catalog::class)->set('category', 'Lubricants')->call('setSpec', '5W-40');
        $page->assertViewHas('parts', fn ($p) => $p->getCollection()->pluck('id')->all() === [$main->id])
            ->assertSee('Quartz 9000 5W-40 (4L)')->assertSee('Pack size', false)
            ->assertSee('>1L</button>', false)->assertDontSee('per litre');

        // filtering by capacity lists that size on its own
        Livewire::test(Catalog::class)->set('category', 'Lubricants')->call('toggleFacet', 'Capacity', '1 L')
            ->assertViewHas('parts', fn ($p) => $p->getCollection()->contains('id', $one->id) && ! $p->getCollection()->contains('id', $main->id));

        // sizes can be bought from the row and the product page offers the other sizes
        Livewire::test(Catalog::class)->call('addToCart', $one->id, 2);
        $this->assertSame(2, \App\Support\Cart::raw()[$one->id]);
        $this->get(route('shop.product', [$main, $main->slug()]))->assertOk()->assertSee('Pack size')->assertDontSee('per litre')->assertSee('5L');
    }

    public function test_spec_filters_split_multi_value_specs(): void
    {
        $page = Livewire::test(Catalog::class)->set('category', 'Lubricants');
        $page->assertSee('Manufacturer approvals')->assertSee('MB 229.5')->assertSee('Capacity (litres)');
        $page->call('toggleFacet', 'Manufacturer approvals', 'MB 229.3')
            ->assertSee('Magnatec 10W-40')->assertSee('Super Leichtlauf')->assertDontSee('Quartz 9000')
            ->assertSee('Selected filters')->call('drop', 'facet', 'Manufacturer approvals|MB 229.3')->assertSee('Quartz 9000');
    }

    public function test_tyre_finder_by_size_tab_season_and_car(): void
    {
        $this->assertSame(['w' => '205', 'h' => '55', 'r' => '16', 'c' => ''], Part::tyreSize('205/55 R16 91V'));
        $this->assertSame('C', Part::tyreSize('215/70R15C')['c']);
        $this->assertSame('91 = up to 615 kg', Part::specDisplay('Load index', '91 (615 kg)'));
        $this->assertSame('Summer tyres 205/55 R16 91V', Part::where('name', 'Primacy 4 205/55 R16')->first()->tyreLine());

        $page = Livewire::test(Catalog::class)->set('category', 'Tyres');
        $page->assertSee('Find your tyres')->assertSee('Passenger car')->assertSee('4x4 / SUV')->assertSee('All season');

        // width → only heights and rims we have for it
        $page->set('tw', '205')->assertSee('Primacy 4')->assertSee('Ventus Prime 4')->assertDontSee('Dueler A/T')
            ->assertViewHas('tyre', fn ($t) => $t['heights']->all() === ['55'] && $t['rims']->all() === ['16'])
            ->assertSee('Size 205/… R…')->assertSee('91 = up to 615 kg');
        $page->set('tw', '265')->set('th', '65')->set('tr', '17')->assertSee('Dueler A/T')->assertSee('Grandtrek AT5')->assertDontSee('KO2');

        Livewire::test(Catalog::class)->set('category', 'Tyres')->call('setVehicleType', 'car')->assertSee('Primacy 4')->assertDontSee('Dueler A/T');
        Livewire::test(Catalog::class)->set('category', 'Tyres')->call('toggleFacet', 'Season', 'All season')
            ->assertSee('Dueler A/T')->assertSee('Wrangler')->assertDontSee('Primacy 4');
        Livewire::test(Catalog::class)->set('category', 'Tyres')->set('make', 'Toyota')->set('model', 'Hiace')->assertSee('Wrangler')->assertDontSee('Primacy 4');
        $this->get('/shop?category=Tyres&tw=195&th=65&tr=15')->assertOk()->assertSee('SP Touring R1')->assertSee('4 × ');
    }

    public function test_oil_page_shows_chosen_manufacturers_first_and_price_follows_quantity(): void
    {
        Part::create(['sku' => 'X-MOB', 'name' => 'Mobil Oil X', 'brand' => 'Mobil', 'category' => 'Lubricants', 'unit' => 'Pcs',
            'purchase_price' => 1, 'selling_price' => 10, 'is_active' => true, 'show_online' => true]);
        $page = Livewire::test(Catalog::class)->set('category', 'Lubricants');
        $page->assertViewHas('brandStrip', fn ($b) => $b->keys()->all() === ['Atlantic Oil', 'Liqui Moly', 'Total', 'Castrol', 'Motul', 'Puma', 'Toyota Genuine'])
            ->assertViewHas('brandList', fn ($b) => $b->has('Mobil'));   // other brands stay in the sidebar filter
        $html = $page->html();
        $this->assertLessThan(strpos($html, 'SAE viscosity grade'), strpos($html, 'Manufacturers'));
        $this->assertStringNotContainsString('per litre', $html);
        $this->assertStringNotContainsString('collect today', $html);
        $this->assertStringContainsString('tzs(it.p * qty)', $html);

        // the manufacturer list is edited in Website texts
        \App\Support\Settings::set(['web_oil_brands' => "Castrol\nTotal"]);
        Livewire::test(Catalog::class)->set('category', 'Lubricants')->assertViewHas('brandStrip', fn ($b) => $b->keys()->all() === ['Castrol', 'Total']);
    }

    public function test_buy_now_stock_badge_and_order_progress(): void
    {
        $oil = Part::where('name', 'Quartz 9000 5W-40 (4L)')->firstOrFail();
        Part::where('name', 'Engine Oil 20W-50 (5L)')->update(['stock_qty' => 0]);

        // only "Out of stock" is shown; the buttons are a cart icon and "Buy now"
        $html = Livewire::test(Catalog::class)->set('category', 'Lubricants')->html();
        $this->assertStringNotContainsString('In stock', str_replace('In stock only', '', $html));
        $this->assertStringContainsString('Out of stock', $html);
        $this->assertStringContainsString('Buy now', $html);
        $this->assertStringNotContainsString('> Add to cart', $html);

        // buy now: into the cart (keeping what is there) and on to checkout, which asks a guest to log in first
        Livewire::test(Catalog::class)->call('addToCart', $oil->id, 3)->call('buyNow', $oil->id, 2)->assertRedirect(route('shop.checkout'));
        $this->assertSame(3, \App\Support\Cart::raw()[$oil->id]);
        Livewire::test(Catalog::class)->call('buyNow', Part::where('name', 'Engine Oil 20W-50 (5L)')->value('id'))->assertNoRedirect();
        $this->get(route('shop.checkout'))->assertRedirect(route('shop.login'));

        // the progress bar: cart is step 1, login from checkout step 2, checkout step 3
        $this->get(route('shop.cart'))->assertSee('Order progress', false)->assertSee('Delivery &amp; payment', false);
        $this->get(route('shop.login'))->assertSee('Order progress', false);
        $customer = ShopCustomer::create(['name' => 'Asha', 'phone' => '0754111000', 'password' => 'secret123']);
        $this->actingAs($customer, 'shop')->get(route('shop.checkout'))->assertOk()->assertSee('aria-current="step"', false);
    }

    public function test_staff_link_another_size_in_the_parts_form(): void
    {
        $this->actingAs(User::factory()->role(User::MANAGER)->create(), 'web');
        $main = Part::where('name', 'Engine Oil 20W-50 (5L)')->firstOrFail();
        Livewire::test(PartsIndex::class)->call('create')
            ->set('form.name', 'Engine Oil 20W-50 (1L)')->set('form.category', 'Engine Oil')->set('form.selling_price', '22000')
            ->set('form.specs_text', "Viscosity: 20W-50\nCapacity: 1 L")->set('form.variant_of', (string) $main->id)
            ->call('save')->assertHasNoErrors();
        $one = Part::where('name', 'Engine Oil 20W-50 (1L)')->firstOrFail();
        $this->assertSame($main->id, $one->variant_of);

        // a size of a size points at the main part; a main part with sizes cannot become a size
        Livewire::test(PartsIndex::class)->call('create')->set('form.name', 'Engine Oil 20W-50 (4L)')->set('form.selling_price', '90000')
            ->set('form.variant_of', (string) $one->id)->call('save')->assertHasNoErrors();
        $this->assertSame($main->id, Part::where('name', 'Engine Oil 20W-50 (4L)')->value('variant_of'));
        $other = Part::where('name', 'Quartz 9000 5W-40 (4L)')->first();
        Livewire::test(PartsIndex::class)->call('edit', $main->id)->set('form.variant_of', (string) $other->id)->call('save')->assertHasErrors('form.variant_of');
    }

    public function test_import_for_you_request_quote_and_customer_follow_up(): void
    {
        RateLimiter::clear('import-request:127.0.0.1');
        $this->get('/')->assertOk()->assertSee('Import for you');
        $this->get(route('shop.import', ['item' => 'Engine']))->assertOk()->assertSee('Where we import from')->assertSee('Japan')->assertSee('How importing works');

        $customer = ShopCustomer::create(['name' => 'Juma Ally', 'phone' => '0754999888', 'password' => 'secret123']);
        $this->actingAs($customer, 'shop');
        Livewire::test(ImportOrder::class)->assertSet('name', 'Juma Ally')->assertSet('phone', '0754999888')
            ->call('send')->assertHasErrors(['item_type', 'description', 'make', 'model'])
            ->set('item_type', 'Engine')->set('description', '1KD-FTV engine complete with turbo')->set('make', 'Toyota')->set('model', 'Prado')
            ->set('engine_code', '1KD-FTV')->set('source_country', 'Japan')->set('condition', 'Used (tested, ex-Japan / UK)')->set('budget', '6,500,000')
            ->call('send')->assertHasNoErrors()->assertSet('sentRef', 'IMP-0001')->assertSee('Request IMP-0001 received');

        $r = ImportRequest::firstOrFail();
        $this->assertSame($customer->id, $r->shop_customer_id);
        $this->assertEquals(6500000, $r->budget);
        $this->get(route('shop.account'))->assertOk()->assertSee('IMP-0001')->assertSee('We are looking for it');

        // staff send a price, record the deposit and the arrival date
        $this->actingAs(User::factory()->role(User::STAFF)->create(), 'web');
        $this->get(route('imports.index'))->assertOk()->assertSee('IMP-0001')->assertSee('1KD-FTV');
        Livewire::test(ImportOrders::class)->call('open', $r->id)->set('form.status', 'quoted')->call('save')->assertHasErrors('form.quote_amount')
            ->set('form.quote_amount', '7200000')->set('form.deposit_paid', '8000000')->call('save')->assertHasErrors('form.deposit_paid')
            ->set('form.deposit_paid', '3600000')->set('form.status', 'shipping')->set('form.eta', '2027-01-10')
            ->set('form.customer_message', 'Engine found in Yokohama, 98,000 km, tested.')->set('form.staff_note', 'Supplier cost USD 1,900')
            ->call('save')->assertHasNoErrors();
        $r->refresh();
        $this->assertSame('shipping', $r->status);
        $this->assertEquals(3600000, $r->balance());

        $this->actingAs($customer, 'shop');
        $this->get(route('shop.account'))->assertSee('On the way')->assertSee('Engine found in Yokohama')->assertSee('7,200,000')
            ->assertSee('10 Jan 2027')->assertDontSee('Supplier cost');
    }

    public function test_oil_department_is_called_lubricants_and_old_links_still_work(): void
    {
        $this->assertContains('Lubricants', Part::DEPARTMENTS);
        $this->assertNotContains('Engine Oil', Part::DEPARTMENTS);
        Livewire::test(Catalog::class, ['category' => 'Engine Oil'])->assertSet('category', 'Lubricants');

        // the rename migration moves existing parts, the list and banner links
        Part::create(['sku' => 'OLD-OIL', 'name' => 'Old Oil', 'category' => 'Engine Oil', 'unit' => 'Pcs', 'purchase_price' => 1, 'selling_price' => 2, 'is_active' => true]);
        \App\Models\ListOption::where('list', 'part_category')->where('value', 'Lubricants')->delete();
        \App\Models\ListOption::create(['list' => 'part_category', 'value' => 'Engine Oil', 'sort_order' => 3]);
        \App\Models\SiteBanner::create(['layout' => 'text', 'title' => 'Oil', 'tone' => 'red', 'link_url' => '/shop?category=Engine+Oil', 'button_label' => 'Shop engine oil', 'is_active' => true]);
        (require database_path('migrations/2026_11_24_001300_rename_engine_oil_category_to_lubricants.php'))->up();
        $this->assertSame('Lubricants', Part::where('sku', 'OLD-OIL')->value('category'));
        $this->assertTrue(\App\Models\ListOption::where('list', 'part_category')->where('value', 'Lubricants')->exists());
        $this->assertFalse(\App\Models\ListOption::where('list', 'part_category')->where('value', 'Engine Oil')->exists());
        $this->assertSame('/shop?category=Lubricants', \App\Models\SiteBanner::where('title', 'Oil')->value('link_url'));
    }
}
