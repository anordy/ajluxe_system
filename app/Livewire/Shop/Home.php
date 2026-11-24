<?php

namespace App\Livewire\Shop;

use App\Models\CarModel;
use App\Models\Part;
use App\Models\SiteBanner;
use App\Support\ProductArt;
use App\Support\ShopVehicles;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.shop')]
class Home extends Component
{
    use AddsToCart;

    /** "Find parts for your car" */
    public string $make = '';
    public string $model = '';
    public string $engine = '';

    public function updatedMake(): void
    {
        $this->model = $this->engine = '';
    }

    public function updatedModel(): void
    {
        $this->engine = '';
    }

    public function findParts()
    {
        return $this->redirect(route('shop.catalog', array_filter(['make' => $this->make, 'model' => $this->model, 'engine' => $this->engine])), navigate: true);
    }

    public function render()
    {
        $inStock = fn () => Part::online()->listed()->where('stock_qty', '>', 0);
        $deals = $inStock()->where('discount_percent', '>', 0)->orderByDesc('discount_percent')->limit(40)->get()
            ->filter->hasDiscount()->sortByDesc(fn ($p) => $p->discountShown())->values();
        $vehicles = ShopVehicles::all();

        // category tiles: departments, then the busiest types
        $byDep = Part::online()->where('stock_qty', '>', 0)->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category');
        $bySub = Part::online()->where('stock_qty', '>', 0)->whereNotNull('subcategory')
            ->selectRaw('category, subcategory, count(*) as n')->groupBy('category', 'subcategory')->orderByDesc('n')->limit(14)->get();
        $tiles = collect(Part::shopDepartments())->filter(fn ($d) => isset($byDep[$d]))
            ->map(fn ($d) => ['label' => $d, 'count' => $byDep[$d], 'url' => route('shop.catalog', ['category' => $d]), 'art' => ProductArt::forCategory($d)])
            ->concat($bySub->map(fn ($r) => ['label' => $r->subcategory, 'count' => $r->n,
                'url' => route('shop.catalog', ['category' => $r->category, 'type' => $r->subcategory]), 'art' => ProductArt::forCategory($r->category, $r->subcategory)]))
            ->values();

        // the scrolling ads: set up in Admin › Website › Banners; generated from stock only when none are live
        $slides = SiteBanner::live()->with('part')->get()->filter->isLive()->map(fn (SiteBanner $b) => [
            'tone' => $b->tone, 'kicker' => $b->tr('kicker'), 'title' => $b->tr('title'), 'text' => $b->body(),
            'cta' => $b->tr('button_label') ?: __('Shop now'), 'url' => $b->url(), 'img' => $b->imageUrl(), 'photo' => $b->isPhoto(),
            'imageOnly' => $b->isImageOnly(), 'href' => $b->clickUrl(),
        ])->values()->all();
        $slides = $slides ?: $this->autoSlides($deals, $byDep);

        return view('livewire.shop.home', [
            'slides'      => $slides,
            'tiles'       => $tiles,
            'hot'         => $deals->take(12),
            'tyres'       => $inStock()->where('category', 'Tyres')->orderByDesc('discount_percent')->limit(10)->get(),
            'oils'        => $inStock()->where('category', Part::OIL)->orderByDesc('discount_percent')->limit(10)->get(),
            'fresh'       => $deals->isEmpty() ? $inStock()->latest('updated_at')->limit(12)->get() : collect(),
            'inStockCount'=> $inStock()->count(),
            'makes'       => array_keys($vehicles),
            'models'      => $this->make !== '' ? ($vehicles[$this->make] ?? []) : [],
            'engines'     => ShopVehicles::engines($this->make, $this->model),
            'brands'      => Part::online()->where('stock_qty', '>', 0)->whereNotNull('brand')->where('brand', '!=', '')
                ->selectRaw('brand, count(*) as n')->groupBy('brand')->orderByDesc('n')->orderBy('brand')->limit(16)->pluck('n', 'brand'),
        ])->title(__('Spare parts, tyres, oil & garage'));
    }

    /** Offers made from the stock when no banner is set up yet. */
    private function autoSlides($deals, $byDep): array
    {
        $slides = [];
        if ($top = $deals->first()) {
            $slides[] = ['tone' => 'red', 'kicker' => __('Deal of the week'), 'title' => __('Save :n% on :part', ['n' => $top->discountShown(), 'part' => $top->name]),
                'text' => __('Now :price instead of :was. While stock lasts.', ['price' => money($top->salePrice()), 'was' => money($top->selling_price)]),
                'cta' => __('Shop the deal'), 'url' => route('shop.product', [$top, $top->slug()]), 'img' => $top->imageUrl() ?: ProductArt::dataUri(...ProductArt::forCategory($top->category, $top->subcategory, $top->name))];
        }
        $slides[] = ['tone' => 'ink', 'kicker' => __('Garage & paint'), 'title' => __('Painting, panel beating and repairs'),
            'text' => __('Single panels or the full car, in our workshop or spray booth. Book your car in on WhatsApp.'), 'cta' => __('Book your car in'), 'url' => route('shop.garage').'#book',
            'img' => ProductArt::dataUri('paint', ['color' => '#E31B23'])];
        if (isset($byDep['Tyres'])) {
            $from = Part::online()->where('category', 'Tyres')->where('stock_qty', '>', 0)->get()->min(fn ($p) => $p->salePrice());
            $slides[] = ['tone' => 'grey', 'kicker' => __('Tyres'), 'title' => __('New tyres from :price', ['price' => money($from)]), 'text' => __('Passenger, SUV and all-terrain sizes in stock. We fit and balance them at the garage.'),
                'cta' => __('Find your size'), 'url' => route('shop.catalog', ['category' => 'Tyres']), 'img' => ProductArt::dataUri('tyre', ['tread' => 'at'])];
        }
        if (isset($byDep[Part::OIL])) {
            $slides[] = ['tone' => 'grey', 'kicker' => __('Lubricants'), 'title' => __('The right oil for your engine'), 'text' => __('Synthetic, semi-synthetic and diesel oils with filters to match.'),
                'cta' => __('Shop lubricants'), 'url' => route('shop.catalog', ['category' => Part::OIL]), 'img' => ProductArt::dataUri('oil', ['label' => '5W-30', 'color' => '#E31B23'])];
        }
        $slides[] = ['tone' => 'ink', 'kicker' => __('Order online'), 'title' => __('Order online, collect today'), 'text' => __('We put your parts aside and call you when they are ready. Pay by M-Pesa, bank or cash.'),
            'cta' => __('Start shopping'), 'url' => route('shop.catalog'), 'img' => ProductArt::dataUri('phone_holder')];

        return $slides;
    }
}
