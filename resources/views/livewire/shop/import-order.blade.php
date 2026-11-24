@php
    $countries = \App\Support\Settings::pairs('web_import_countries');
    $steps = \App\Support\Settings::pairs('web_import_steps');
    $me = auth('shop')->user();
@endphp
<div>
    {{-- intro --}}
    <section class="bg-[#16181B] text-white">
        <div class="wrap grid gap-10 py-12 lg:grid-cols-12 lg:py-16">
            <div class="lg:col-span-7">
                <nav class="text-sm text-white/50" aria-label="{{ __('Breadcrumb') }}"><a href="{{ route('shop.home') }}" wire:navigate class="hover:text-white">{{ __('Home') }}</a> / <span class="text-white/80">{{ webtext('web_import_title') }}</span></nav>
                <h1 class="mt-4 font-display text-5xl font-extrabold leading-[0.95] tracking-tight sm:text-6xl">{{ webtext('web_import_title') }}</h1>
                <p class="mt-5 max-w-xl text-lg leading-relaxed text-white/75">{{ webtext('web_import_text') }}</p>

                @if ($countries)
                    <h2 class="mt-10 font-display text-2xl font-bold">{{ __('Where we import from') }}</h2>
                    <ul class="mt-4 grid gap-px overflow-hidden rounded-md bg-white/10 sm:grid-cols-2">
                        @foreach ($countries as [$c, $t])
                            <li class="flex gap-3 bg-[#1F2226] p-4">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                                <span><span class="block font-semibold">{{ $c }}</span>@if ($t)<span class="text-sm text-white/60">{{ $t }}</span>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if (webtext('web_import_note'))
                    <p class="mt-5 flex gap-2 text-sm text-white/60">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        {{ webtext('web_import_note') }}
                    </p>
                @endif
            </div>

            {{-- request form --}}
            <div class="lg:col-span-5" id="request">
                <div class="rounded-md bg-white text-[#16181B] shadow-xl">
                    @if ($sentRef)
                        <div class="px-6 py-14 text-center">
                            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5 9-10"/></svg>
                            </span>
                            <h2 class="mt-5 font-display text-3xl font-bold">{{ __('Request :ref received', ['ref' => $sentRef]) }}</h2>
                            <p class="mx-auto mt-2 max-w-sm text-[#4B5563]">{{ __('We will find it and send you one price delivered to Dar es Salaam. Nothing is ordered until you agree to the price.') }}</p>
                            @if ($me)
                                <a href="{{ route('shop.account') }}" wire:navigate class="s-btn-ink mt-7">{{ __('Follow it in my account') }}</a>
                            @else
                                <p class="mt-4 text-sm text-[#6B7280]">{{ __('Keep the number :ref for when we call you.', ['ref' => $sentRef]) }}</p>
                            @endif
                            <button type="button" wire:click="another" class="mt-4 block w-full text-sm font-semibold text-brand-600 hover:underline">{{ __('Ask for something else') }}</button>
                        </div>
                    @else
                        <form wire:submit="send" class="space-y-4 p-6">
                            <div>
                                <h2 class="font-display text-[28px] font-bold leading-tight">{{ __('Ask for a price') }}</h2>
                                <p class="text-sm text-[#4B5563]">{{ __('Free, and you do not pay anything until you agree.') }}</p>
                            </div>

                            <fieldset>
                                <legend class="s-label">{{ __('What do you need? *') }}</legend>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach (\App\Models\ImportRequest::ITEM_TYPES as $t)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2.5 text-sm font-medium transition-colors has-[:checked]:border-[#16181B] has-[:checked]:ring-1 has-[:checked]:ring-[#16181B] {{ $item_type === $t ? '' : 'border-[#16181B]/15 hover:border-[#16181B]/40' }}">
                                            <input type="radio" wire:model.live="item_type" value="{{ $t }}" class="h-4 w-4 border-[#16181B]/30 text-[#16181B] focus:ring-[#16181B]"> {{ __($t) }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('item_type') <p class="s-error">{{ $message }}</p> @enderror
                            </fieldset>

                            <div>
                                <label for="im-desc" class="s-label">{{ __('Describe it *') }}</label>
                                <textarea id="im-desc" wire:model="description" rows="3" maxlength="1000" class="s-input resize-none"
                                          placeholder="{{ $item_type === 'Engine' ? __('e.g. 1KD-FTV engine complete, with injectors and turbo') : ($item_type === 'Gearbox / transmission' ? __('e.g. automatic gearbox A750F, 4WD') : __('e.g. front bumper and both headlights')) }}"></textarea>
                                @error('description') <p class="s-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="im-make" class="s-label">{{ __('Car make *') }}</label>
                                    <input id="im-make" wire:model="make" list="im-makes" class="s-input" placeholder="{{ __('Toyota') }}">
                                    <datalist id="im-makes">@foreach ($makes as $m)<option value="{{ $m }}">@endforeach</datalist>
                                    @error('make') <p class="s-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="im-model" class="s-label">{{ __('Model *') }}</label>
                                    <input id="im-model" wire:model="model" class="s-input" placeholder="{{ __('Land Cruiser Prado') }}">
                                    @error('model') <p class="s-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="im-year" class="s-label">{{ __('Year') }}</label>
                                    <input id="im-year" wire:model="year" inputmode="numeric" maxlength="4" class="s-input" placeholder="2012">
                                    @error('year') <p class="s-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="im-engine" class="s-label">{{ __('Engine code') }}</label>
                                    <input id="im-engine" wire:model="engine_code" class="s-input uppercase placeholder:normal-case" placeholder="{{ __('e.g. 1KD-FTV') }}">
                                </div>
                            </div>
                            <div>
                                <label for="im-vin" class="s-label">{{ __('Chassis / VIN number') }} <span class="font-normal text-[#6B7280]">{{ __('(helps us find the exact part)') }}</span></label>
                                <input id="im-vin" wire:model="vin" maxlength="20" class="s-input uppercase placeholder:normal-case" placeholder="{{ __('On the registration card') }}">
                                @error('vin') <p class="s-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="im-country" class="s-label">{{ __('Import from') }}</label>
                                    <select id="im-country" wire:model="source_country" class="s-input">
                                        <option value="">{{ __('Best price – you choose') }}</option>
                                        @foreach (\App\Models\ImportRequest::countries() as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="im-cond" class="s-label">{{ __('Condition') }}</label>
                                    <select id="im-cond" wire:model="condition" class="s-input">
                                        <option value="">{{ __('Any') }}</option>
                                        @foreach (\App\Models\ImportRequest::CONDITIONS as $c)<option value="{{ $c }}">{{ __($c) }}</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="im-qty" class="s-label">{{ __('Quantity') }}</label>
                                    <input id="im-qty" wire:model="quantity" type="number" min="1" max="99" class="s-input">
                                    @error('quantity') <p class="s-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="im-budget" class="s-label">{{ __('Budget (TZS)') }}</label>
                                    <input id="im-budget" wire:model="budget" inputmode="numeric" class="s-input" placeholder="{{ __('Optional') }}">
                                </div>
                            </div>

                            <div class="space-y-3 border-t border-[#16181B]/10 pt-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="im-name" class="s-label">{{ __('Your name *') }}</label>
                                        <input id="im-name" wire:model="name" class="s-input" autocomplete="name">
                                        @error('name') <p class="s-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="im-phone" class="s-label">{{ __('Phone / WhatsApp *') }}</label>
                                        <input id="im-phone" wire:model="phone" type="tel" class="s-input" autocomplete="tel" placeholder="0754 000 000">
                                        @error('phone') <p class="s-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div>
                                    <label for="im-email" class="s-label">{{ __('Email') }}</label>
                                    <input id="im-email" wire:model="email" type="email" class="s-input" autocomplete="email">
                                    @error('email') <p class="s-error">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <button class="s-btn-red w-full py-3.5" wire:loading.attr="disabled" wire:target="send">
                                <span wire:loading.remove wire:target="send">{{ __('Send request') }}</span><span wire:loading wire:target="send">{{ __('Sending…') }}</span>
                            </button>
                            @unless ($me)
                                <p class="text-center text-xs text-[#6B7280]"><a href="{{ route('shop.login') }}" wire:navigate class="font-semibold text-[#16181B] hover:underline">{{ __('Log in') }}</a> {{ __('first to follow your request in your account.') }}</p>
                            @endunless
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- how it works --}}
    @if ($steps)
        <section class="wrap pt-16">
            <h2 class="font-display text-4xl font-extrabold tracking-tight">{{ __('How importing works') }}</h2>
            <ol class="mt-8 grid gap-6 sm:grid-cols-2 {{ ['', 'lg:grid-cols-1', 'lg:grid-cols-2', 'lg:grid-cols-3', 'lg:grid-cols-4', 'lg:grid-cols-5'][min(5, count($steps))] }}">
                @foreach ($steps as $i => [$h, $t])
                    <li class="border-t-[3px] {{ $loop->first ? 'border-brand-500' : 'border-[#16181B]' }} pt-4">
                        <span class="font-display text-4xl font-extrabold text-[#D1D5DB]">{{ $i + 1 }}</span>
                        <h3 class="mt-1 text-lg font-semibold">{{ $h }}</h3>
                        @if ($t)<p class="mt-1 text-[15px] text-[#4B5563]">{{ $t }}</p>@endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
</div>
