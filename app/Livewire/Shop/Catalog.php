<?php

namespace App\Livewire\Shop;

use App\Models\Part;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.shop')]
class Catalog extends Component
{
    use AddsToCart, WithPagination;

    #[Url(except: '')]
    public string $q = '';

    /** department */
    #[Url(except: '')]
    public string $category = '';

    /** type inside the department (subcategory) */
    #[Url(except: '')]
    public string $type = '';

    #[Url(except: [])]
    public array $brands = [];

    #[Url(except: '')]
    public string $make = '';

    #[Url(except: '')]
    public string $model = '';

    /** car_engines.id from the engine dropdown (after make and model) */
    #[Url(except: '')]
    public string $engine = '';

    /** value of the department's quick-filter spec (viscosity, tyre size) */
    #[Url(except: '')]
    public string $spec = '';

    /** "Fitting position" spec: Front axle, Rear axle, Left, Right… */
    #[Url(except: '')]
    public string $position = '';

    /** specification filters from the sidebar: ['Capacity' => ['4 L'], 'Season' => ['Summer']] */
    #[Url(except: [])]
    public array $f = [];

    /** tyre finder: width, height (profile), rim diameter, and the tab (car | suv | van) */
    #[Url(except: '')]
    public string $tw = '';

    #[Url(except: '')]
    public string $th = '';

    #[Url(except: '')]
    public string $tr = '';

    #[Url(except: '')]
    public string $vt = '';

    #[Url(except: false)]
    public bool $deals = false;

    #[Url(except: false)]
    public bool $instock = false;

    /** relevance | price_low | price_high | name */
    #[Url(except: 'relevance')]
    public string $sort = 'relevance';

    public function updating($field): void
    {
        if (in_array(explode('.', $field)[0], ['q', 'category', 'type', 'brands', 'make', 'model', 'engine', 'spec', 'position', 'f', 'tw', 'th', 'tr', 'vt', 'deals', 'instock', 'sort'], true)) {
            $this->resetPage();
        }
        if ($field === 'category') {
            $this->type = $this->spec = $this->position = $this->tw = $this->th = $this->tr = $this->vt = '';
            $this->brands = $this->f = [];
        }
        if ($field === 'make') {
            $this->model = $this->engine = '';
        }
        if ($field === 'model') {
            $this->engine = '';
        }
        if ($field === 'tw') {
            $this->th = $this->tr = '';
        }
        if ($field === 'th') {
            $this->tr = '';
        }
    }

    public function setCategory(string $c): void
    {
        $this->updating('category');
        $this->category = $this->category === $c ? '' : $c;
    }

    public function setType(string $t): void
    {
        $this->resetPage();
        $this->type = $this->type === $t ? '' : $t;
    }

    public function setSpec(string $v): void
    {
        $this->resetPage();
        $this->spec = $this->spec === $v ? '' : $v;
    }

    public function toggleFacet(string $key, string $value): void
    {
        $this->resetPage();
        $vals = $this->f[$key] ?? [];
        $vals = in_array($value, $vals, true) ? array_values(array_diff($vals, [$value])) : [...$vals, $value];
        if ($vals) {
            $this->f[$key] = $vals;
        } else {
            unset($this->f[$key]);
        }
    }

    public function setVehicleType(string $vt): void
    {
        $this->resetPage();
        $this->vt = $this->vt === $vt ? '' : $vt;
        $this->type = '';
    }

    public function toggleBrand(string $b): void
    {
        $this->resetPage();
        $this->brands = in_array($b, $this->brands, true) ? array_values(array_diff($this->brands, [$b])) : [...$this->brands, $b];
    }

    public function setPosition(string $p): void
    {
        $this->resetPage();
        $this->position = $this->position === $p ? '' : $p;
    }

    /** Remove one active filter (the chips above the results). */
    public function drop(string $what, string $value = ''): void
    {
        $this->resetPage();
        match ($what) {
            'brand'  => $this->brands = array_values(array_diff($this->brands, [$value])),
            'make'   => [$this->make, $this->model, $this->engine] = ['', '', ''],
            'engine' => $this->engine = '',
            'deals', 'instock' => $this->{$what} = false,
            'facet'  => $this->toggleFacet(...explode('|', $value, 2)),
            'size'   => [$this->tw, $this->th, $this->tr] = ['', '', ''],
            default  => $this->{$what} = '',
        };
    }

    /** Clear the filters but stay in the department. */
    public function resetFilters(): void
    {
        $this->reset(['q', 'type', 'brands', 'make', 'model', 'engine', 'spec', 'position', 'f', 'tw', 'th', 'tr', 'vt', 'deals', 'instock']);
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['q', 'category', 'type', 'brands', 'make', 'model', 'engine', 'spec', 'position', 'f', 'tw', 'th', 'tr', 'vt', 'deals', 'instock', 'sort']);
        $this->resetPage();
    }

    public function render()
    {
        // old links and bookmarks: the oil department used to be called "Engine Oil"
        if ($this->category === 'Engine Oil') {
            $this->category = Part::OIL;
        }
        $chipKey = Part::CHIP_SPECS[$this->category] ?? null;
        // the price customers pay (after website discount), used for sorting
        $priceSql = 'CASE WHEN discount_percent > 0 THEN selling_price * (1 - discount_percent / 100) ELSE selling_price END';

        $facetKeys = Part::FACETS[$this->category] ?? [];
        $isTyres = $this->category === 'Tyres';
        $groupTypes = $isTyres && isset(Part::TYRE_GROUPS[$this->vt]) ? Part::TYRE_GROUPS[$this->vt][1] : null;

        // everything except the brand / type / spec filters — used for the counts beside those filters
        $scope = Part::online()
            ->search($this->q)
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($groupTypes, fn ($q) => $q->whereIn('subcategory', $groupTypes))
            ->fits($this->make ?: null, $this->model ?: null, ctype_digit($this->engine) ? (int) $this->engine : null)
            ->when($this->deals, fn ($q) => $q->where('discount_percent', '>', 0))
            ->when($this->instock, fn ($q) => $q->where('stock_qty', '>', 0));

        // tyre size from the finder: "205/55 R16" with any part left open
        $sizeScope = clone $scope;
        if ($isTyres && ($this->tw || $this->th || $this->tr)) {
            $pattern = ($this->tw ?: '%').'/'.($this->th ?: '%').' R'.($this->tr ?: '%').'%';
            $scope->where('specs->Size', 'like', $pattern);
        }

        $applyFacets = function ($q, ?string $except = null) {
            foreach ($this->f as $key => $vals) {
                if ($key === $except || ! is_array($vals) || ! $vals) {
                    continue;
                }
                $q->where(function ($q) use ($key, $vals) {
                    foreach ($vals as $v) {
                        in_array($key, Part::MULTI_SPECS, true)
                            ? $q->orWhere('specs->'.$key, 'like', '%'.$v.'%')
                            : $q->orWhere('specs->'.$key, $v);
                    }
                });
            }

            return $q;
        };
        $filtered = fn ($q) => $applyFacets($q)
            ->when($this->brands, fn ($q) => $q->whereIn('brand', $this->brands))
            ->when($chipKey && $this->spec, fn ($q) => $q->where('specs->'.$chipKey, $this->spec))
            ->when($this->position, fn ($q) => $q->where('specs->Fitting position', $this->position));

        // other sizes of the same product share one row, unless the customer filters by size
        $bySize = ! empty($this->f['Capacity']);

        $parts = $filtered(clone $scope)->with('fitments.carModel')
            ->when($this->type, fn ($q) => $q->where('subcategory', $this->type))
            ->when(! $bySize, fn ($q) => $q->listed())
            ->orderByRaw('CASE WHEN stock_qty > 0 THEN 0 ELSE 1 END')
            ->when($this->sort === 'price_low', fn ($q) => $q->orderByRaw($priceSql.' asc'))
            ->when($this->sort === 'price_high', fn ($q) => $q->orderByRaw($priceSql.' desc'))
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'relevance', fn ($q) => $q->orderByDesc('discount_percent')->orderBy('name'))
            ->paginate(20);

        $all = Part::online();
        $order = ['Front axle', 'Front left', 'Front right', 'Rear axle', 'Rear left', 'Rear right', 'Left', 'Right'];
        // counts beside filters: one per product (other sizes are not counted twice), unless sizes are filtered
        $typed = fn ($q) => $q->when($this->type, fn ($q) => $q->where('subcategory', $this->type))->when(! $bySize, fn ($q) => $q->listed());
        $positions = $typed(clone $scope)->get(['specs'])
            ->pluck('specs.Fitting position')->filter()->countBy()
            ->sortBy(fn ($n, $p) => ($i = array_search($p, $order, true)) === false ? 99 : $i)->all();
        $chipValues = $chipKey ? $applyFacets($typed(clone $scope))->when($this->brands, fn ($q) => $q->whereIn('brand', $this->brands))
            ->get(['specs'])->pluck('specs.'.$chipKey)->filter()->countBy()->sortKeys()->all() : [];

        // sidebar specification filters with counts (each one counted with the other filters applied)
        $facets = [];
        foreach ($facetKeys as $key) {
            $base = $key === 'Capacity' ? (clone $scope)->when($this->type, fn ($q) => $q->where('subcategory', $this->type)) : $typed(clone $scope);
            $rows = $applyFacets($base, $key)
                ->when($this->brands, fn ($q) => $q->whereIn('brand', $this->brands))
                ->when($chipKey && $this->spec, fn ($q) => $q->where('specs->'.$chipKey, $this->spec))
                ->get(['specs'])->pluck('specs.'.$key)->filter();
            $counts = [];
            foreach ($rows as $v) {
                $vals = in_array($key, Part::MULTI_SPECS, true) ? array_map('trim', explode(',', (string) $v)) : [(string) $v];
                foreach (array_unique(array_filter($vals)) as $one) {
                    $counts[$one] = ($counts[$one] ?? 0) + 1;
                }
            }
            uksort($counts, fn ($a, $b) => strnatcasecmp($a, $b));
            if (count($counts) > 1 || ! empty($this->f[$key]) || ($key === 'Season' && $counts)) {
                $facets[$key] = $counts;
            }
        }

        // tyre finder options, from the tyres we have
        $tyre = null;
        if ($isTyres) {
            $sizes = $sizeScope->get(['specs', 'subcategory'])->map(fn ($p) => Part::tyreSize($p->specs['Size'] ?? null))->filter();
            $tyreAll = Part::online()->where('category', 'Tyres')->with('fitments.carModel:id,make')->get(['id', 'subcategory', 'specs', 'vehicle_make']);
            $tyre = [
                'widths'  => $sizes->pluck('w')->unique()->sort()->values(),
                'heights' => $sizes->when($this->tw, fn ($c) => $c->where('w', $this->tw))->pluck('h')->unique()->sort()->values(),
                'rims'    => $sizes->when($this->tw, fn ($c) => $c->where('w', $this->tw))->when($this->th, fn ($c) => $c->where('h', $this->th))->pluck('r')->unique()->sort()->values(),
                'groups'  => collect(Part::TYRE_GROUPS)->map(fn ($g) => $tyreAll->whereIn('subcategory', $g[1])->count()),
                'makes'   => $tyreAll->flatMap(fn ($p) => [$p->vehicle_make, ...$p->fitments->pluck('carModel.make')])->filter()->unique()->sort()->values(),
            ];
        }

        $brandList = $applyFacets($typed(clone $scope))->whereNotNull('brand')->where('brand', '!=', '')
            ->selectRaw('brand, count(*) as n')->groupBy('brand')->orderBy('brand')->pluck('n', 'brand');
        // engine oil: the manufacturer row shows only the brands chosen in Website texts, in that order
        $brandStrip = $brandList;
        if ($this->category === Part::OIL && ($chosen = array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', (string) setting('web_oil_brands', '')))))) {
            // "Atlantic Oil" in the list also finds parts branded "Atlantic", "Toyota Genuine" finds "Toyota"
            $same = fn ($x, $y) => ($x = mb_strtolower(trim($x))) === ($y = mb_strtolower(trim($y))) || str_starts_with($x, $y.' ') || str_starts_with($y, $x.' ');
            $brandStrip = collect($chosen)->mapWithKeys(function ($c) use ($brandList, $same) {
                $hit = $brandList->keys()->first(fn ($b) => mb_strtolower($b) === mb_strtolower($c)) ?? $brandList->keys()->first(fn ($b) => $same($b, $c));

                return $hit ? [$hit => $brandList[$hit]] : [];
            });
        }

        return view('livewire.shop.catalog', [
            'parts'       => $parts,
            'families'    => Part::families($parts->getCollection()),
            'departments' => (clone $all)->search($this->q)->whereIn('category', Part::shopDepartments())->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category')
                ->sortBy(fn ($n, $dep) => array_search($dep, Part::shopDepartments(), true)),
            'types'       => $this->category && ! $isTyres ? $filtered(clone $scope)->when(! $bySize, fn ($q) => $q->listed())->whereNotNull('subcategory')
                ->selectRaw('subcategory, count(*) as n')->groupBy('subcategory')->orderBy('subcategory')->pluck('n', 'subcategory') : collect(),
            'brandList'   => $brandList,
            'brandStrip'  => $brandStrip,
            'makes'       => (clone $all)->whereNotNull('vehicle_make')->where('vehicle_make', '!=', '')->distinct()->pluck('vehicle_make')
                ->merge(\Illuminate\Support\Facades\DB::table('part_vehicles')->join('car_models', 'car_models.id', '=', 'part_vehicles.car_model_id')
                    ->whereIn('part_vehicles.part_id', Part::online()->select('id'))->distinct()->pluck('car_models.make'))
                ->unique()->sort()->values(),
            'positions'   => $positions,
            'carModels'   => $this->make ? (\App\Support\ShopVehicles::all()[$this->make] ?? []) : [],
            'engines'     => \App\Support\ShopVehicles::engines($this->make, $this->model),
            'chipKey'     => $isTyres ? null : $chipKey,
            'chipValues'  => $chipValues,
            'facets'      => $facets,
            'tyre'        => $tyre,
            'filtered'    => $this->q !== '' || $this->category || $this->type || $this->brands || $this->make || $this->model || $this->spec || $this->position || $this->f || $this->tw || $this->th || $this->tr || $this->vt || $this->deals || $this->instock,
            'active'      => $this->q !== '' || $this->type || $this->brands || $this->make || $this->spec || $this->position || $this->f || $this->tw || $this->th || $this->tr || $this->deals || $this->instock,
        ])->title($this->deals ? __('Deals') : ($this->type ? __($this->type) : ($this->category ? __($this->category) : __('Shop spare parts'))));
    }
}
