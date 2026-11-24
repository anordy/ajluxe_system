<?php

namespace App\Livewire\Shop;

use App\Models\Service;
use App\Support\Settings;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** The garage on the website: what we do and booking. No prices are shown here. */
#[Layout('components.layouts.shop')]
class Garage extends Component
{
    public function render()
    {
        // service names by category (painting price-list items are left out)
        $services = Service::active()->where('code', 'not like', 'PNT-%')->orderBy('name')->get(['name', 'category'])
            ->groupBy(fn ($s) => $s->category ?: __('Other services'))
            ->sortBy(fn ($list, $cat) => $cat === __('Other services') ? 'zzz' : $cat);

        return view('livewire.shop.garage', [
            'offer'    => Settings::pairs('web_garage_services'),
            'services' => $services,
        ])->title(__('Garage, body work & paint'));
    }
}
