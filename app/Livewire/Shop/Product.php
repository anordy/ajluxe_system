<?php

namespace App\Livewire\Shop;

use App\Models\Part;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.shop')]
class Product extends Component
{
    use AddsToCart;

    public Part $part;
    public int $qty = 1;

    public function mount(Part $part): void
    {
        abort_unless($part->is_active && $part->show_online && (float) $part->selling_price > 0, 404);
        $this->part = $part;
        $this->qty = $part->category === 'Tyres' ? max(1, min(4, (int) $part->stock_qty)) : 1;
    }

    public function buy()
    {
        return $this->buyNow($this->part->id, max(1, $this->qty));
    }

    public function add(): void
    {
        $this->addToCart($this->part->id, max(1, $this->qty));
        $this->qty = 1;
    }

    public function render()
    {
        $p = $this->part->refresh();

        return view('livewire.shop.product', [
            'p'       => $p,
            'family'  => ($f = $p->family())->count() > 1 ? $f : null,
            'related' => Part::online()->where('id', '!=', $p->id)->listed()->where('stock_qty', '>', 0)
                ->where(fn ($q) => $q->whereNull('variant_of')->orWhere('variant_of', '!=', $p->variant_of ?: $p->id))
                ->where(fn ($q) => $q->where('id', '!=', $p->variant_of ?: 0))
                ->where(fn ($q) => $q->when($p->category, fn ($q) => $q->where('category', $p->category))
                    ->when($p->vehicle_make, fn ($q) => $q->orWhere('vehicle_make', $p->vehicle_make)))
                ->inRandomOrder()->limit(4)->get(),
        ])->title($p->name);
    }
}
