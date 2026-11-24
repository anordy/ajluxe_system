@props(['step' => 1])
{{-- order progress shown under the menu: cart → your account → delivery & payment → done --}}
@php
    $steps = [__('Cart'), __('Your account'), __('Delivery & payment'), __('Order placed')];
@endphp
<div class="border-b border-[#16181B]/10 bg-[#F7F8F9]">
    <ol class="wrap flex items-start py-5" aria-label="{{ __('Order progress') }}">
        @foreach ($steps as $i => $label)
            @php $n = $i + 1; $done = $n < $step; $now = $n === $step; @endphp
            <li class="relative flex flex-1 flex-col items-center text-center" @if ($now) aria-current="step" @endif>
                @if (! $loop->first)
                    <span class="absolute right-1/2 top-4 h-0.5 w-full -translate-y-1/2 {{ $n <= $step ? 'bg-emerald-500' : 'bg-[#E5E7EB]' }}" aria-hidden="true"></span>
                @endif
                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold ring-4
                    {{ $done ? 'bg-emerald-500 text-white ring-emerald-100' : ($now ? 'bg-brand-500 text-white ring-[#FDECEC]' : 'bg-white text-[#9CA3AF] ring-[#F1F2F4] border border-[#E5E7EB]') }}">
                    @if ($done)
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 5 5 9-10"/></svg>
                    @else
                        {{ $n }}
                    @endif
                </span>
                <span class="mt-2 text-xs font-semibold sm:text-sm {{ $now ? 'text-[#16181B]' : ($done ? 'text-emerald-700' : 'text-[#9CA3AF]') }}">{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</div>
