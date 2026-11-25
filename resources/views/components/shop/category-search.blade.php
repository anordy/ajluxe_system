{{-- Quick search by category: a searchable A–Z list of part types on the left, department tiles on the right --}}
@php
    use App\Models\Part;
    use App\Support\ProductArt;
    $rows = Part::online()->listed()->whereNotNull('category')->where('category', '!=', '')
        ->selectRaw('category, subcategory, count(*) as n')->groupBy('category', 'subcategory')->get()->toBase();
    $deps = collect(Part::shopDepartments())->mapWithKeys(fn ($d) => [$d => (int) $rows->where('category', $d)->sum('n')])->filter();
    $items = $rows->filter(fn ($r) => filled($r->subcategory) && isset($deps[$r->category]))
        ->map(fn ($r) => ['name' => __($r->subcategory), 'dep' => __($r->category), 'n' => (int) $r->n, 'c' => $r->category, 't' => $r->subcategory,
            'url' => route('shop.catalog', ['category' => $r->category, 'type' => $r->subcategory]),
            'img' => ProductArt::dataUri(...ProductArt::forCategory($r->category, $r->subcategory))])
        ->merge($deps->map(fn ($n, $d) => ['name' => __($d), 'dep' => 'Department', 'n' => $n, 'c' => $d, 't' => null, 'url' => route('shop.catalog', ['category' => $d]),
            'img' => ProductArt::dataUri(...ProductArt::forCategory($d))])->values())
        ->sortBy(fn ($i) => mb_strtolower($i['name']))->values();
@endphp
@if ($deps->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'grid gap-6 lg:grid-cols-[360px_1fr]']) }}
         x-init="$watch('q', () => { clearTimeout(t); t = setTimeout(() => send(), 300) })"
         x-data="{ q: '', t: null, items: @js($items),
                   get shown() { const q = this.q.trim().toLowerCase(); return q ? this.items.filter(i => i.name.toLowerCase().includes(q) || i.dep.toLowerCase().includes(q)) : this.items },
                   send() { Livewire.dispatch('category-filter', { q: this.q, cats: this.q.trim() ? this.shown.map(i => [i.c, i.t]) : [] }) },
                   letter(i) { const l = this.shown[i].name.charAt(0).toUpperCase(); return i === 0 || this.shown[i - 1].name.charAt(0).toUpperCase() !== l ? l : '' } }">
        {{-- searchable list --}}
        <div class="flex flex-col overflow-hidden rounded-xl border border-[#16181B]/10 bg-white">
            <div class="border-b border-[#16181B]/10 p-4">
                <label for="cat-q" class="text-sm font-semibold">{{ __('Quick search by category') }}</label>
                <div class="relative mt-2">
                    <input id="cat-q" type="search" x-model="q" autocomplete="off" placeholder="{{ __('e.g. brakes, filters, oil') }}" class="s-input pr-10">
                    <svg class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-[#6B7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </div>
            </div>
            <div class="relative flex-1 lg:min-h-[360px]">
            <ul class="max-h-[380px] overflow-y-auto lg:absolute lg:inset-0 lg:max-h-none py-1 [scrollbar-width:thin]">
                <template x-for="(it, i) in shown" :key="it.dep + it.name">
                    <li>
                        <p x-show="letter(i)" x-text="letter(i)" class="bg-[#F7F8F9] px-4 py-1.5 text-xs font-bold text-[#6B7280]"></p>
                        <a :href="it.url" wire:navigate class="flex items-center gap-3 px-4 py-2 hover:bg-[#FDF2F2]">
                            <img :src="it.img" alt="" class="h-9 w-9 shrink-0 object-contain">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[15px] font-medium" x-text="it.name"></span>
                                <span class="block text-xs text-[#6B7280]" x-text="it.dep === 'Department' ? {{ Js::from(__('All')) }} + ' ' + it.n + ' ' + {{ Js::from(__('products')) }} : it.dep"></span>
                            </span>
                            <span class="text-xs text-[#9CA3AF]" x-text="it.n"></span>
                        </a>
                    </li>
                </template>
                <li x-show="shown.length === 0" class="px-4 py-8 text-center text-sm text-[#6B7280]">
                    {{ __('No category called “') }}<span x-text="q"></span>”.
                    <button type="button" class="mt-1 block w-full font-semibold text-brand-600 hover:underline" @click="Livewire.dispatch('open-car-request', { parts: q })">{{ __('Ask us to find it') }}</button>
                </li>
            </ul>
            </div>
        </div>

        {{-- random products; they change to the matching ones as the customer types --}}
        <livewire:shop.category-products :key="'cat-products'" />
    </div>
@endif
