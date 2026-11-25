<?php

namespace App\Livewire\Shop;

use App\Models\Part;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Product tiles beside "Quick search by category": a random mix of the shop's departments,
 * swapped for the matching products as the customer types in the category search.
 */
class CategoryProducts extends Component
{
    public const LIMIT = 8;

    /** Random picks chosen once per visit, so they don't reshuffle while the page is used. */
    public array $randomIds = [];

    public string $q = '';

    /** [[category, subcategory|null], ...] — the list rows that match the search. */
    public array $cats = [];

    public function mount(): void
    {
        // an even mix: a couple from every department, topped up at random if one is short
        $deps = Part::shopDepartments();
        $each = max(1, (int) ceil(self::LIMIT / max(1, count($deps))));
        $ids = collect($deps)->flatMap(fn ($d) => $this->base()->where('category', $d)->inRandomOrder()->limit($each)->pluck('id'));
        if ($ids->count() < self::LIMIT) {
            $ids = $ids->concat($this->base()->whereIn('category', $deps)->whereNotIn('id', $ids)->inRandomOrder()
                ->limit(self::LIMIT - $ids->count())->pluck('id'));
        }
        $this->randomIds = $ids->shuffle()->take(self::LIMIT)->values()->all();
    }

    #[On('category-filter')]
    public function filter(string $q = '', array $cats = []): void
    {
        $this->q = mb_substr(trim($q), 0, 60);
        $this->cats = array_slice(array_values(array_filter($cats, fn ($c) => is_array($c) && filled($c[0] ?? null))), 0, 60);
    }

    private function base()
    {
        return Part::online()->listed()->where('stock_qty', '>', 0);
    }

    public function render()
    {
        if ($this->q === '') {
            $order = array_flip($this->randomIds);
            $parts = Part::whereIn('id', $this->randomIds)->get()->sortBy(fn ($p) => $order[$p->id] ?? 99)->values();
        } elseif ($this->cats === []) {
            $parts = collect();
        } else {
            $parts = $this->base()->where(function ($w) {
                foreach ($this->cats as $c) {
                    $w->orWhere(fn ($x) => $x->where('category', (string) $c[0])
                        ->when(filled($c[1] ?? null), fn ($x) => $x->where('subcategory', (string) $c[1])));
                }
            })->inRandomOrder()->limit(self::LIMIT)->get();
        }

        return view('livewire.shop.category-products', ['parts' => $parts]);
    }
}
