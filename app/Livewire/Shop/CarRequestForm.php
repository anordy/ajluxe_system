<?php

namespace App\Livewire\Shop;

use App\Models\CarModel;
use App\Models\CarRequest;
use App\Models\Part;
use App\Support\Phone;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\On;
use Livewire\Component;

/** "Can't find your car?" side panel. Lives once in the shop layout; open it with Livewire.dispatch('open-car-request'). */
class CarRequestForm extends Component
{
    public bool $open = false;
    public bool $sent = false;

    public string $vin = '';
    public string $make = '';
    public string $model = '';
    public string $year = '';
    public string $parts = '';
    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public bool $wants_offers = false;

    #[On('open-car-request')]
    public function show(string $make = '', string $model = '', string $parts = ''): void
    {
        $this->resetValidation();
        $this->sent = false;
        $this->make = $make ?: $this->make;
        $this->model = $model ?: $this->model;
        $this->parts = $parts ?: $this->parts;
        if ($me = auth('shop')->user()) {
            $this->name = $this->name ?: (string) $me->name;
            $this->phone = $this->phone ?: (string) $me->phone;
            $this->email = $this->email ?: (string) $me->email;
        }
        $this->open = true;
    }

    public function send(): void
    {
        $this->vin = strtoupper(preg_replace('/\s+/', '', $this->vin));
        $data = $this->validate([
            'vin'          => ['nullable', 'string', 'max:20', 'regex:/^[A-HJ-NPR-Z0-9-]{5,20}$/'],
            'make'         => 'required|string|max:60',
            'model'        => 'required|string|max:80',
            'year'         => 'nullable|integer|min:1960|max:'.(now()->year + 1),
            'parts'        => 'nullable|string|max:500',
            'name'         => 'nullable|string|max:120',
            'phone'        => 'required_without:email|nullable|string|max:30',
            'email'        => 'required_without:phone|nullable|email|max:150',
            'wants_offers' => 'boolean',
        ], [
            'vin.regex'              => __('A VIN has letters and numbers only (no I, O or Q).'),
            'phone.required_without' => __('Give us a phone number or an email so we can reply.'),
            'email.required_without' => __('Give us a phone number or an email so we can reply.'),
            'year.max'               => __('Check the year.'),
        ]);

        $key = 'car-request:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('make', __('You have sent several requests already. Please try again in an hour or call us.'));

            return;
        }
        RateLimiter::hit($key, 3600);

        CarRequest::create([
            ...array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $data),
            'phone'            => $data['phone'] ? Phone::normalize($data['phone']) : null,
            'year'             => $data['year'] !== '' && $data['year'] !== null ? (int) $data['year'] : null,
            'shop_customer_id' => auth('shop')->id(),
        ]);

        $this->reset(['vin', 'year', 'parts']);
        $this->sent = true;
    }

    public function render()
    {
        // suggestions for the make box: the car list plus makes on parts sold online
        $makes = CarModel::where('is_active', true)->distinct()->pluck('make')
            ->merge(Part::online()->whereNotNull('vehicle_make')->where('vehicle_make', '!=', '')->distinct()->pluck('vehicle_make'))
            ->unique()->sort()->values();

        return view('livewire.shop.car-request-form', ['makes' => $makes]);
    }
}
