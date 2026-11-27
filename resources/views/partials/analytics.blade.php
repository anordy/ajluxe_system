{{-- Google Analytics 4 for the public website. Set the ID in Settings › Business Profile; empty = off.
     The shop moves between pages with wire:navigate, so page views are sent on every livewire:navigated. --}}
@php $ga = strtoupper(trim((string) setting('ga_measurement_id', ''))); @endphp
@if (preg_match('/^G-[A-Z0-9]{4,16}$/', $ga))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}" data-navigate-once></script>
<script data-navigate-once>
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', @json($ga), { send_page_view: false });
    (function () {
        var last = null;
        function pageView() {
            if (last === location.href) return;
            last = location.href;
            gtag('event', 'page_view', { page_location: location.href, page_title: document.title });
        }
        document.addEventListener('livewire:navigated', pageView);
        if (document.readyState !== 'loading') pageView(); else document.addEventListener('DOMContentLoaded', pageView);
    })();
</script>
@endif
