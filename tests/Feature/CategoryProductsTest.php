<?php

namespace Tests\Feature;

use App\Livewire\Shop\CategoryProducts;
use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryProductsTest extends TestCase
{
    use RefreshDatabase;

    private function part(string $name, string $cat, ?string $sub = null): Part
    {
        $p = Part::create(['sku' => 'T-'.md5($name), 'name' => $name, 'category' => $cat, 'subcategory' => $sub, 'unit' => 'Pcs',
            'purchase_price' => 1000, 'selling_price' => 2000, 'is_active' => true, 'show_online' => true]);
        $p->forceFill(['stock_qty' => 5])->save();

        return $p;
    }

    public function test_random_mix_then_filtered_products(): void
    {
        $this->seed();
        $this->part('Brake Disc Front', 'Car Parts', 'Brakes');
        $this->part('Oil Filter AA1', 'Filters', 'Oil Filter');
        $this->part('Air Filter BB2', 'Filters', 'Air Filter');

        Livewire::test(CategoryProducts::class)
            ->assertSee('Brake Disc Front')->assertSee('Oil Filter AA1')
            ->dispatch('category-filter', q: 'oil', cats: [['Filters', 'Oil Filter']])
            ->assertSee('Oil Filter AA1')->assertDontSee('Brake Disc Front')->assertDontSee('Air Filter BB2')
            ->dispatch('category-filter', q: 'zzz', cats: [])
            ->assertDontSee('Oil Filter AA1')
            ->dispatch('category-filter', q: '', cats: [])
            ->assertSee('Brake Disc Front');
    }
}
