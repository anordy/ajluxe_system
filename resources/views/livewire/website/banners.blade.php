<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <p class="text-sm text-ink-500">The scrolling ads at the top of the website home page, shown in this order. While no banner is switched on, the website makes offers from your stock by itself.</p>
        @if ($this->canManage())
            <div class="flex shrink-0 gap-2 sm:ml-auto">
                <a href="{{ shop_route('shop.home') }}" target="_blank" class="btn-light">Open the website</a>
                <button class="btn-primary" wire:click="create"><x-icon name="plus" class="h-4 w-4" /> New Banner</button>
            </div>
        @endif
    </div>

    <div class="space-y-3">
        @forelse ($banners as $b)
            @php
                $live = $b->isLive();
                $state = ! $b->is_active ? ['Off', 'badge-gray'] : ($b->starts_on && $b->starts_on->isFuture() ? ['Starts '.$b->starts_on->format('d M'), 'badge-amber']
                    : ($b->ends_on && $b->ends_on->lt(today()) ? ['Ended', 'badge-gray'] : ($live ? ['Live', 'badge-green'] : [$b->isImageOnly() && ! $b->image_path ? 'No picture' : 'Part not online', 'badge-amber'])));
                $bg = ['red' => 'bg-brand-500', 'ink' => 'bg-ink-950', 'grey' => 'bg-ink-100'][$b->tone] ?? 'bg-ink-950';
            @endphp
            <div wire:key="bn{{ $b->id }}" class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center {{ $b->is_active ? '' : 'opacity-60' }}">
                <div class="flex h-24 w-full shrink-0 items-center justify-center overflow-hidden rounded-lg sm:w-44 {{ $b->isImageOnly() ? 'bg-ink-100' : $bg }}">
                    @if ($img = $b->imageUrl()) <img src="{{ $img }}" alt="" class="{{ $b->isPhoto() || $b->isImageOnly() ? 'h-full w-full object-cover' : 'h-20 object-contain' }}"> @else <x-icon name="image" class="h-7 w-7 {{ $b->tone === 'grey' ? 'text-ink-400' : 'text-white/50' }}" /> @endif
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2"><span class="{{ $state[1] }}">{{ $state[0] }}</span>@if ($b->isImageOnly())<span class="badge-gray">Picture only</span>@endif @if ($b->kicker && ! $b->isImageOnly())<span class="text-xs font-semibold text-ink-400">{{ $b->kicker }}</span>@endif</div>
                    <p class="mt-1 font-bold text-ink-900">{{ $b->title }}</p>
                    @if ($b->body() && ! $b->isImageOnly()) <p class="truncate text-sm text-ink-500">{{ $b->body() }}</p> @endif
                    <p class="mt-1 truncate text-xs text-ink-400">{{ $b->isImageOnly() ? 'Click' : ($b->button_label ?: 'Shop now') }} → {{ $b->part ? $b->part->name : ($b->link_url ?: ($b->isImageOnly() ? 'no link' : 'All parts')) }}@if ($b->ends_on) · until {{ $b->ends_on->format('d M Y') }}@endif</p>
                </div>
                @if ($this->canManage())
                    <div class="flex shrink-0 gap-1">
                        <button class="btn-icon" title="Move up" wire:click="move({{ $b->id }}, -1)" @disabled($loop->first)><x-icon name="up" class="h-4 w-4" /></button>
                        <button class="btn-icon" title="Move down" wire:click="move({{ $b->id }}, 1)" @disabled($loop->last)><x-icon name="down" class="h-4 w-4" /></button>
                        <button class="btn-icon" title="Edit" wire:click="edit({{ $b->id }})"><x-icon name="pencil" class="h-4 w-4" /></button>
                        <button class="btn-icon" title="{{ $b->is_active ? 'Switch off' : 'Switch on' }}" wire:click="toggle({{ $b->id }})"><x-icon name="{{ $b->is_active ? 'x' : 'check' }}" class="h-4 w-4" /></button>
                        <button class="btn-icon hover:!text-brand-600" title="Delete" wire:click="delete({{ $b->id }})" wire:confirm="Delete this banner?"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                @endif
            </div>
        @empty
            <div class="card py-12 text-center text-sm text-ink-400">No banners yet. The website is showing offers made from your stock.</div>
        @endforelse
    </div>

    <x-modal show="showForm" :title="$editingId ? 'Edit Banner' : 'New Banner'" max-width="max-w-2xl">
        <form wire:submit="save" id="bannerForm" class="grid gap-4">
            @php $imageOnly = ($form['layout'] ?? 'text') === 'image'; @endphp
            <div>
                <label class="label">Type of banner</label>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (\App\Models\SiteBanner::LAYOUTS as $k => $label)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm {{ ($form['layout'] ?? 'text') === $k ? 'border-ink-900 ring-1 ring-ink-900' : 'border-ink-200' }}">
                            <input type="radio" wire:model.live="form.layout" value="{{ $k }}" class="mt-0.5 border-ink-300 text-ink-900 focus:ring-ink-400">
                            <span><span class="block font-semibold text-ink-900">{{ $label }}</span>
                                <span class="text-xs text-ink-500">{{ $k === 'image' ? 'Upload a finished design. It fills the whole banner; no text or button is added.' : 'We lay out your title, text and button beside a picture.' }}</span></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="label">Promote a part (optional)</label>
                <select wire:model.live="form.part_id" class="input">
                    <option value="">— No part —</option>
                    @foreach ($parts as $p) <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }}){{ $p->hasDiscount() ? ' −'.$p->discountShown().'%' : '' }}</option> @endforeach
                </select>
                <p class="hint">The banner links to the part, uses its picture and shows its price. Only parts shown on the website are listed.</p>
            </div>
            @if ($imageOnly)
                <div><label class="label">Name <span class="font-normal text-ink-400">(optional)</span></label><input wire:model="form.title" maxlength="120" class="input" placeholder="December tyre sale">
                    <p class="hint">Not shown on the banner, which shows only your design. Read out to blind visitors and used in this list.</p>@error('form.title') <p class="error">{{ $message }}</p> @enderror</div>
            @else
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
                <div><label class="label">Small line above</label><input wire:model="form.kicker" maxlength="60" class="input" placeholder="Deal of the week"></div>
                <div><label class="label">Title *</label><input wire:model="form.title" maxlength="120" class="input" placeholder="New tyres from TZS 98,000">@error('form.title') <p class="error">{{ $message }}</p> @enderror</div>
            </div>
            <div><label class="label">Text</label><textarea wire:model="form.text" rows="2" maxlength="300" class="input" placeholder="One or two short sentences."></textarea></div>
            <details class="rounded-lg border border-ink-100 p-4" @if (($form['title_sw'] ?? '') !== '') open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-ink-800">Swahili wording <span class="font-normal text-ink-400">(optional — visitors who choose Swahili see this; empty boxes show the English)</span></summary>
                <div class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
                        <div><label class="label">Small line above</label><input wire:model="form.kicker_sw" maxlength="80" class="input" placeholder="Ofa ya wiki"></div>
                        <div><label class="label">Title</label><input wire:model="form.title_sw" maxlength="160" class="input" placeholder="Matairi mapya kuanzia TZS 98,000"></div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                        <div><label class="label">Text</label><textarea wire:model="form.text_sw" rows="2" maxlength="400" class="input"></textarea></div>
                        <div><label class="label">Button</label><input wire:model="form.button_label_sw" maxlength="60" class="input" placeholder="Nunua sasa"></div>
                    </div>
                </div>
            </details>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                @unless ($imageOnly)
                    <div><label class="label">Button</label><input wire:model="form.button_label" maxlength="40" class="input" placeholder="Shop now"></div>
                @endunless
                <div>
                    <label class="label">{{ $imageOnly ? 'Clicking the banner goes to (optional)' : 'Button goes to' }}</label>
                    <input wire:model="form.link_url" list="banner-links" class="input" placeholder="{{ $form['part_id'] ?? '' ? 'The part page' : '/shop?category=Tyres' }}">
                    <datalist id="banner-links">@foreach ($links as $url => $label)<option value="{{ $url }}">{{ $label }}</option>@endforeach</datalist>
                    @error('form.link_url') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="label">{{ $imageOnly ? 'Your banner design *' : 'Picture' }}</label>
                <x-photo-input model="photoData" reset="banner-form-opened" :max="1800" size="h-20 w-36"
                               :current="$editingId ? \App\Models\SiteBanner::find($editingId)?->imageUrl() : null" />
                @if ($imageOnly)
                    <p class="hint">Best size: <b>1600 × 900 pixels</b> (JPG or PNG). Keep words and logos away from the edges: on phones the sides are cut off a little.</p>
                @else
                    <p class="hint">A wide photo (about 1400 × 800) fills the right half of the banner. Without one, the linked part's picture is used.</p>
                @endif
                @if ($editingId && \App\Models\SiteBanner::find($editingId)?->image_path)
                    <label class="mt-2 flex items-center gap-2 text-sm text-ink-700"><input type="checkbox" wire:model="removePhoto" class="rounded border-ink-300 text-brand-500 focus:ring-brand-400"> Remove the current picture</label>
                @endif
                @error('photoData') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div @class(['hidden' => $imageOnly])>
                <label class="label">Colour</label>
                <div class="flex gap-2">
                    @foreach (\App\Models\SiteBanner::TONES as $k => $label)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm {{ ($form['tone'] ?? '') === $k ? 'border-ink-900 ring-1 ring-ink-900' : 'border-ink-200' }}">
                            <input type="radio" wire:model.live="form.tone" value="{{ $k }}" class="sr-only">
                            <span class="h-4 w-4 rounded-full {{ ['red' => 'bg-brand-500', 'ink' => 'bg-ink-950', 'grey' => 'bg-ink-200'][$k] }}"></span> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label">Show from</label><input type="date" wire:model="form.starts_on" class="input"></div>
                <div><label class="label">Show until</label><input type="date" wire:model="form.ends_on" class="input">@error('form.ends_on') <p class="error">{{ $message }}</p> @enderror</div>
            </div>
            <div x-data="{ on: @js((bool) ($form['is_active'] ?? true)) }" wire:key="bn-on-{{ $editingId ?? 'new' }}" class="rounded-lg border p-3" :class="on ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
                <label class="flex items-center gap-2 text-sm font-semibold" :class="on ? 'text-emerald-800' : 'text-amber-900'">
                    <input type="checkbox" wire:model="form.is_active" @change="on = $event.target.checked" class="rounded border-ink-300 text-emerald-600 focus:ring-emerald-500">
                    <span x-text="on ? 'Switched on — shown on the website' : 'Switched off — NOT shown on the website'"></span>
                </label>
                <p x-show="! on" x-cloak class="mt-1 pl-6 text-xs text-amber-800">Tick this box to show the banner on the home page.</p>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="bannerForm" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save Banner</span><span wire:loading wire:target="save">Saving…</span>
            </button>
        </x-slot:footer>
    </x-modal>
</div>
