<div class="space-y-5">
    {{-- summary --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-4"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Active parts</p><p class="mt-1 text-2xl font-extrabold">{{ number_format($totals->items ?? 0) }}</p></div>
        <button wire:click="$set('stock', 'low')" class="card p-4 text-left hover:border-brand-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Low stock</p>
            <p class="mt-1 text-2xl font-extrabold {{ $lowCount ? 'text-brand-600' : '' }}">{{ number_format($lowCount) }}</p>
        </button>
        @if ($this->canSeeCost())
            <div class="card p-4"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Stock value (at cost)</p><p class="mt-1 text-2xl font-extrabold">{{ money($totals->value ?? 0) }}</p></div>
        @endif
    </div>

    {{-- filters --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative w-full lg:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Search name, part code, OEM no, brand, car…">
        </div>
        <select wire:model.live="category" class="input lg:max-w-[190px]">
            <option value="">All categories</option>
            @foreach ($categories as $c) <option>{{ $c }}</option> @endforeach
        </select>
        <select wire:model.live="stock" class="input lg:max-w-[150px]">
            <option value="">Any stock</option><option value="low">Low stock</option><option value="out">Out of stock</option><option value="sourced">From suppliers</option>
        </select>
        <select wire:model.live="status" class="input lg:max-w-[130px]">
            <option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All</option>
        </select>
        @if ($this->canManage())
            <button class="btn-primary lg:ml-auto" wire:click="create"><x-icon name="plus" class="h-4 w-4" /> New Part</button>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr>
                    <th>Part</th><th>Category</th><th>Fits</th><th>Location</th><th class="text-right">In stock</th>
                    @if ($this->canSeeCost()) <th class="text-right">Cost</th> @endif
                    <th class="text-right">Price</th><th class="text-right">Actions</th>
                </tr></thead>
                <tbody>
                @forelse ($parts as $p)
                    <tr wire:key="p{{ $p->id }}" class="{{ $p->is_active ? '' : 'opacity-60' }}">
                        <td>
                            <div class="flex items-center gap-3">
                                @if ($p->imageUrl())
                                    <img src="{{ $p->thumbUrl() }}" loading="lazy" class="h-10 w-10 rounded-lg border border-ink-100 object-cover" alt="">
                                @else
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-ink-100 text-ink-400"><x-icon name="box" class="h-5 w-5" /></span>
                                @endif
                                <div>
                                    <div class="font-semibold text-ink-900">{{ $p->name }}</div>
                                    <div class="font-mono text-[11px] text-ink-400">{{ $p->sku }}@if ($p->oem_no) · OEM {{ $p->oem_no }}@endif @if ($p->brand) · {{ $p->brand }}@endif</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $p->category ?: '—' }}</td>
                        <td class="max-w-[220px] truncate text-xs" title="{{ $p->fitsText(20) }}">{{ $p->fitsText(2) ?: '—' }}</td>
                        <td class="font-mono text-xs">{{ $p->rack_location ?: '—' }}</td>
                        <td class="text-right">
                            <button wire:click="history({{ $p->id }})" title="Stock history"
                                    class="badge {{ (float) $p->stock_qty <= 0 && $p->sourced ? 'bg-sky-50 text-sky-700' : ((float) $p->stock_qty <= 0 ? 'bg-brand-500 text-white' : ($p->isLow() ? 'bg-brand-50 text-brand-700' : 'bg-emerald-50 text-emerald-700')) }}">
                                {{ rtrim(rtrim(number_format((float) $p->stock_qty, 2), '0'), '.') }} {{ $p->unit }}
                            </button>
                            @if ($p->sourced) <div class="mt-0.5 text-[10px] font-semibold text-sky-700" >{{ $p->sourceSupplier?->name ?? 'supplier' }}</div> @endif
                        </td>
                        @if ($this->canSeeCost()) <td class="text-right text-ink-500">{{ amount($p->purchase_price) }}</td> @endif
                        <td class="text-right font-semibold">{{ amount($p->selling_price) }}
                            @if (! $p->show_online) <div class="text-[10px] font-normal text-ink-400">not on website</div>
                            @elseif ($p->hasDiscount()) <div class="text-[10px] font-bold text-brand-600">web −{{ $p->discountShown() }}%</div> @endif</td>
                        <td class="whitespace-nowrap text-right">
                            <button class="btn-icon" title="View" wire:click="view({{ $p->id }})"><x-icon name="eye" class="h-4 w-4" /></button>
                            @if ($this->canManage())
                                <button class="btn-icon" title="Edit" wire:click="edit({{ $p->id }})"><x-icon name="pencil" class="h-4 w-4" /></button>
                                <button class="btn-icon" title="{{ $p->is_active ? 'Deactivate' : 'Activate' }}" wire:click="toggleActive({{ $p->id }})"><x-icon name="{{ $p->is_active ? 'x' : 'check' }}" class="h-4 w-4" /></button>
                                <button class="btn-icon hover:!text-brand-600" title="Delete" wire:click="delete({{ $p->id }})" wire:confirm="Delete {{ $p->name }}?"><x-icon name="trash" class="h-4 w-4" /></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-12 text-center text-ink-400">
                        No parts found. @if ($this->canManage() && ! $search) <button wire:click="create" class="font-semibold text-brand-600 hover:underline">Add the first part</button> @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($parts->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $parts->links() }}</div> @endif
    </div>
    <p class="text-xs text-ink-400">Prices in {{ setting('currency') }}. Click a stock figure to see its history. Stock changes through sales, purchases, job cards and adjustments; see the Stock Register for all movements and adjustments.</p>

    {{-- add / edit --}}
    <x-modal show="showForm" :title="$editingId ? 'Edit Part' : 'New Part'" max-width="max-w-3xl">
        <form wire:submit="save" id="partForm" class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="label">Part name *</label>
                <input wire:model="form.name" class="input" placeholder="e.g. Oil Filter">
                @error('form.name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Part code / SKU</label>
                <input wire:model="form.sku" class="input font-mono uppercase" placeholder="Auto if empty">
                @error('form.sku') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">OEM / part number</label>
                <input wire:model="form.oem_no" class="input font-mono" placeholder="90915-YZZE1">
            </div>
            <div>
                <label class="label">Brand</label>
                <input wire:model="form.brand" list="part-brands" class="input">
                <datalist id="part-brands">@foreach ($brands as $b) <option value="{{ $b }}"> @endforeach</datalist>
            </div>
            <div>
                <label class="label">Category</label>
                <select wire:model="form.category" class="input">
                    <option value="">—</option>
                    @foreach ($categories as $c) <option>{{ $c }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Type <span class="font-normal normal-case text-ink-400">(e.g. Brake Pads)</span></label>
                <input wire:model="form.subcategory" list="part-types" class="input">
                <datalist id="part-types">@foreach (\App\Models\Part::whereNotNull('subcategory')->distinct()->orderBy('subcategory')->pluck('subcategory') as $t) <option value="{{ $t }}"> @endforeach</datalist>
            </div>
            <div>
                <label class="label">Fits make <span class="font-normal normal-case text-ink-400">(free text)</span></label>
                <input wire:model="form.vehicle_make" list="part-makes" class="input">
                <datalist id="part-makes">@foreach ($makes as $m) <option value="{{ $m }}"> @endforeach</datalist>
            </div>
            <div>
                <label class="label">Fits model(s)</label>
                <input wire:model="form.vehicle_model" class="input" placeholder="Corolla, Premio, Allion">
            </div>
            <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-4 sm:col-span-3">
                <p class="text-sm font-semibold text-ink-900">Fits these cars</p>
                <p class="text-xs text-ink-500">Customers who choose one of these cars on the website will find this part. The website shows make and model; engines are for staff.</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_1fr_1.3fr_auto]">
                    <select wire:model.live="fitMake" class="input" aria-label="Make">
                        <option value="">Make</option>
                        @foreach ($fitMakes as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                    </select>
                    <select wire:model.live="fitModel" class="input" aria-label="Model" @disabled(! $fitMake)>
                        <option value="">Model</option>
                        @foreach ($fitModels as $m) <option value="{{ $m->id }}">{{ $m->model }}</option> @endforeach
                    </select>
                    <select wire:model="fitEngine" class="input" aria-label="Engine" @disabled(! $fitModel)>
                        <option value="">All engines</option>
                        @foreach ($fitEngines as $e) <option value="{{ $e->id }}">{{ $e->label() }}</option> @endforeach
                    </select>
                    <button type="button" wire:click="addFit" class="btn-light" @disabled(! $fitModel)><x-icon name="plus" class="h-4 w-4" /> Add</button>
                </div>
                @error('fitModel') <p class="error">{{ $message }}</p> @enderror
                @if ($fitList)
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($fitList as $i => $label)
                            @continue(! $label)
                            <li class="flex items-center gap-1 rounded-full bg-white py-1 pl-3 pr-1 text-sm ring-1 ring-ink-200" wire:key="fit{{ $i }}">
                                {{ $label }}
                                <button type="button" wire:click="removeFit({{ $i }})" class="rounded-full p-1 text-ink-400 hover:bg-ink-100 hover:text-brand-600" aria-label="Remove {{ $label }}"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-xs text-ink-400">No cars linked yet. Cars missing from the lists can be added in Website › Car List.</p>
                @endif
            </div>
            <div>
                <label class="label">Rack / shelf</label>
                <input wire:model="form.rack_location" class="input font-mono" placeholder="A-03">
            </div>

            <div>
                <label class="label">Buying price</label>
                <input type="number" min="0" step="any" wire:model="form.purchase_price" class="input">
                @error('form.purchase_price') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Selling price</label>
                <input type="number" min="0" step="any" wire:model="form.selling_price" class="input">
                @error('form.selling_price') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Minimum selling price</label>
                <input type="number" min="0" step="any" wire:model="form.min_selling_price" class="input" placeholder="Lowest price staff may sell at">
                @error('form.min_selling_price') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Unit</label>
                    <select wire:model="form.unit" class="input">@foreach ($units as $u) <option>{{ $u }}</option> @endforeach</select>
                </div>
                <div>
                    <label class="label">VAT %</label>
                    <input type="number" min="0" max="100" step="any" wire:model="form.tax_rate" class="input" placeholder="0">
                </div>
            </div>
            <div>
                <label class="label">Re-order level</label>
                <input type="number" min="0" step="any" wire:model="form.reorder_level" class="input" placeholder="Alert when stock falls to…">
            </div>
            @unless ($editingId)
                <div>
                    <label class="label">Opening stock</label>
                    @if ($form['sourced'] ?? false)
                        <p class="input flex items-center bg-ink-50 text-sm text-ink-500">0 · bought from the supplier when sold</p>
                    @else
                    <input type="number" min="0" step="any" wire:model="opening_qty" class="input" placeholder="Quantity on the shelf now">
                    @endif
                    @error('opening_qty') <p class="error">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <label class="label">In stock</label>
                    <div class="input bg-ink-50 text-ink-500">Changed by purchases, sales & adjustments</div>
                </div>
            @endunless
            <label class="flex items-center gap-2 self-end pb-2 text-sm text-ink-700">
                <input type="checkbox" wire:model="form.is_active" class="rounded border-ink-300 text-brand-500 focus:ring-brand-400"> Active
            </label>

            {{-- bought from a supplier when sold --}}
            <div class="rounded-xl border border-sky-100 bg-sky-50/60 p-4 sm:col-span-3">
                <label class="flex items-start gap-2 text-sm text-ink-800">
                    <input type="checkbox" wire:model.live="form.sourced" class="mt-0.5 rounded border-ink-300 text-brand-500 focus:ring-brand-400">
                    <span><b>Buy from supplier when sold</b><br><span class="text-ink-500">Shown on the website as in stock even when we hold none. When it sells, the missing quantity is bought from this supplier automatically (purchase order + what we owe them), so stock value only counts what we really hold.</span></span>
                </label>
                @if ($form['sourced'] ?? false)
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Supplier</label>
                            <select wire:model="form.source_supplier_id" class="input">
                                <option value="">Pick a supplier…</option>
                                @foreach ($suppliers as $sup) <option value="{{ $sup->id }}">{{ $sup->name }}</option> @endforeach
                            </select>
                            @error('form.source_supplier_id') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <p class="self-end pb-2 text-xs text-ink-500">Put <b>their price to you</b> in "Buying price" above and <b>your price to customers</b> in "Selling price".</p>
                    </div>
                @endif
            </div>

            {{-- website --}}
            <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-4 sm:col-span-3" x-data>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-bold text-ink-900">Online shop</p>
                    <label class="flex items-center gap-2 text-sm text-ink-700">
                        <input type="checkbox" wire:model.live="form.show_online" class="rounded border-ink-300 text-brand-500 focus:ring-brand-400"> Show on the website
                    </label>
                </div>
                @if ($form['show_online'] ?? true)
                    @php
                        $sp = (float) (($form['selling_price'] ?? '') ?: 0); $dp = (float) (($form['discount_percent'] ?? '') ?: 0); $mn = (float) (($form['min_selling_price'] ?? '') ?: 0);
                        $web = $sp > 0 ? max(round($sp * (1 - $dp / 100)), $mn) : 0;
                    @endphp
                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="label">Website discount %</label>
                            <input type="number" min="0" max="90" step="any" wire:model.live.debounce.400ms="form.discount_percent" class="input" placeholder="0">
                            @error('form.discount_percent') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Ribbon on the picture</label>
                            <select wire:model="form.ribbon" class="input">
                                <option value="">Automatic</option>
                                @foreach (\App\Models\Part::RIBBONS as $rk => $rl) <option value="{{ $rk }}">{{ $rl }}</option> @endforeach
                                <option value="none">No ribbon</option>
                            </select>
                            <p class="mt-1 text-xs text-ink-400">Automatic: Sold out → New (first 12 h) → Sale (with a discount)</p>
                        </div>
                        <div>
                            <label class="label">Price customers see</label>
                            <div class="input bg-white font-semibold">
                                @if ($sp <= 0) <span class="font-normal text-ink-400">Set a selling price — parts without one are hidden</span>
                                @else {{ money($web) }} @if ($web < $sp) <span class="ml-1 text-ink-400 line-through">{{ amount($sp) }}</span> @endif
                                    @if ($dp > 0 && $mn > 0 && $web === $mn) <span class="ml-1 text-xs font-normal text-amber-700">limited by the minimum price</span> @endif
                                @endif
                            </div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="label">Specifications <span class="font-normal normal-case text-ink-400">— one per line, e.g. “Size: 265/65 R17” or “Viscosity: 5W-30”</span></label>
                            <textarea wire:model="form.specs_text" rows="3" class="input font-mono text-sm" placeholder="Viscosity: 5W-30&#10;Capacity: 4 L"></textarea>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="label">Same product in another size <span class="font-normal normal-case text-ink-400">— e.g. this is the 1L of a 4L oil: pick the 4L. The website shows them as one product with size buttons.</span></label>
                            <select wire:model="form.variant_of" class="input">
                                <option value="">— Not another size —</option>
                                @foreach (\App\Models\Part::whereNull('variant_of')->where('id', '!=', $editingId ?? 0)->when($form['category'] ?? '', fn ($q, $c) => $q->where('category', $c))->orderBy('name')->get(['id', 'name', 'sku']) as $vp)
                                    <option value="{{ $vp->id }}">{{ $vp->name }} ({{ $vp->sku }})</option>
                                @endforeach
                            </select>
                            <p class="hint">Write the pack size in Specifications as “Capacity: 1 L” so customers see the price per litre.</p>
                            @error('form.variant_of') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label class="label">Description for customers</label>
                            <textarea wire:model="form.web_description" rows="2" class="input" placeholder="What it is, what it fits, warranty…"></textarea>
                            @error('form.web_description') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @else
                    <p class="mt-1 text-xs text-ink-500">Hidden from customers. Staff can still sell it in the shop.</p>
                @endif
            </div>

            <div class="sm:col-span-3">
                <label class="label">Photo</label>
                <x-photo-input model="photoData" reset="part-form-opened" :current="$editingId ? \App\Models\Part::find($editingId)?->imageUrl() : null" />
                @error('photoData') <p class="error">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="partForm" class="btn-primary" wire:loading.attr="disabled">Save Part</button>
        </x-slot:footer>
    </x-modal>

    {{-- stock history --}}
    {{-- view --}}
    <x-modal show="showView" :title="$viewPart?->name ?? 'Part'" max-width="max-w-3xl">
        @if ($p = $viewPart)
            <div class="grid gap-6 sm:grid-cols-[180px_1fr]">
                <div>
                    <img src="{{ $p->pictureUrl() }}" alt="" class="aspect-square w-full rounded-xl border border-ink-100 bg-ink-50 {{ $p->imageUrl() && ! str_ends_with((string) $p->image_path, '.svg') ? 'object-cover' : 'object-contain p-3' }}">
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @if ($p->show_online) <span class="badge-green">On website</span> @else <span class="badge-gray">Not on website</span> @endif
                        @if ($p->sourced) <span class="badge bg-sky-50 text-sky-700">Supplier: {{ $p->sourceSupplier?->name ?? '—' }}</span> @endif
                        @if (! $p->is_active) <span class="badge-gray">Inactive</span> @endif
                        @if ($p->hasDiscount()) <span class="badge-red">Web −{{ $p->discountShown() }}%</span> @endif
                    </div>
                </div>
                <div class="space-y-5 text-sm">
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                        <div><dt class="text-xs text-ink-400">Code</dt><dd class="font-mono">{{ $p->sku }}</dd></div>
                        <div><dt class="text-xs text-ink-400">Part number</dt><dd class="font-mono">{{ $p->oem_no ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-400">Brand</dt><dd>{{ $p->brand ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-400">Category</dt><dd>{{ $p->category ?: '—' }}{{ $p->subcategory ? ' › '.$p->subcategory : '' }}</dd></div>
                        <div><dt class="text-xs text-ink-400">In stock</dt><dd class="font-semibold">{{ rtrim(rtrim(number_format((float) $p->stock_qty, 2), '0'), '.') }} {{ $p->unit }}</dd></div>
                        <div><dt class="text-xs text-ink-400">Rack</dt><dd class="font-mono">{{ $p->rack_location ?: '—' }}</dd></div>
                        @if ($this->canSeeCost()) <div><dt class="text-xs text-ink-400">Cost</dt><dd>{{ money($p->purchase_price) }}</dd></div> @endif
                        <div><dt class="text-xs text-ink-400">Selling price</dt><dd class="font-semibold">{{ money($p->selling_price) }}</dd></div>
                        <div><dt class="text-xs text-ink-400">Website price</dt><dd>{{ money($p->salePrice()) }}</dd></div>
                    </dl>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Fits</p>
                        @if ($p->fitments->isNotEmpty())
                            <ul class="mt-1.5 flex flex-wrap gap-1.5">
                                @foreach ($p->fitments as $f) <li class="rounded-full bg-ink-50 px-2.5 py-1 ring-1 ring-ink-100">{{ $f->label() }}</li> @endforeach
                            </ul>
                        @endif
                        @if ($p->vehicle_make || $p->vehicle_model) <p class="mt-1.5 text-ink-600">{{ trim($p->vehicle_make.' '.$p->vehicle_model) }} <span class="text-ink-400">(free text)</span></p> @endif
                        @if ($p->fitments->isEmpty() && ! $p->vehicle_make && ! $p->vehicle_model) <p class="mt-1 text-ink-400">No cars linked yet.</p> @endif
                    </div>
                    @if ($specs = $p->specList())
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Specifications</p>
                            <dl class="mt-1.5 grid grid-cols-2 gap-x-6 gap-y-2">
                                @foreach ($specs as $k => $v) <div><dt class="text-xs text-ink-400">{{ $k }}</dt><dd>{{ $v }}</dd></div> @endforeach
                            </dl>
                        </div>
                    @endif
                    @if ($p->variants->isNotEmpty() || $p->main)
                        <p class="text-ink-600">Other sizes: {{ ($p->main ? collect([$p->main]) : $p->variants)->pluck('name')->implode(', ') }}</p>
                    @endif
                </div>
            </div>
        @endif
        <x-slot:footer>
            @if ($viewPart)
                @if ($viewPart->show_online) <a href="{{ route('shop.product', [$viewPart, $viewPart->slug()]) }}" target="_blank" class="btn-light mr-auto">See on website</a> @endif
                <button type="button" class="btn-light" @click="open = false">Close</button>
                @if ($this->canManage()) <button type="button" class="btn-primary" wire:click="editFromView({{ $viewPart->id }})">Edit</button> @endif
            @endif
        </x-slot:footer>
    </x-modal>

    <x-modal show="showHistory" :title="'Stock history'.($historyPart ? ' · '.$historyPart->name : '')" max-width="max-w-3xl">
        @if ($historyPart)
            <p class="mb-3 text-sm text-ink-500">Current stock: <b class="text-ink-900">{{ rtrim(rtrim(number_format((float) $historyPart->stock_qty, 2), '0'), '.') }} {{ $historyPart->unit }}</b> · last 30 movements</p>
        @endif
        <div class="overflow-x-auto rounded-xl border border-ink-100">
            <table class="table">
                <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th class="text-right">In</th><th class="text-right">Out</th><th class="text-right">Balance</th><th>By</th></tr></thead>
                <tbody>
                @forelse ($movements as $m)
                    <tr>
                        <td class="whitespace-nowrap text-xs">{{ $m->date->format('d M Y H:i') }}</td>
                        <td><span class="badge-gray">{{ \App\Models\StockMovement::TYPES[$m->type] ?? $m->type }}</span></td>
                        <td class="font-mono text-xs">{{ $m->reference_no ?: ($m->note ?: '—') }}</td>
                        <td class="text-right text-emerald-700">{{ (float) $m->qty_in ? '+'.rtrim(rtrim(number_format((float) $m->qty_in, 2), '0'), '.') : '' }}</td>
                        <td class="text-right text-brand-600">{{ (float) $m->qty_out ? '−'.rtrim(rtrim(number_format((float) $m->qty_out, 2), '0'), '.') : '' }}</td>
                        <td class="text-right font-semibold">{{ rtrim(rtrim(number_format((float) $m->balance_after, 2), '0'), '.') }}</td>
                        <td class="text-xs text-ink-500">{{ $m->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-ink-400">No stock movements yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-modal>
</div>
