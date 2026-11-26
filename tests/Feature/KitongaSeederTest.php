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
        $this->assertEquals(25000, (float) $p->selling_price);
        $this->get(route('shop.catalog', ['instock' => 1]))->assertSee('Spark Plug 90919-01210');
    }
}
