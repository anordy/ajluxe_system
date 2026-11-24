<div>
    <x-shop.checkout-steps :step="3" />
<div class="wrap pt-10">
    <h1 class="font-display text-5xl font-extrabold tracking-tight">{{ __('Checkout') }}</h1>
    <form wire:submit="place" class="mt-8 grid gap-10 lg:grid-cols-[1fr_380px]">
        <div class="space-y-10">
            <fieldset>
                <legend class="font-display text-2xl font-extrabold">{{ __('How do you want to get it?') }}</legend>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach (\App\Models\Order::FULFILMENT as $k => $label)
                        <label class="flex cursor-pointer gap-3 border-2 p-4 {{ $fulfilment === $k ? 'border-[#16181B]' : 'border-[#16181B]/15' }}">
                            <input type="radio" wire:model.live="fulfilment" value="{{ $k }}" class="mt-1 text-[#16181B] focus:ring-[#16181B]">
                            <span><span class="block font-semibold">{{ __($label) }}</span>
                                <span class="text-sm text-[#4B5563]">{{ $k === 'pickup' ? (setting('address') ?: __('At our shop')) : __('We agree the cost and time with you when we confirm') }}</span></span>
                        </label>
                    @endforeach
                </div>
                @if ($fulfilment === 'delivery')
                    <div class="mt-4">
                        <label for="addr" class="s-label">{{ __('Delivery address') }}</label>
                        <input id="addr" wire:model="delivery_address" class="s-input" placeholder="{{ __('Area, street, landmark') }}">
                        @error('delivery_address') <p class="s-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div class="mt-4 max-w-sm">
                    <label for="cphone" class="s-label">{{ __('Phone we should call') }}</label>
                    <input id="cphone" wire:model="contact_phone" type="tel" class="s-input">
                    @error('contact_phone') <p class="s-error">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            <fieldset>
                <legend class="font-display text-2xl font-extrabold">{{ __('How will you pay?') }}</legend>
                <div class="mt-4 space-y-3">
                    @foreach (\App\Models\Order::PAYMENT_METHODS as $k => $label)
                        <label class="flex cursor-pointer gap-3 border-2 p-4 {{ $payment_method === $k ? 'border-[#16181B]' : 'border-[#16181B]/15' }}">
                            <input type="radio" wire:model.live="payment_method" value="{{ $k }}" class="mt-1 text-[#16181B] focus:ring-[#16181B]">
                            <span class="font-semibold">{{ __($label) }}</span>
                        </label>
                    @endforeach
                </div>
                @if ($payment_method !== 'on_pickup')
                    <div class="mt-4 bg-[#F1F2F4] p-5 text-[15px]">
                        <p class="font-semibold">{{ __('Pay :amount to:', ['amount' => money($totals['total'])]) }}</p>
                        <ul class="mt-2 space-y-1">
                            @foreach (preg_split('/\r\n|\r|\n/', (string) setting('payment_details')) as $row)
                                @if (trim($row) !== '' && ($payment_method === 'bank' ? stripos($row, 'bank') !== false || stripos($row, 'crdb') !== false : stripos($row, 'pesa') !== false || stripos($row, 'lipa') !== false)) <li class="font-semibold">{{ $row }}</li> @endif
                            @endforeach
                        </ul>
                        <p class="mt-3 text-[#4B5563]">{{ __('You can pay now and enter the transaction code below, or pay after we confirm your order.') }}</p>
                        <label for="ref" class="s-label mt-4">{{ __('Transaction code') }} <span class="font-normal text-[#6B7280]">{{ __('(optional)') }}</span></label>
                        <input id="ref" wire:model="payment_reference" class="s-input max-w-xs uppercase" placeholder="{{ __('e.g. QKR7AB12XY') }}">
                    </div>
                @endif
            </fieldset>

            <div>
                <label for="notes" class="s-label">{{ __('Anything we should know?') }} <span class="font-normal text-[#6B7280]">{{ __('(optional)') }}</span></label>
                <textarea id="notes" wire:model="notes" rows="3" class="s-input" placeholder="{{ __('Car model and year, or a time you will collect') }}"></textarea>
            </div>
        </div>

        <aside class="h-fit border-2 border-[#16181B] p-6">
            <h2 class="font-display text-2xl font-extrabold">{{ __('Your order') }}</h2>
            <ul class="mt-4 space-y-3 text-[15px]">
                @foreach ($lines as $l)
                    <li class="flex justify-between gap-3"><span class="min-w-0"><span class="font-medium">{{ $l->qty }} ×</span> {{ $l->part?->name }}</span><span class="shrink-0">{{ amount($l->amount) }}</span></li>
                @endforeach
            </ul>
            <dl class="mt-4 space-y-2 border-t border-[#16181B]/15 pt-4 text-[15px]">
                @if ($totals['discount'] > 0) <div class="flex justify-between text-brand-600"><dt>{{ __('You save') }}</dt><dd>− {{ money($totals['discount']) }}</dd></div> @endif
                <div class="flex justify-between"><dt class="text-lg font-semibold">{{ __('Total') }}</dt><dd class="s-price text-3xl">{{ money($totals['total']) }}</dd></div>
            </dl>
            @error('order') <p class="s-error mt-4">{{ $message }}</p> @enderror
            <button class="s-btn-red mt-5 w-full" wire:loading.attr="disabled" wire:target="place">
                <span wire:loading.remove wire:target="place">{{ __('Place order') }}</span><span wire:loading wire:target="place">{{ __('Placing order…') }}</span>
            </button>
            <p class="mt-3 text-sm text-[#4B5563]">{{ __('We check your order and call you to confirm. Nothing is charged automatically.') }}</p>
            <a href="{{ route('shop.cart') }}" wire:navigate class="mt-3 block text-center text-sm font-semibold text-[#4B5563] hover:text-[#16181B]">{{ __('Back to cart') }}</a>
        </aside>
    </form>
</div>
</div>
