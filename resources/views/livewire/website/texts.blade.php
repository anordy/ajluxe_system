<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <p class="text-sm text-ink-500">Headings and short texts on the website. Phone, address, opening hours, payment details and logo are in <b>Settings</b>; parts, prices and pictures come from the <b>Parts Catalogue</b>. Visitors who choose Swahili see the Swahili box; when it is empty they see the English text.</p>
        <a href="{{ route('shop.home') }}" target="_blank" class="btn-light shrink-0 sm:ml-auto">Open the website</a>
    </div>

    <form wire:submit="save" class="card divide-y divide-ink-100">
        @foreach (\App\Livewire\Website\Texts::FIELDS as $key => [$label, $max, $rows, $hint])
            <div class="grid gap-2 p-5 lg:grid-cols-[260px_1fr] lg:gap-6" wire:key="t-{{ $key }}">
                <div>
                    <label for="t-{{ $key }}" class="text-sm font-semibold text-ink-900">{{ $label }}</label>
                    @if ($hint) <p class="mt-0.5 text-xs text-ink-500">{{ $hint }}</p> @endif
                </div>
                <div class="grid gap-4 {{ \App\Livewire\Website\Texts::hasSwahili($key) ? 'xl:grid-cols-2' : '' }}">
                    @foreach (\App\Livewire\Website\Texts::hasSwahili($key) ? [$key => 'English', $key.'_sw' => 'Swahili'] : [$key => null] as $fk => $lang)
                        <div>
                            @if ($lang) <p class="mb-1 text-xs font-semibold text-ink-500">{{ $lang }}</p> @endif
                            @if ($rows)
                                <textarea id="t-{{ $fk }}" wire:model="form.{{ $fk }}" rows="{{ $rows }}" maxlength="{{ $lang === 'Swahili' ? (int) ceil($max * 1.4) : $max }}" class="input" aria-label="{{ $label }}{{ $lang ? ' ('.$lang.')' : '' }}"></textarea>
                            @else
                                <input id="t-{{ $fk }}" wire:model="form.{{ $fk }}" maxlength="{{ $lang === 'Swahili' ? (int) ceil($max * 1.4) : $max }}" class="input" aria-label="{{ $label }}{{ $lang ? ' ('.$lang.')' : '' }}">
                            @endif
                            @error('form.'.$fk) <p class="error">{{ $message }}</p> @enderror
                            @if (isset(\App\Support\Settings::DEFAULTS[$fk]) && ($form[$fk] ?? '') !== \App\Support\Settings::DEFAULTS[$fk])
                                <button type="button" wire:click="restore('{{ $fk }}')" class="mt-1 text-xs font-semibold text-ink-500 hover:text-ink-900">Use the original text</button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        <div class="flex justify-end p-4">
            <button class="btn-primary" wire:loading.attr="disabled">Save texts</button>
        </div>
    </form>
</div>
