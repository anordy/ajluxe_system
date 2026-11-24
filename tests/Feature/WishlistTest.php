<?php

namespace Tests\Feature;

use App\Livewire\Shop\Auth\Login;
use App\Livewire\Shop\Catalog;
use App\Livewire\Shop\Product;
use App\Livewire\Shop\WishlistPage;
use App\Models\Part;
use App\Models\ShopCustomer;
use App\Support\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private function part(string $name, float $stock = 5): Part
    {
        $p = Part::create(['sku' => 'W-'.md5($name), 'name' => $name, 'category' => 'Filters', 'unit' => 'Pcs', 'purchase_price' => 1000,
            'selling_price' => 2000, 'is_active' => true, 'show_online' => true]);
        $p->forceFill(['stock_qty' => $stock])->save();

        return $p;
    }

    public function test_guest_saves_parts_and_keeps_them_after_logging_in(): void
    {
        $this->seed();
        $a = $this->part('Oil Filter Hilux');
        $b = $this->part('Air Filter Prado', 0);

        Livewire::test(Catalog::class)->call('toggleWishlist', $a->id)->assertDispatched('wishlist-updated')
            ->assertSeeHtml('aria-pressed="true"');
        Livewire::test(Product::class, ['part' => $b])->call('toggleWishlist', $b->id);
        $this->assertSame([$b->id, $a->id], Wishlist::ids());

        $this->get(route('shop.wishlist'))->assertOk()->assertSee('Oil Filter Hilux')->assertSee('Air Filter Prado')
            ->assertSee('Log in to keep your wishlist on every device.');
        // header heart shows the count
        $this->get('/')->assertSeeHtml('aria-label="Wishlist, 2 items"');

        // remove one again
        Livewire::test(WishlistPage::class)->call('toggleWishlist', $b->id)->assertDontSee('Air Filter Prado');

        ShopCustomer::create(['name' => 'Asha', 'phone' => '0712000000', 'password' => 'secret-pass']);
        Livewire::test(Login::class)->set('login', '0712000000')->set('password', 'secret-pass')->call('signIn')->assertHasNoErrors();
        $this->assertSame([$a->id], DB::table('wishlist_items')->pluck('part_id')->map(fn ($v) => (int) $v)->all());
        $this->assertNull(session('wishlist'));
        $this->get(route('shop.wishlist'))->assertSee('Oil Filter Hilux')->assertSee('Saved in your account.');
    }

    public function test_cards_show_the_cart_button_on_the_picture_and_product_zooms(): void
    {
        $this->seed();
        $a = $this->part('Spark Plug NGK');
        Livewire::test(WishlistPage::class)->call('toggleWishlist', $a->id)
            ->assertDontSeeHtml('wire:click="buyNow('.$a->id.')"')->assertSeeHtml('wire:click="addToCart('.$a->id.')"');
        $this->get(route('shop.product', [$a, $a->slug()]))->assertOk()->assertSee('View full screen')->assertSeeHtml('cursor-zoom-in')
            ->assertSeeHtml('aria-label="Remove from wishlist"');
        Livewire::test(WishlistPage::class)->call('buyNow', $a->id)->assertRedirect(route('shop.checkout'));
    }
}
