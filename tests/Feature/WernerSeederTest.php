<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\Supplier;
use Database\Seeders\KitongaSparkPlugSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WernerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_werner_brake_pads_plus_seventy_percent(): void
    {
        $this->seed();
        // first version used "SGS Sales Ltd": its parts are taken over, not doubled
        $sgs = Supplier::create(['code' => 'SUP-77', 'name' => 'SGS Sales Ltd', 'is_active' => true]);
        $this->seed(\Database\Seeders\WernerBrakePadSeeder::class);
        $this->seed(\Database\Seeders\WernerBrakePadSeeder::class);
        $this->assertSame('Werner Supplier', $sgs->fresh()->name);

        $sup = Supplier::where('name', 'Werner Supplier')->firstOrFail();
        $parts = Part::where('source_supplier_id', $sup->id)->get();
        $this->assertCount(14, $parts);
        $this->assertTrue($parts->every(fn ($p) => $p->sourced && (float) $p->stock_qty === 0.0 && $p->subcategory === 'Brake Pads' && $p->image_path === 'parts/brake-pad.jpg'));
        $p = $parts->firstWhere('oem_no', '04465-35290');
        $this->assertEquals(23000, (float) $p->purchase_price);
        $this->assertEquals(39500, (float) $p->selling_price);          // 23,000 + 70% = 39,100 → 39,500
        $this->assertSame('Front', $p->specs['Fitting position']);
        $this->assertSame('Rear', $parts->firstWhere('oem_no', '04466-48020')->specs['Fitting position']);
    }

    public function test_werner_stabilizer_links(): void
    {
        $this->seed();
        $this->seed(\Database\Seeders\WernerBrakePadSeeder::class);
        $this->seed(\Database\Seeders\WernerStabilizerLinkSeeder::class);
        $this->seed(\Database\Seeders\WernerStabilizerLinkSeeder::class);

        $this->assertSame(1, Supplier::where('name', 'Werner Supplier')->count());        // both lists share one supplier
        $links = Part::where('subcategory', 'Stabilizer Links')->get();
        $this->assertCount(26, $links);
        $this->assertTrue($links->every(fn ($p) => $p->sourced && (float) $p->stock_qty === 0.0 && $p->image_path === 'parts/stabilizer-link.jpg'));
        $this->assertEquals(7000, (float) $links->firstWhere('oem_no', '48819-52010')->selling_price);    // 4,000 + 70% = 6,800 → 7,000
        $this->assertEquals(14000, (float) $links->firstWhere('oem_no', '48830-51010')->selling_price);   // 8,000 + 70% = 13,600 → 14,000
        $this->assertSame('Nissan', $links->firstWhere('oem_no', '54668-JD00A')->vehicle_make);
    }

    public function test_werner_ball_joints(): void
    {
        $this->seed();
        $this->seed(\Database\Seeders\WernerBallJointSeeder::class);
        $this->seed(\Database\Seeders\WernerBallJointSeeder::class);

        $joints = Part::where('subcategory', 'Ball Joints')->get();
        $this->assertCount(5, $joints);
        $this->assertTrue($joints->every(fn ($p) => $p->sourced && (float) $p->stock_qty === 0.0 && $p->image_path === 'parts/ball-joint.jpg'));
        $this->assertEquals(17000, (float) $joints->firstWhere('oem_no', '43330-19115')->selling_price);   // 10,000 + 70%
        $this->assertEquals(51000, (float) $joints->firstWhere('oem_no', '43330-39585')->selling_price);   // 30,000 + 70%
        $this->assertEquals(14000, (float) $joints->firstWhere('oem_no', '43308-59035')->selling_price);   // 8,000 + 70% = 13,600 → 14,000
    }
}
