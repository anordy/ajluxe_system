<?php

namespace Tests\Feature;

use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_badge_shows_for_twelve_hours(): void
    {
        $this->seed();
        $p = Part::create(['sku' => 'NEW-1', 'name' => 'Fresh Wiper', 'category' => 'Car Parts', 'unit' => 'Pcs',
            'purchase_price' => 1000, 'selling_price' => 3000, 'is_active' => true, 'show_online' => true]);
        $p->forceFill(['stock_qty' => 4])->save();

        $this->assertTrue($p->isNew());
        $this->get(route('shop.catalog'))->assertSeeInOrder(['New', 'Fresh Wiper']);

        $this->travel(13)->hours();
        $this->assertFalse($p->fresh()->isNew());
    }

    public function test_ribbons_automatic_and_picked(): void
    {
        $this->seed();
        $p = Part::create(['sku' => 'RB-1', 'name' => 'Ribbon Part', 'category' => 'Car Parts', 'unit' => 'Pcs',
            'purchase_price' => 1000, 'selling_price' => 3000, 'is_active' => true, 'show_online' => true, 'discount_percent' => 10]);
        $this->assertSame('sold_out', $p->ribbonBadge()['key']);                 // nothing to sell
        $p->forceFill(['stock_qty' => 3])->save();
        $this->assertSame('new', $p->fresh()->ribbonBadge()['key']);
        $this->assertSame('New −10%', $p->fresh()->ribbonBadge()['label']);
        $this->travel(13)->hours();
        $this->assertSame('sale', $p->fresh()->ribbonBadge()['key']);
        $p->update(['ribbon' => 'best_offer']);
        $this->assertSame('best_offer', $p->fresh()->ribbonBadge()['key']);
        $this->get(route('shop.product', [$p, $p->slug()]))->assertSee('ribbon-best-offer', false)->assertSee('Best offer −10%');
        $p->update(['ribbon' => 'none']);
        $this->assertNull($p->fresh()->ribbonBadge());
    }
}
