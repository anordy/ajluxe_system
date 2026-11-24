<?php

namespace Tests\Feature;

use App\Livewire\Shop\Catalog;
use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Parts from the old AJ AUTO PARTS DB spreadsheet. */
class ImportOldPartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_then_import_old_parts_sheet(): void
    {
        Storage::fake('public');
        $this->seed();
        $this->seed(\Database\Seeders\DemoShopSeeder::class);
        $file = base_path('tests/Fixtures/old-system-parts.csv');

        // preview saves nothing
        $this->artisan('import:old-parts', ['file' => $file])->expectsOutputToContain('48 parts ready. Nothing was saved')->assertSuccessful();
        $this->assertSame(0, Part::where('sku', 'PRT-0001')->count());

        $this->artisan('import:old-parts', ['file' => $file, '--commit' => true, '--remove-demo' => true])->expectsOutputToContain('48 parts added')->assertSuccessful();
        $this->assertSame(0, Part::where('sku', 'like', 'DEMO-%')->where('is_active', true)->where('show_online', true)->count());

        $big = Part::where('sku', 'PRT-0001')->first();
        $small = Part::where('sku', 'PRT-0002')->first();
        $this->assertSame(['Lubricants', 'Pcs', '5 L', '5W-30'], [$big->category, $big->unit, $big->specs['Capacity'], $big->specs['Viscosity']]);
        $this->assertSame($big->id, $small->variant_of);
        $this->assertEquals(17, $big->stock_qty);
        $this->assertSame('Transmission', Part::where('sku', 'PRT-0006')->value('subcategory'));
        $this->assertSame('Paint & Body', Part::where('sku', 'PRT-0008')->value('category'));
        $this->assertSame('Cabin Filter', Part::where('sku', 'PRT-0041')->value('subcategory'));

        // car names in Notes (with spelling mistakes) become links
        $f = Part::where('sku', 'PRT-0013')->first();
        $this->assertSame('Toyota IST, Toyota Vitz, Toyota Raum and 4 more', $f->fitsText());
        $this->assertSame('Mazda CX-5', Part::where('sku', 'PRT-0017')->first()->fitsText());
        Livewire::test(Catalog::class)->set('make', 'Toyota')->set('model', 'Fortuner')->assertSee('Oil Filter-YZZD2')->assertDontSee('Oil Filter-YZZE1');

        // running again updates and never counts stock twice
        $this->artisan('import:old-parts', ['file' => $file, '--commit' => true])->expectsOutputToContain('0 parts added, 48 updated')->assertSuccessful();
        $this->assertEquals(17, $big->fresh()->stock_qty);
        $this->assertSame(1, \App\Models\StockMovement::where('part_id', $big->id)->count());

        // parts that are not in the sheet can be switched off
        $mine = Part::create(['sku' => 'TEST-1', 'name' => 'Test part', 'unit' => 'Pcs', 'purchase_price' => 1, 'selling_price' => 2, 'is_active' => true, 'show_online' => true]);
        $this->artisan('import:old-parts', ['file' => $file])->expectsOutputToContain('TEST-1  Test part')->assertSuccessful();
        $this->assertTrue($mine->fresh()->is_active);
        $this->artisan('import:old-parts', ['file' => $file, '--commit' => true, '--deactivate-others' => true])->assertSuccessful();
        $this->assertFalse($mine->fresh()->is_active);
        $this->assertTrue($big->fresh()->is_active);

        // engine oil manufacturers: "Atlantic Oil" in the list finds parts branded "Atlantic"
        Livewire::test(Catalog::class)->set('category', 'Lubricants')->assertViewHas('brandStrip', fn ($b) => $b->has('Atlantic') && $b->has('Toyota'));
    }
}
