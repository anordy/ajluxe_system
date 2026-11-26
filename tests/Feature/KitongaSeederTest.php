<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\Supplier;
use Database\Seeders\KitongaSparkPlugSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitongaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_spark_plugs_come_in_as_supplier_parts_without_stock(): void
    {
        $this->seed();
        $this->seed(KitongaSparkPlugSeeder::class);
        $this->seed(KitongaSparkPlugSeeder::class);                  // running again does not double them

        $sup = Supplier::where('name', 'Kitonga Spareparts')->firstOrFail();
        $parts = Part::where('source_supplier_id', $sup->id)->get();
        $this->assertCount(12, $parts);
        $this->assertTrue($parts->every(fn ($p) => $p->sourced && (float) $p->stock_qty === 0.0 && $p->show_online && $p->subcategory === 'Spark Plugs'));
        $this->assertSame(['Denso', 'NGK'], $parts->pluck('brand')->unique()->sort()->values()->all());

        $p = $parts->firstWhere('oem_no', '90919-01210');
        $this->assertEquals(18000, (float) $p->purchase_price);
        $this->assertEquals(29000, (float) $p->selling_price);   // 25,000 + 15% = 28,750, rounded up to 500
        $this->get(route('shop.catalog', ['instock' => 1]))->assertSee('Spark Plug 90919-01210');
        $this->assertSame('parts/kitonga-spark-plug.jpg', $p->image_path);

        // a picture uploaded in the staff portal survives running the seeder again
        $p->update(['image_path' => 'parts/my-own-plug-photo.jpg']);
        $this->seed(KitongaSparkPlugSeeder::class);
        $this->assertSame('parts/my-own-plug-photo.jpg', $p->fresh()->image_path);
    }

    public function test_shock_absorbers_with_photo_and_markup(): void
    {
        $this->seed();
        $this->seed(\Database\Seeders\KitongaShockAbsorberSeeder::class);
        $this->seed(\Database\Seeders\KitongaShockAbsorberSeeder::class);

        $sup = Supplier::where('name', 'Kitonga Spareparts')->firstOrFail();
        $parts = Part::where('source_supplier_id', $sup->id)->get();
        $this->assertCount(79, $parts);
        $this->assertTrue($parts->every(fn ($p) => $p->sourced && (float) $p->stock_qty === 0.0 && $p->image_path === 'parts/kitonga-shock-absorber.jpg'));
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('parts/kitonga-shock-absorber.jpg');

        $harrier = $parts->firstWhere('oem_no', '334399');
        $this->assertEquals(65000, (float) $harrier->purchase_price);
        $this->assertEquals(75000, (float) $harrier->selling_price);                 // 65,000 + 15% = 74,750 → 75,000
        $this->assertSame('Front', $harrier->specs['Fitting position']);
        $odd = $parts->firstWhere('oem_no', '341191');                                // list sells below cost: we never go under cost + 15%
        $this->assertEquals(40500, (float) $odd->selling_price);
        $this->get(route('shop.product', [$harrier, $harrier->slug()]))->assertOk()->assertSee('Toyota Harrier');
    }
}
