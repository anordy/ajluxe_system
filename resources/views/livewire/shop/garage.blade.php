@php
    $wa = setting('whatsapp') ?: setting('phone');
    $waBase = $wa ? 'https://wa.me/'.\App\Support\Phone::international($wa) : null;
@endphp
<div>
    {{-- ============================== INTRO --}}
    <section class="bg-[#16181B] text-white">
        <div class="wrap py-14 lg:py-20">
            <nav class="text-sm text-white/50" aria-label="{{ __('Breadcrumb') }}"><a href="{{ route('shop.home') }}" wire:navigate class="hover:text-white">{{ __('Home') }}</a> / <span class="text-white/80">{{ __('Garage & paint') }}</span></nav>
            <h1 class="mt-4 max-w-3xl font-display text-[44px] font-extrabold leading-[0.95] tracking-tight sm:text-6xl">{{ webtext('web_garage_title') }}</h1>
            <p class="mt-5 max-w-2xl text-lg text-white/70">{{ webtext('web_garage_text') }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="#book" class="s-btn-red">{{ __('Book your car in') }}</a>
                <a href="#services" class="s-btn border border-white/30 hover:border-white">{{ __('What we do') }}</a>
            </div>
        </div>
    </section>

    {{-- ============================== WHAT WE DO --}}
    <section id="services" class="wrap scroll-mt-28 pt-16">
        <h2 class="font-display text-4xl font-extrabold tracking-tight">{{ __('What we do') }}</h2>
        <p class="mt-2 max-w-2xl text-[#4B5563]">{{ __('Tell us what your car needs and we will give you a price once we have seen it. Parts can come straight from our shop.') }}</p>

        @if ($offer)
            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($offer as [$h, $t])
                    <li class="rounded-xl border border-[#16181B]/10 bg-white p-6 transition-shadow hover:shadow-[0_8px_24px_rgba(22,24,27,.08)]">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#FDECEC] text-brand-600"><x-shop.service-icon :title="$h" /></span>
                        <h3 class="mt-4 text-lg font-semibold">{{ $h }}</h3>
                        @if ($t) <p class="mt-1 text-[15px] leading-relaxed text-[#4B5563]">{{ $t }}</p> @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($services->isNotEmpty())
            <div class="mt-10 rounded-xl bg-[#F4F5F7] p-6 sm:p-8">
                <h3 class="font-display text-2xl font-bold">{{ __('All our services') }}</h3>
                <div class="mt-5 grid gap-x-10 gap-y-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($services as $cat => $list)
                        <div>
                            <p class="text-sm font-semibold text-[#6B7280]">{{ __($cat) }}</p>
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($list as $s)
                                    <li class="flex items-start gap-2">
                                        <svg class="mt-1 h-4 w-4 shrink-0 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                                        <span>{{ $s->name }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{-- ============================== BOOK --}}
    <section id="book" class="wrap scroll-mt-28 pt-16"
             x-data="{ name: '', car: '', plate: '', job: '', day: '' }">
        <div class="grid overflow-hidden rounded-xl border border-[#16181B]/10 bg-white shadow-[0_8px_30px_rgba(22,24,27,.06)] lg:grid-cols-5">
            <div class="bg-[#16181B] p-8 text-white sm:p-10 lg:col-span-2">
                <h2 class="font-display text-4xl font-extrabold tracking-tight">{{ __('Book your car in') }}</h2>
                <p class="mt-3 max-w-md text-white/70">{{ __('Tell us about the car and what it needs. Your booking opens in WhatsApp, ready to send to us.') }}</p>
                <ul class="mt-8 space-y-4 text-[15px]">
                    <li class="flex gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                        <span>{{ setting('address') ?: __('Call us for directions') }}</span>
                    </li>
                    @if (setting('opening_hours'))
                        <li class="flex gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            <span>{{ opening_hours() }}</span>
                        </li>
                    @endif
                    @if (setting('phone'))
                        <li class="flex gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
                            <a href="tel:{{ preg_replace('/\s+/', '', setting('phone')) }}" class="font-semibold hover:underline">{{ setting('phone') }}</a>
                        </li>
                    @endif
                </ul>
            </div>
            <form class="grid gap-4 p-8 sm:grid-cols-2 sm:p-10 lg:col-span-3"
                  @submit.prevent="window.open('{{ $waBase }}?text=' + encodeURIComponent({{ Js::from(__('Booking request')) }} + '\n' + {{ Js::from(__('Name:')) }} + ' ' + name + '\n' + {{ Js::from(__('Car:')) }} + ' ' + car + (plate ? ' (' + plate + ')' : '') + '\n' + {{ Js::from(__('Work needed:')) }} + ' ' + job + (day ? '\n' + {{ Js::from(__('Preferred day:')) }} + ' ' + day : '')), '_blank')">
                <div><label for="b-name" class="s-label">{{ __('Your name') }}</label><input id="b-name" x-model="name" required class="s-input" autocomplete="name"></div>
                <div><label for="b-plate" class="s-label">{{ __('Plate number') }}</label><input id="b-plate" x-model="plate" class="s-input uppercase placeholder:normal-case" placeholder="{{ __('e.g. T 123 ABC') }}"></div>
                <div class="sm:col-span-2"><label for="b-car" class="s-label">{{ __('Car make and model') }}</label><input id="b-car" x-model="car" required class="s-input" placeholder="{{ __('e.g. Toyota Prado 2015') }}"></div>
                <div class="sm:col-span-2">
                    <label for="b-job" class="s-label">{{ __('What does it need?') }}</label>
                    <textarea id="b-job" x-model="job" required rows="3" class="s-input resize-none" placeholder="{{ __('e.g. service, brakes, paint the front bumper') }}"></textarea>
                    @if ($offer)
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($offer as [$h])
                                <button type="button" @click="job = job ? job + ', ' + @js(strtolower($h)) : @js($h)" class="rounded-md border border-[#16181B]/15 px-3 py-1 text-sm hover:border-[#16181B]">+ {{ $h }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div><label for="b-day" class="s-label">{{ __('Preferred day') }}</label><input id="b-day" type="date" x-model="day" min="{{ now()->toDateString() }}" class="s-input"></div>
                <div class="flex items-end"><button class="s-btn-red w-full py-3.5" @disabled(! $waBase)>{{ __('Send booking on WhatsApp') }}</button></div>
            </form>
        </div>
    </section>
</div>
