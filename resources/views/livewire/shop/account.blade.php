<div class="wrap pt-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-5xl font-extrabold tracking-tight">{{ __('Hello, :name', ['name' => $me->firstName()]) }}</h1>
            <p class="mt-2 text-[#4B5563]">{{ $me->phone }}{{ $me->email ? ', '.$me->email : '' }}</p>
        </div>
        <form method="POST" action="{{ route('shop.logout') }}">@csrf <button class="s-btn-line">{{ __('Log out') }}</button></form>
    </div>

    <h2 class="mt-10 font-display text-3xl font-extrabold">{{ __('My orders') }}</h2>
    @if ($orders->isEmpty())
        <div class="mt-4 border border-dashed border-[#16181B]/25 px-6 py-14 text-center">
            <p class="text-lg font-semibold">{{ __('You have not ordered anything yet.') }}</p>
            <a href="{{ route('shop.catalog') }}" wire:navigate class="s-btn-ink mt-5">{{ __('Shop parts') }}</a>
        </div>
    @else
        <ul class="mt-4 divide-y divide-[#16181B]/10 border-y border-[#16181B]/10">
            @foreach ($orders as $o)
                <li>
                    <a href="{{ route('shop.order', $o) }}" wire:navigate class="flex flex-wrap items-center gap-x-6 gap-y-1 py-4 hover:bg-[#F7F7F8]">
                        <span class="w-40 font-semibold">{{ $o->number }}</span>
                        <span class="text-[#4B5563]">{{ $o->created_at->format('d M Y') }}</span>
                        <span class="text-[#4B5563]">{{ trans_choice(':count item|:count items', $o->items_count) }}</span>
                        <span class="ml-auto font-semibold">{{ money($o->total) }}</span>
                        <span class="w-52 text-right text-sm font-semibold {{ $o->status === 'cancelled' ? 'text-[#6B7280]' : ($o->status === 'ready' ? 'text-emerald-700' : '') }}">{{ __($o->status === 'ready' ? $o->readyLabel() : $o->label()) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif

    <div class="mt-12 flex flex-wrap items-end justify-between gap-3">
        <h2 class="font-display text-3xl font-extrabold">{{ __('My import requests') }}</h2>
        <a href="{{ route('shop.import') }}" wire:navigate class="font-semibold text-brand-600 hover:underline">{{ __('Ask for a new import') }}</a>
    </div>
    @if ($imports->isEmpty())
        <p class="mt-3 text-[#4B5563]">{{ __('Need an engine, gearbox or part we do not stock? We can import it from Japan, China, Dubai and more.') }}</p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($imports as $im)
                @php $steps = ['new', 'quoted', 'confirmed', 'ordered', 'shipping', 'arrived', 'completed']; $at = array_search($im->status, $steps, true); @endphp
                <li class="border border-[#16181B]/10 p-4" wire:key="imp{{ $im->id }}">
                    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <span class="font-mono font-semibold">{{ $im->ref }}</span>
                        <span class="font-semibold">{{ $im->item_type }}</span>
                        <span class="text-[#4B5563]">{{ $im->car() }}</span>
                        <span class="ml-auto text-sm font-semibold {{ $im->status === 'cancelled' ? 'text-[#6B7280]' : ($im->status === 'arrived' ? 'text-emerald-700' : '') }}">{{ __(\App\Models\ImportRequest::STATUSES[$im->status] ?? $im->status) }}</span>
                    </div>
                    <p class="mt-1 truncate text-sm text-[#4B5563]">{{ $im->description }}</p>
                    @if ($at !== false)
                        <div class="mt-3 flex gap-1" aria-hidden="true">
                            @foreach ($steps as $i => $st)<span class="h-1.5 flex-1 rounded-full {{ $i <= $at ? 'bg-brand-500' : 'bg-[#E5E7EB]' }}"></span>@endforeach
                        </div>
                    @endif
                    <p class="mt-3 text-sm">{{ __(\App\Models\ImportRequest::CUSTOMER_TEXT[$im->status] ?? '') }}</p>
                    @if ($im->customer_message) <p class="mt-1 rounded bg-[#F4F5F7] px-3 py-2 text-sm">{{ $im->customer_message }}</p> @endif
                    <div class="mt-2 flex flex-wrap gap-x-6 text-sm text-[#4B5563]">
                        @if ($im->quote_amount !== null) <span>{{ __('Price delivered:') }} <b class="text-[#16181B]">{{ money($im->quote_amount) }}</b></span> @endif
                        @if ($im->deposit_paid) <span>{{ __('Deposit paid:') }} {{ money($im->deposit_paid) }}</span> <span>{{ __('Balance:') }} {{ money($im->balance()) }}</span> @endif
                        @if ($im->eta && ! in_array($im->status, ['completed', 'cancelled'], true)) <span>{{ __('Expected:') }} {{ $im->eta->format('d M Y') }}</span> @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
