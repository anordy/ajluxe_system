<?php

namespace Tests\Feature;

use App\Livewire\Parts\Index as PartsIndex;
use App\Livewire\Shop\Catalog;
use App\Livewire\Website\Cars;
use App\Models\CarEngine;
use App\Models\CarModel;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Vehicle database (make → model → engine), linking parts to cars, and the website using those links. */
class VehicleMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed();
    }

    public function test_starter_engines_and_engine_lines(): void
    {
        $hilux = CarModel::where('make', 'Toyota')->where('model', 'Hilux')->firstOrFail();
        $codes = $hilux->engines->pluck('code')->all();
        $this->assertContains('1KD-FTV', $codes);
        $this->assertSame('3.0 Diesel (1KD-FTV)', $hilux->engines->firstWhere('code', '1KD-FTV')->label());

        $e = CarEngine::parseLine('2.8 Diesel | 1gd-ftv | 2015-');
        $this->assertSame(['litres' => 2.8, 'fuel' => 'Diesel', 'code' => '1GD-FTV', 'year_from' => 2015, 'year_to' => null], $e);
        $this->assertSame('Hybrid', CarEngine::parseLine('1.5 H | LEB')['fuel']);
        $this->assertSame('Petrol', CarEngine::parseLine('1.3 | 2NZ-FE')['fuel']);
    }

    public function test_car_list_engines_editor_and_csv_import(): void
    {
        $this->actingAs(User::factory()->role(User::MANAGER)->create(), 'web');
        $ist = CarModel::where('model', 'IST')->firstOrFail();
        Livewire::test(Cars::class)->call('edit', $ist->id)->assertSet('form.engines', fn ($t) => str_contains($t, '2NZ-FE'))
            ->set('form.engines', "1.5 Petrol | 1NZ-FE | 2002-2016\n1.3 Petrol | 2NZ-FE")->call('save')->assertHasNoErrors();
        $this->assertSame(2002, $ist->engines()->where('code', '1NZ-FE')->value('year_from'));

        $csv = "make,model,engine_code,litres,fuel,year_from,year_to\nToyota,Hilux,1GD-FTV,2.8,Diesel,2015,\nGreat Wall,Wingle 5,GW4D20,2.0,diesel,2011,2018\n,NoMake,,,,,\n";
        $this->post(route('website.cars.import'), ['file' => UploadedFile::fake()->createWithContent('cars.csv', $csv)])
            ->assertRedirect(route('website.cars'))->assertSessionHas('toast', fn ($t) => str_contains($t['message'], '1 new cars, 1 new engines, 1 rows skipped'));
        $this->assertTrue(CarModel::where('make', 'Great Wall')->where('model', 'Wingle 5')->exists());
        $this->assertSame('Diesel', CarEngine::where('code', 'GW4D20')->value('fuel'));

        // staff without the manager role cannot import
        $this->actingAs(User::factory()->role(User::STAFF)->create(), 'web');
        $this->post(route('website.cars.import'))->assertForbidden();
    }

    public function test_parts_link_to_cars_and_the_website_finds_them(): void
    {
        $this->actingAs(User::factory()->role(User::MANAGER)->create(), 'web');
        $part = Part::create(['sku' => 'FLT-9', 'name' => 'Diesel Fuel Filter', 'category' => 'Filters', 'unit' => 'Pcs', 'purchase_price' => 10000,
            'selling_price' => 20000, 'stock_qty' => 5, 'is_active' => true, 'show_online' => true]);
        $hilux = CarModel::where('make', 'Toyota')->where('model', 'Hilux')->first();
        $kd = $hilux->engines()->where('code', '1KD-FTV')->first();
        $fortuner = CarModel::where('make', 'Toyota')->where('model', 'Fortuner')->first();

        Livewire::test(PartsIndex::class)->call('edit', $part->id)
            ->set('fitMake', 'Toyota')->set('fitModel', (string) $hilux->id)->set('fitEngine', (string) $kd->id)->call('addFit')
            ->set('fitModel', (string) $fortuner->id)->call('addFit')                   // all engines
            ->set('fitModel', (string) $fortuner->id)->set('fitEngine', (string) $fortuner->engines()->first()->id)->call('addFit')->assertHasErrors('fitModel')
            ->assertSee('Toyota Hilux – 3.0 Diesel (1KD-FTV)')->assertSee('Toyota Fortuner – all engines')
            ->call('save')->assertHasNoErrors();
        $this->assertSame(2, $part->fitments()->count());

        // the view icon shows the details, with engines for staff
        Livewire::test(PartsIndex::class)->call('view', $part->id)->assertSet('showView', true)->assertSee('Toyota Hilux – 3.0 Diesel (1KD-FTV)');

        // website: found by make and model, and shows make and model only (no engines)
        Livewire::test(Catalog::class)->set('make', 'Toyota')->set('model', 'Fortuner')->assertSee('Diesel Fuel Filter')
            ->assertSee('Fits Toyota Hilux, Toyota Fortuner');
        Livewire::test(Catalog::class)->set('make', 'Nissan')->assertDontSee('Diesel Fuel Filter');
        $this->get(route('shop.product', [$part, $part->slug()]))->assertSee('Toyota Hilux, Toyota Fortuner')->assertDontSee('1KD-FTV');

        // the home page car picker only offers cars we have parts for
        $home = Livewire::test(\App\Livewire\Shop\Home::class);
        $home->assertViewHas('makes', fn ($makes) => in_array('Toyota', $makes, true) && ! in_array('Nissan', $makes, true));
        $home->set('make', 'Toyota')->assertViewHas('models', fn ($models) => in_array('Hilux', $models, true) && in_array('Fortuner', $models, true) && ! in_array('Prado', $models, true));
        // a part hidden from the website no longer puts its cars in the picker
        $part->update(['show_online' => false]);
        Livewire::test(\App\Livewire\Shop\Home::class)->assertViewHas('makes', fn ($makes) => ! in_array('Toyota', $makes, true));
    }

    public function test_engine_dropdown_after_model_filters_parts(): void
    {
        $hilux = CarModel::where('make', 'Toyota')->where('model', 'Hilux')->first();
        $kd = $hilux->engines()->where('code', '1KD-FTV')->first();
        $other = $hilux->engines()->where('id', '!=', $kd->id)->first();
        $mk = fn ($name) => Part::create(['sku' => 'E-'.$name, 'name' => $name, 'category' => 'Filters', 'unit' => 'Pcs', 'purchase_price' => 1,
            'selling_price' => 2, 'stock_qty' => 5, 'is_active' => true, 'show_online' => true]);
        $diesel = $mk('Diesel Only Filter');
        $all = $mk('Every Hilux Wiper');
        $petrol = $mk('Petrol Only Plug');
        \App\Models\PartVehicle::create(['part_id' => $diesel->id, 'car_model_id' => $hilux->id, 'car_engine_id' => $kd->id]);
        \App\Models\PartVehicle::create(['part_id' => $all->id, 'car_model_id' => $hilux->id]);
        \App\Models\PartVehicle::create(['part_id' => $petrol->id, 'car_model_id' => $hilux->id, 'car_engine_id' => $other->id]);

        // a part for all engines makes every Hilux engine selectable
        $engines = \App\Support\ShopVehicles::engines('Toyota', 'Hilux');
        $this->assertSame($hilux->engines()->count(), count($engines));
        $this->assertStringContainsString('1KD-FTV', $engines[$kd->id]);

        // home: make → model → engine, then on to the shop with the engine
        Livewire::test(\App\Livewire\Shop\Home::class)->set('make', 'Toyota')->set('model', 'Hilux')
            ->assertSee('1KD-FTV')->set('engine', (string) $kd->id)->call('findParts')
            ->assertRedirect(route('shop.catalog', ['make' => 'Toyota', 'model' => 'Hilux', 'engine' => $kd->id]));

        // shop: the engine narrows the list; "all engines" parts stay
        Livewire::test(Catalog::class)->set('make', 'Toyota')->set('model', 'Hilux')->set('engine', (string) $kd->id)
            ->assertSee('Diesel Only Filter')->assertSee('Every Hilux Wiper')->assertDontSee('Petrol Only Plug')->assertSee('Engine ')
            ->set('model', 'Fortuner')->assertSet('engine', '');

        // without the all-engines part only the linked engines are offered
        $all->fitments()->delete();
        $this->assertEqualsCanonicalizing([$kd->id, $other->id], array_keys(\App\Support\ShopVehicles::engines('Toyota', 'Hilux')));
    }

    public function test_category_quick_search_dashboard_order_and_out_of_stock(): void
    {
        $this->seed(\Database\Seeders\DemoShopSeeder::class);
        $home = $this->get('/')->assertOk()->assertSee('Quick search by category')->assertSee('Spark Plugs');
        $this->get('/shop')->assertOk()->assertSee('Quick search by category');
        $this->get('/shop?category=Filters')->assertOk()->assertDontSee('Quick search by category');

        Part::where('name', 'Dashboard Phone Holder')->update(['stock_qty' => 0]);
        $this->get('/shop?category=Car+Accessories')->assertSee('text-red-700', false);

        $this->actingAs(User::factory()->role(User::ADMIN)->create(), 'web');
        $this->get(route('dashboard'))->assertOk()->assertSee('Where spare parts are sold')->assertDontSee('In the Workshop');
    }
}
