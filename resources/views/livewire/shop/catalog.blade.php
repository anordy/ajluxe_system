@php $title = $deals ? __('Deals') : ($type ? __($type) : ($category ? __($category) : ($q !== '' ? __('Search results') : __('All spare parts')))); @endphp
<div class="wrap pt-6">
    <nav class="text-sm text-[#6B7280]" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ route('shop.home') }}" wire:navigate class="hover:text-[#16181B]">{{ __('Home') }}</a> /
        <button type="button" wire:click="clearFilters" class="hover:text-[#16181B]">{{ __('All parts') }}</button>
        @if ($category) / <button type="button" wire:click="$set('type', '')" class="hover:text-[#16181B]">{{ __($category) }}</button> @endif
        @if ($type) / <span class="text-[#16181B]">{{ __($type) }}</span> @endif
    </nav>

    <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-5xl font-extrabold tracking-tight">{{ $title }}</h1>
            <p class="mt-1 text-[#4B5563]">{{ trans_choice(':count part|:count parts', $parts->total(), ['count' => number_format($parts->total())]) }}{{ $q !== '' ? ' '.__('for “:q”', ['q' => $q]) : '' }}{{ $make ? ' '.__('that fit :car', ['car' => trim($make.' '.$model)]) : '' }}{{ $make && $engine && isset($engines[$engine]) ? ', '.$engines[$engine] : '' }}</p>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <span class="text-[#4B5563]">{{ __('Sort by') }}</span>
            <select wire:model.live="sort" class="s-input w-auto py-2 pr-9">
                <option value="relevance">{{ __('Best offers first') }}</option>
                <option value="price_low">{{ __('Price: low to high') }}</option>
                <option value="price_high">{{ __('Price: high to low') }}</option>
                <option value="name">{{ __('Name A–Z') }}</option>
            </select>
        </label>
    </div>

    {{-- all parts, nothing chosen yet: quick search by category --}}
    @if (! $category && $q === '' && ! $active && ! $deals)
        <x-shop.category-search class="mt-6" />
    @endif

    {{-- active filters --}}
    @if ($active)
        <div class="mt-5 flex flex-wrap items-center gap-2">
            <span class="mr-1 text-sm font-semibold">{{ __('Selected filters') }}</span>
            @php
                $chips = [];
                if ($q !== '') $chips[] = ['“'.$q.'”', 'q', ''];
                if ($type) $chips[] = [__($type), 'type', ''];
                foreach ($brands as $b) $chips[] = [$b, 'brand', $b];
                if ($make) $chips[] = [__('Fits :cars', ['cars' => trim($make.' '.$model)]), 'make', ''];
                if ($engine && isset($engines[$engine])) $chips[] = [__('Engine :engine', ['engine' => $engines[$engine]]), 'engine', ''];
                if ($spec) $chips[] = [$spec, 'spec', ''];
                if ($tw || $th || $tr) $chips[] = [__('Size').' '.($tw ?: '…').'/'.($th ?: '…').' R'.($tr ?: '…'), 'size', ''];
                foreach ($f as $fk => $fvals) foreach ((array) $fvals as $fv) $chips[] = [__($fv), 'facet', $fk.'|'.$fv];
                if ($deals) $chips[] = [__('Deals only'), 'deals', ''];
                if ($instock) $chips[] = [__('In stock only'), 'instock', ''];
            @endphp
            @foreach ($chips as [$label, $what, $val])
                <button type="button" wire:click="drop(@js($what), @js($val))" class="flex items-center gap-1.5 rounded-md bg-[#E9EBEE] py-1.5 pl-3 pr-2 text-sm font-medium hover:bg-[#DDE0E4]" aria-label="{{ __('Remove :item', ['item' => $label]) }}">
                    {{ $label }} <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            @endforeach
            <button type="button" wire:click="resetFilters" class="ml-1 text-sm font-semibold text-brand-600 hover:underline">{{ __('Reset') }}</button>
        </div>
    @endif

    {{-- types in the department, with pictures --}}
    @if ($types->count() > 1 && $category !== 'Lubricants')
        <div class="mt-4 flex gap-3 overflow-x-auto pb-1 [scrollbar-width:none]">
            @foreach ($types as $t => $n)
                <button type="button" wire:click="setType(@js($t))" class="flex w-[124px] shrink-0 flex-col items-center rounded-xl border p-3 text-center transition-colors {{ $type === $t ? 'border-[#16181B] bg-white ring-1 ring-[#16181B]' : 'border-transparent bg-[#F1F2F4] hover:border-[#16181B]/30' }}">
                    <img src="{{ \App\Support\ProductArt::dataUri(...\App\Support\ProductArt::forCategory($category, $t)) }}" alt="" class="h-16 w-full object-contain">
                    <span class="mt-2 text-sm font-semibold leading-tight">{{ __($t) }}</span>
                    <span class="text-xs text-[#6B7280]">{{ $n }}</span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- tyre finder: by size or by car --}}
    @if ($tyre)
        @php $seasons = $facets['Season'] ?? []; @endphp
        <section class="mt-5 overflow-hidden rounded-xl border border-[#16181B]/10 bg-white" x-data="{ mode: @js($make ? 'car' : 'size') }">
            <div class="flex overflow-x-auto border-b border-[#16181B]/10 [scrollbar-width:none]" role="tablist" aria-label="{{ __('Vehicle type') }}">
                <button type="button" wire:click="setVehicleType('')" role="tab" aria-selected="{{ $vt === '' ? 'true' : 'false' }}"
                        class="relative shrink-0 px-5 py-4 font-semibold {{ $vt === '' ? 'text-brand-600 after:absolute after:inset-x-0 after:bottom-0 after:h-[3px] after:bg-brand-500' : 'text-[#374151] hover:text-[#16181B]' }}">{{ __('All tyres') }}</button>
                @foreach (\App\Models\Part::TYRE_GROUPS as $key => [$label])
                    <button type="button" wire:click="setVehicleType(@js($key))" role="tab" aria-selected="{{ $vt === $key ? 'true' : 'false' }}"
                            class="relative flex shrink-0 items-center gap-2 px-5 py-4 font-semibold {{ $vt === $key ? 'text-brand-600 after:absolute after:inset-x-0 after:bottom-0 after:h-[3px] after:bg-brand-500' : 'text-[#374151] hover:text-[#16181B]' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            @if ($key === 'car') <path d="M3 15v-3l2-5h11l3 5h1a1 1 0 0 1 1 1v2h-2"/><circle cx="7" cy="16" r="2"/><circle cx="16" cy="16" r="2"/><path d="M9 16h5"/>
                            @elseif ($key === 'suv') <path d="M3 16V9l2-3h10l3 4h2a1 1 0 0 1 1 1v5h-2"/><circle cx="7" cy="17" r="2"/><circle cx="16" cy="17" r="2"/><path d="M9 17h5M7 6v4h11"/>
                            @else <path d="M2 16V6h11v10M13 9h4l3 4v3h-2"/><circle cx="6" cy="17" r="2"/><circle cx="16" cy="17" r="2"/><path d="M8 17h6"/>
                            @endif
                        </svg>
                        {{ __($label) }} <span class="text-xs font-normal text-[#6B7280]">{{ $tyre['groups'][$key] }}</span>
                    </button>
                @endforeach
            </div>
            <div class="grid gap-8 p-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:p-8">
                <div class="hidden flex-col justify-center lg:flex">
                    <p class="mb-3 text-sm text-[#4B5563]">{{ __('The size is written on the side of your tyre:') }} <b>{{ __('width / height R diameter') }}</b>.</p>
                    <x-shop.tyre-size :w="$tw" :h="$th" :r="$tr" class="max-w-md" />
                </div>
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <h2 class="font-display text-[28px] font-bold leading-none">{{ __('Find your tyres') }}</h2>
                        <div class="flex rounded-lg bg-[#F1F2F4] p-1 text-sm font-semibold" role="tablist" aria-label="{{ __('Search tyres') }}">
                            <button type="button" @click="mode = 'size'" :class="mode === 'size' ? 'bg-white shadow-sm text-[#16181B]' : 'text-[#4B5563]'" class="rounded-md px-4 py-2">{{ __('By size') }}</button>
                            <button type="button" @click="mode = 'car'" :class="mode === 'car' ? 'bg-white shadow-sm text-[#16181B]' : 'text-[#4B5563]'" class="rounded-md px-4 py-2">{{ __('By car') }}</button>
                        </div>
                    </div>

                    <div x-show="mode === 'size'" class="mt-5 grid grid-cols-3 gap-3">
                        <label><span class="s-label">{{ __('Width') }}</span>
                            <select wire:model.live="tw" class="s-input"><option value="">{{ __('Any') }}</option>@foreach ($tyre['widths'] as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach</select></label>
                        <label><span class="s-label">{{ __('Height') }}</span>
                            <select wire:model.live="th" class="s-input"><option value="">{{ __('Any') }}</option>@foreach ($tyre['heights'] as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach</select></label>
                        <label><span class="s-label">{{ __('Diameter') }}</span>
                            <select wire:model.live="tr" class="s-input"><option value="">{{ __('Any') }}</option>@foreach ($tyre['rims'] as $v)<option value="{{ $v }}">R{{ $v }}</option>@endforeach</select></label>
                    </div>
                    <div x-show="mode === 'car'" x-cloak class="mt-5 grid grid-cols-2 gap-3">
                        <label><span class="s-label">{{ __('Make') }}</span>
                            <select wire:model.live="make" class="s-input"><option value="">{{ __('Any make') }}</option>@foreach ($tyre['makes'] as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach</select></label>
                        <label><span class="s-label">{{ __('Model') }}</span>
                            <input wire:model.live.debounce.400ms="model" class="s-input" placeholder="{{ __('e.g. Prado') }}" @disabled(! $make)></label>
                    </div>

                    @if ($seasons)
                        <p class="s-label mt-5">{{ __('Season') }}</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach (['Summer', 'All season', 'Winter'] as $sv)
                                @continue(! isset($seasons[$sv]) && ! in_array($sv, $f['Season'] ?? [], true))
                                @php $on = in_array($sv, $f['Season'] ?? [], true); @endphp
                                <button type="button" wire:click="toggleFacet('Season', @js($sv))" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                        class="flex items-center gap-3 border px-3 py-2.5 text-left transition-colors {{ $on ? 'border-[#16181B] ring-1 ring-[#16181B]' : 'border-[#16181B]/15 hover:border-[#16181B]/40' }}">
                                    <svg class="h-7 w-7 shrink-0" viewBox="0 0 32 32" fill="none" stroke-width="2" aria-hidden="true">
                                        @if ($sv !== 'Winter') <circle cx="{{ $sv === 'Summer' ? 16 : 12 }}" cy="{{ $sv === 'Summer' ? 16 : 12 }}" r="5" fill="#F5B301" stroke="#F5B301"/>
                                            @foreach (range(0, 315, 45) as $a)<line x1="{{ ($sv === 'Summer' ? 16 : 12) + 8 * cos(deg2rad($a)) }}" y1="{{ ($sv === 'Summer' ? 16 : 12) + 8 * sin(deg2rad($a)) }}" x2="{{ ($sv === 'Summer' ? 16 : 12) + 11 * cos(deg2rad($a)) }}" y2="{{ ($sv === 'Summer' ? 16 : 12) + 11 * sin(deg2rad($a)) }}" stroke="#F5B301" stroke-linecap="round"/>@endforeach
                                        @endif
                                        @if ($sv !== 'Summer') @php $cx = $sv === 'Winter' ? 16 : 21; $cy = $sv === 'Winter' ? 16 : 21; $rr = $sv === 'Winter' ? 11 : 8; @endphp
                                            @foreach ([0, 60, 120] as $a)<line x1="{{ $cx - $rr * cos(deg2rad($a)) }}" y1="{{ $cy - $rr * sin(deg2rad($a)) }}" x2="{{ $cx + $rr * cos(deg2rad($a)) }}" y2="{{ $cy + $rr * sin(deg2rad($a)) }}" stroke="#3B82F6" stroke-linecap="round"/>@endforeach
                                        @endif
                                    </svg>
                                    <span class="min-w-0"><span class="block text-sm font-semibold leading-tight">{{ __($sv) }}</span><span class="text-xs text-[#6B7280]">{{ trans_choice(':count tyre|:count tyres', $seasons[$sv] ?? 0) }}</span></span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-5 grid gap-3 sm:grid-cols-[1fr_auto]">
                        <label><span class="s-label">{{ __('Manufacturer') }}</span>
                            <select class="s-input" wire:change="$set('brands', $event.target.value ? [$event.target.value] : [])">
                                <option value="">{{ __('All manufacturers') }}</option>
                                @foreach ($brandList as $b => $n)<option value="{{ $b }}" @selected($brands === [$b])>{{ $b }} ({{ $n }})</option>@endforeach
                            </select></label>
                        <a href="#results" class="s-btn-red self-end px-8 py-3.5">{{ trans_choice('Show :count tyre|Show :count tyres', $parts->total()) }}</a>
                    </div>
                    <p class="mt-3 text-sm text-[#4B5563]">{{ __('Size not here?') }} <button type="button" class="font-semibold text-brand-600 hover:underline" @click="Livewire.dispatch('open-car-request', { parts: @js('Tyres '.trim(($tw ?: '').'/'.($th ?: '').' R'.($tr ?: ''), '/ R')) })">{{ __('Ask us to get it') }}</button></p>
                </div>
            </div>
        </section>
    @endif

    {{-- shop by brand --}}
    @if ($brandStrip->count() > 1 && ($category || $q !== ''))
        <div class="mt-6">
            <p class="mb-2 text-sm font-semibold">{{ $category === 'Tyres' || $category === 'Lubricants' ? __('Manufacturers') : __(':what by brand', ['what' => $type ? __($type) : ($category ? __($category) : __('Parts'))]) }}</p>
            <x-shop.scroller :label="__('Brands')">
                @foreach ($brandStrip as $b => $n)
                    @php $on = in_array($b, $brands, true); @endphp
                    <button type="button" wire:click="toggleBrand(@js($b))" wire:key="br{{ md5($b) }}"
                            class="flex h-[72px] w-[132px] shrink-0 snap-start flex-col items-center justify-center rounded-xl border px-2 text-center transition-colors {{ $on ? 'border-[#16181B] bg-white ring-1 ring-[#16181B]' : 'border-transparent bg-[#F1F2F4] hover:border-[#16181B]/30' }}" aria-pressed="{{ $on ? 'true' : 'false' }}">
                        <span class="w-full truncate font-display text-xl font-extrabold leading-none tracking-tight">{{ $b }}</span>
                        <span class="mt-1 text-xs text-[#6B7280]">{{ trans_choice(':count product|:count products', $n) }}</span>
                    </button>
                @endforeach
            </x-shop.scroller>
        </div>
    @endif

    {{-- quick spec filter (oil viscosity, tyre size) --}}
    @if ($chipKey && count($chipValues) > 1)
        <div class="mt-5">
            <p class="text-sm font-semibold">{{ $category === 'Lubricants' ? __('SAE viscosity grade') : __($chipKey) }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($chipValues as $v => $n)
                    <button type="button" wire:click="setSpec(@js($v))" aria-pressed="{{ $spec === $v ? 'true' : 'false' }}"
                            class="min-w-[124px] rounded-xl border px-4 py-3 text-left transition-colors {{ $spec === $v ? 'border-[#16181B] bg-[#16181B] text-white' : 'border-transparent bg-[#F1F2F4] hover:border-[#16181B]/30' }}">
                        @if ($category === 'Lubricants')
                            <span class="flex items-center gap-1 text-xs font-semibold {{ $spec === $v ? 'text-white/70' : 'text-[#6B7280]' }}">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11Z"/></svg> SAE
                            </span>
                        @endif
                        <span class="block font-display text-[26px] font-bold leading-none">{{ $v }}</span>
                        <span class="text-xs {{ $spec === $v ? 'text-white/70' : 'text-[#6B7280]' }}">{{ trans_choice(':count product|:count products', $n) }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-6 grid gap-8 lg:grid-cols-[250px_1fr]">
        {{-- filters --}}
        <aside x-data="{ more: false }" class="lg:sticky lg:top-36 lg:self-start">
            <label for="q-cat" class="s-label">{{ __('Search') }}</label>
            <input id="q-cat" wire:model.live.debounce.350ms="q" type="search" class="s-input" placeholder="{{ __('Name, part number, car…') }}">
            <button type="button" class="s-btn-line mt-3 w-full lg:hidden" @click="more = !more" :aria-expanded="more" x-text="more ? @js(__('Hide filters')) : @js(__('More filters'))">{{ __('More filters') }}</button>
            <div class="mt-5 space-y-6" :class="more ? '' : 'hidden lg:block'">
                @if ($q !== '' && $departments->count() > 1)
                    <fieldset class="border-t border-[#16181B]/10 pt-5">
                        <legend class="s-label">{{ __('Department') }}</legend>
                        <div class="space-y-2">
                            @foreach ($departments->sortBy(fn ($n, $d) => array_search($d, \App\Models\Part::shopDepartments(), true)) as $dep => $n)
                                <label class="flex items-center gap-2.5 text-[15px]">
                                    <input type="radio" name="dep" @checked($category === $dep) wire:click="setCategory(@js($dep))" class="h-4 w-4 border-[#16181B]/30 text-[#16181B] focus:ring-[#16181B]">
                                    <span class="flex-1">{{ __($dep) }}</span><span class="text-sm text-[#6B7280]">{{ $n }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                <div class="space-y-2.5 border-t border-[#16181B]/10 pt-5">
                    <label class="flex items-center gap-2.5 text-[15px]"><input type="checkbox" wire:model.live="deals" class="h-4 w-4 rounded border-[#16181B]/30 text-brand-500 focus:ring-brand-500"> {{ __('Deals only') }}</label>
                    <label class="flex items-center gap-2.5 text-[15px]"><input type="checkbox" wire:model.live="instock" class="h-4 w-4 rounded border-[#16181B]/30 text-[#16181B] focus:ring-[#16181B]"> {{ __('In stock only') }}</label>
                </div>
                @if ($makes->isNotEmpty())
                    <div class="border-t border-[#16181B]/10 pt-5">
                        <label for="make" class="s-label">{{ __('Fits car make') }}</label>
                        <select id="make" wire:model.live="make" class="s-input">
                            <option value="">{{ __('Any make') }}</option>
                            @foreach ($makes as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                        </select>
                        @if ($make)
                            <label for="model" class="s-label mt-3">{{ __('Model') }}</label>
                            <select id="model" wire:model.live="model" class="s-input">
                                <option value="">{{ __('Any :make model', ['make' => $make]) }}</option>
                                @foreach ($carModels as $cm) <option value="{{ $cm }}">{{ $cm }}</option> @endforeach
                                @if ($model && ! in_array($model, $carModels, true)) <option value="{{ $model }}">{{ $model }}</option> @endif
                            </select>
                        @endif
                        @if ($make && $model && $engines)
                            <label for="engine" class="s-label mt-3">{{ __('Engine') }}</label>
                            <select id="engine" wire:model.live="engine" class="s-input">
                                <option value="">{{ __('Any engine') }}</option>
                                @foreach ($engines as $id => $label) <option value="{{ $id }}">{{ $label }}</option> @endforeach
                            </select>
                        @endif
                        <button type="button" class="mt-2 text-sm font-semibold text-brand-600 hover:underline" @click="Livewire.dispatch('open-car-request', { make: @js($make), model: @js($model) })">{{ __('Can\'t find your car?') }}</button>
                    </div>
                @endif
                @foreach ($facets as $fkey => $fvals)
                    @continue($tyre && $fkey === 'Season')
                    <fieldset class="border-t border-[#16181B]/10 pt-5" x-data="{ open: {{ $loop->index < 2 || ! empty($f[$fkey]) ? 'true' : 'false' }}, all: false }" wire:key="facet-{{ md5($fkey) }}">
                        <legend class="w-full">
                            <button type="button" @click="open = !open" class="flex w-full items-center justify-between text-left font-semibold" :aria-expanded="open">
                                {{ $fkey === 'Capacity' ? __('Capacity (litres)') : __($fkey) }}
                                <svg class="h-4 w-4 transition-transform" :class="open || 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m6 15 6-6 6 6"/></svg>
                            </button>
                        </legend>
                        <div x-show="open" class="mt-3 space-y-2">
                            @foreach ($fvals as $fv => $n)
                                @php $on = in_array((string) $fv, $f[$fkey] ?? [], true); @endphp
                                <label class="flex items-center gap-2.5 text-[15px]" @if ($loop->index >= 6 && ! $on) x-show="all" x-cloak @endif>
                                    <input type="checkbox" @checked($on) wire:click="toggleFacet(@js($fkey), @js((string) $fv))" class="h-4 w-4 rounded border-[#16181B]/30 text-[#16181B] focus:ring-[#16181B]">
                                    <span class="flex-1">{{ \App\Models\Part::specDisplay($fkey, (string) $fv) }}</span><span class="text-sm text-[#6B7280]">{{ $n }}</span>
                                </label>
                            @endforeach
                            @if (count($fvals) > 6)
                                <button type="button" class="text-sm font-semibold text-brand-600 hover:underline" @click="all = !all" x-text="all ? @js(__('Show fewer')) : @js(__('Show all :n', ['n' => count($fvals)]))">{{ __('Show all :n', ['n' => count($fvals)]) }}</button>
                            @endif
                        </div>
                    </fieldset>
                @endforeach
                @if ($brandList->isNotEmpty())
                    <fieldset class="border-t border-[#16181B]/10 pt-5">
                        <legend class="s-label">{{ __('Brand') }}</legend>
                        <div class="max-h-64 space-y-2 overflow-y-auto pr-1">
                            @foreach ($brandList as $b => $n)
                                <label class="flex items-center gap-2.5 text-[15px]">
                                    <input type="checkbox" wire:model.live="brands" value="{{ $b }}" class="h-4 w-4 rounded border-[#16181B]/30 text-[#16181B] focus:ring-[#16181B]">
                                    <span class="flex-1">{{ $b }}</span><span class="text-sm text-[#6B7280]">{{ $n }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                @if ($filtered)
                    <button type="button" wire:click="clearFilters" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('Clear all filters') }}</button>
                @endif
            </div>
        </aside>

        {{-- results --}}
        <div id="results" wire:loading.class="opacity-60" class="min-w-0 scroll-mt-40 transition-opacity">
            @if ($parts->isEmpty())
                <div class="border border-dashed border-[#16181B]/25 px-6 py-16 text-center">
                    <p class="text-lg font-semibold">{{ __('No parts match that search.') }}</p>
                    <p class="mt-1 text-[#4B5563]">{{ __('Try a shorter word or a part number. We may still be able to get it for you.') }}</p>
                    <div class="mt-5 flex flex-wrap justify-center gap-3">
                        <button type="button" class="s-btn-red" @click="Livewire.dispatch('open-car-request', { make: @js($make), model: @js($model), parts: @js($q) })">{{ __('Ask us to find it') }}</button>
                        @if ($filtered) <button type="button" wire:click="clearFilters" class="s-btn-line">{{ __('Show all parts') }}</button> @endif
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($parts as $p) <x-shop.part-row :part="$p" :family="$families[$p->variant_of ?: $p->id] ?? null" wire:key="p{{ $p->id }}" /> @endforeach
                </div>
                @if ($parts->hasPages()) <div class="mt-8">{{ $parts->links() }}</div> @endif
            @endif
        </div>
    </div>
</div>
