<div x-data="{ open: $wire.entangle('open') }" x-on:keydown.escape.window="open = false">
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-[#16181B]/50" @click="open = false"></div>
    <aside x-show="open" x-cloak
           x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           x-trap.noscroll="open"
           class="fixed inset-y-0 right-0 z-50 flex w-full max-w-[460px] flex-col bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="car-req-title">
        <div class="flex items-start justify-between gap-4 border-b border-[#16181B]/10 px-6 py-5">
            <h2 id="car-req-title" class="font-display text-[28px] font-bold leading-tight">{{ __('Can\'t find your car?') }}</h2>
            <button type="button" @click="open = false" class="-mr-2 rounded-md p-2 text-[#4B5563] hover:bg-[#F1F2F4] hover:text-[#16181B]" aria-label="{{ __('Close') }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        @if ($sent)
            <div class="flex flex-1 flex-col items-center justify-center px-8 text-center">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5 9-10"/></svg>
                </span>
                <h3 class="mt-5 font-display text-2xl font-bold">{{ __('Request received') }}</h3>
                <p class="mt-2 max-w-xs text-[#4B5563]">{{ __('Thank you. We will check parts for your :car and get back to you.', ['car' => trim($make.' '.$model)]) }} {{ webtext('web_car_request_note') }}</p>
                <button type="button" class="s-btn-ink mt-8" @click="open = false">{{ __('Back to the shop') }}</button>
            </div>
        @else
            <form wire:submit="send" id="car-request" class="flex-1 space-y-5 overflow-y-auto px-6 py-6">
                <p class="text-[15px] leading-relaxed text-[#374151]">{{ __('Tell us about your car and we will find the parts and add it to our catalogue.') }}</p>

                <div>
                    <label for="cr-vin" class="s-label">{{ __('Chassis / VIN number') }} <span class="font-normal text-[#6B7280]">{{ __('(optional)') }}</span></label>
                    <input id="cr-vin" wire:model="vin" class="s-input uppercase placeholder:normal-case" maxlength="20" autocomplete="off" placeholder="{{ __('e.g. JTEBU3FJ10K123456') }}">
                    <p class="mt-1 text-xs text-[#6B7280]">{{ __('On the registration card or under the windscreen. It helps us find the exact part.') }}</p>
                    @error('vin') <p class="s-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="cr-make" class="s-label">{{ __('Make *') }}</label>
                        <input id="cr-make" wire:model="make" list="cr-makes" class="s-input" placeholder="{{ __('e.g. Toyota') }}" maxlength="60">
                        <datalist id="cr-makes">@foreach ($makes as $m)<option value="{{ $m }}">@endforeach</datalist>
                        @error('make') <p class="s-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cr-model" class="s-label">{{ __('Model *') }}</label>
                        <input id="cr-model" wire:model="model" class="s-input" placeholder="{{ __('e.g. Crown') }}" maxlength="80">
                        @error('model') <p class="s-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="w-1/2 pr-1.5">
                    <label for="cr-year" class="s-label">{{ __('Year of manufacture') }}</label>
                    <input id="cr-year" wire:model="year" inputmode="numeric" maxlength="4" class="s-input" placeholder="e.g. 2012">
                    @error('year') <p class="s-error">{{ $message }}</p> @enderror
                </div>

                <div x-data="{ n: $wire.parts.length }">
                    <label for="cr-parts" class="s-label">{{ __('Parts you are looking for') }}</label>
                    <textarea id="cr-parts" wire:model="parts" rows="3" maxlength="500" @input="n = $event.target.value.length" class="s-input resize-none" placeholder="{{ __('e.g. front brake pads, oil filter, air filter') }}"></textarea>
                    <p class="mt-1 text-right text-xs text-[#6B7280]"><span x-text="n">0</span>/500</p>
                    @error('parts') <p class="s-error">{{ $message }}</p> @enderror
                </div>

                <fieldset class="space-y-3 border-t border-[#16181B]/10 pt-5">
                    <legend class="sr-only">{{ __('How should we reply?') }}</legend>
                    <p class="text-[15px] font-semibold">{{ __('How should we reply?') }}</p>
                    <div><label for="cr-name" class="s-label">{{ __('Your name') }}</label><input id="cr-name" wire:model="name" class="s-input" autocomplete="name"></div>
                    <div>
                        <label for="cr-phone" class="s-label">{{ __('Phone / WhatsApp *') }}</label>
                        <input id="cr-phone" wire:model="phone" type="tel" class="s-input" placeholder="0754 000 000" autocomplete="tel">
                        @error('phone') <p class="s-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cr-email" class="s-label">{{ __('Email') }} <span class="font-normal text-[#6B7280]">{{ __('(or instead of phone)') }}</span></label>
                        <input id="cr-email" wire:model="email" type="email" class="s-input" autocomplete="email">
                        @error('email') <p class="s-error">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-start gap-3 pt-1 text-sm text-[#374151]">
                        <input type="checkbox" wire:model="wants_offers" class="mt-0.5 h-4 w-4 rounded border-[#16181B]/30 text-brand-500 focus:ring-brand-500">
                        {{ __('Yes, send me news about offers and discounts by SMS or WhatsApp.') }}
                    </label>
                </fieldset>
                <p class="text-sm text-[#6B7280]">{{ webtext('web_car_request_note') }}</p>
            </form>
            <div class="border-t border-[#16181B]/10 p-4">
                <button type="submit" form="car-request" class="s-btn-red w-full py-3.5" wire:loading.attr="disabled" wire:target="send">
                    <span wire:loading.remove wire:target="send">{{ __('Send request') }}</span><span wire:loading wire:target="send">{{ __('Sending…') }}</span>
                </button>
            </div>
        @endif
    </aside>
</div>
