<?php

namespace App\Livewire\Shop;

use App\Models\GarageBooking;
use App\Models\Service;
use App\Support\Audit;
use Illuminate\Support\Facades\RateLimiter;
use App\Support\Settings;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** The garage on the website: what we do and booking. No prices are shown here. */
#[Layout('components.layouts.shop')]
class Garage extends Component
{
    /**
     * Keep the booking for the garage team (it also opens in WhatsApp in the browser).
     * Returns true when saved, so the page can say "received".
     */
    public function book(array $b): bool
    {
        $key = 'garage-booking:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return false;
        }
        $data = validator($b, [
            'name'  => 'required|string|max:120',
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+ ()-]{9,}$/'],
            'car'   => 'required|string|max:120',
            'plate' => 'nullable|string|max:30',
            'job'   => 'required|string|max:2000',
            'day'   => 'nullable|date|after_or_equal:today',
        ])->validate();
        RateLimiter::hit($key, 600);

        $booking = GarageBooking::create([
            'name' => trim($data['name']), 'phone' => trim($data['phone']), 'car' => trim($data['car']),
            'plate' => filled($data['plate'] ?? null) ? \App\Models\Vehicle::normalizeReg($data['plate']) : null,
            'work' => trim($data['job']), 'preferred_date' => $data['day'] ?? null,
            'shop_customer_id' => auth('shop')->id(),
        ]);
        $booking->update(['customer_id' => $booking->matchCustomer()?->id]);
        Audit::log('Website', 'BOOKING', $booking->car, 'From '.$booking->name.' '.$booking->phone);

        return true;
    }

    public function render()
    {
        // service names by category (painting price-list items are left out)
        $services = Service::active()->where('code', 'not like', 'PNT-%')->orderBy('name')->get(['name', 'category'])
            ->groupBy(fn ($s) => $s->category ?: __('Other services'))
            ->sortBy(fn ($list, $cat) => $cat === __('Other services') ? 'zzz' : $cat);

        return view('livewire.shop.garage', [
            'offer'    => Settings::pairs('web_garage_services'),
            'services' => $services,
            'me'       => auth('shop')->user(),
        ])->title(__('Garage, body work & paint'));
    }
}
