<div>
    @if ($placed) <x-shop.checkout-steps :step="4" /> @endif
@php
    $steps = [
        ['pending', __('Order placed'), __('We have your order and are checking the parts.')],
        ['confirmed', __('Confirmed'), __('Your parts are set aside for you.')],
        ['ready', __($o->readyLabel()), $o->fulfilment === 'delivery' ? __('Your parts are on the way.') : __('Come to the shop to collect.')],
        ['completed', $o->fulfilment === 'delivery' ? __('Delivered') : __('Collected'), __('Thank you for buying from us.')],
    ];
    $pos = array_search($o->status, array_column($steps, 0), true);
@endphp
<div class="wrap max-w-[900px] pt-10">
    <a href="{{ route('shop.account') }}" wire:navigate class="text-sm font-semibold text-[#4B5563] hover:text-[#16181B]">{{ __('My orders') }}</a>

    @if ($placed)
        <div class="mt-4 border-l-4 border-emerald-600 bg-emerald-50 p-5">
            <p class="text-lg font-semibold text-emerald-900">{{ __('Thank you, your order is placed.') }}</p>
            <p class="mt-1 text-emerald-900/80">{{ __('We will call :phone to confirm. Keep your order number:', ['phone' => $o->contact_phone]) }} <b>{{ $o->number }}</b>.</p>
        </div>
    @endif

    <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-5xl font-extrabold tracking-tight">{{ __('Order :number', ['number' => $o->number]) }}</h1>
            <p class="mt-2 text-[#4B5563]">{{ __('Placed :date', ['date' => $o->created_at->format('d M Y, H:i')]) }}</p>
        </div>
        @if ($o->status === 'pending')
            <button type="button" wire:click="cancel" wire:confirm="{{ __('Cancel this order?') }}" class="s-btn-line">{{ __('Cancel order') }}</button>
        @endif
    </div>

    {{-- progress --}}
    @if ($o->status === 'cancelled')
        <p class="mt-8 border-2 border-[#16181B]/15 p-5 font-semibold">{{ __('This order was cancelled.') }}{{ $o->cancel_reason ? ' '.$o->cancel_reason.'.' : '' }}</p>
    @else
        <ol class="mt-8 grid gap-4 sm:grid-cols-4">
            @foreach ($steps as $i => [$key, $title, $text])
                <li class="border-t-4 pt-3 {{ $i <= $pos ? ($i === $pos ? 'border-brand-500' : 'border-[#16181B]') : 'border-[#16181B]/15' }}">
                    <p class="font-semibold {{ $i > $pos ? 'text-[#9CA3AF]' : '' }}">{{ $title }}</p>
                    @if ($i === $pos) <p class="mt-1 text-sm text-[#4B5563]">{{ $text }}</p> @endif
                </li>
            @endforeach
        </ol>
    @endif

    <div class="mt-10 grid gap-10 md:grid-cols-[1fr_300px]">
        <ul class="self-start divide-y divide-[#16181B]/10 border-y border-[#16181B]/10">
            @foreach ($o->items as $i)
                <li class="flex justify-between gap-4 py-4">
                    <span><span class="font-semibold">{{ $i->qtyLabel() }} ×</span> {{ $i->name }}
                        @if ((float) $i->discount_percent > 0) <span class="ml-1 text-sm text-brand-600">−{{ (int) $i->discount_percent }}%</span> @endif</span>
                    <span class="shrink-0 font-medium">{{ money($i->amount) }}</span>
                </li>
            @endforeach
            <li class="flex justify-between py-4"><span class="text-lg font-semibold">{{ __('Total') }}</span><span class="s-price text-3xl">{{ money($o->total) }}</span></li>
        </ul>
        <dl class="space-y-4 text-[15px]">
            <div><dt class="text-[#6B7280]">{{ __('Getting it') }}</dt><dd class="font-medium">{{ __(\App\Models\Order::FULFILMENT[$o->fulfilment]) }}{{ $o->delivery_address ? ': '.$o->delivery_address : '' }}</dd></div>
            <div><dt class="text-[#6B7280]">{{ __('Paying by') }}</dt><dd class="font-medium">{{ __(\App\Models\Order::PAYMENT_METHODS[$o->payment_method]) }}{{ $o->payment_reference ? ' ('.$o->payment_reference.')' : '' }}</dd></div>
            @if ($o->payment_method !== 'on_pickup' && $o->isOpen())
                <div class="bg-[#F1F2F4] p-4">
                    <dt class="font-semibold">{{ __('Payment details') }}</dt>
                    @foreach (preg_split('/\r\n|\r|\n/', (string) setting('payment_details')) as $row) @if (trim($row) !== '') <dd>{{ $row }}</dd> @endif @endforeach
                </div>
            @endif
            <div><dt class="text-[#6B7280]">{{ __('Questions?') }}</dt><dd class="font-medium">{{ __('Call :phone and quote :number', ['phone' => setting('phone'), 'number' => $o->number]) }}</dd></div>
        </dl>
    </div>
</div>
</div>
