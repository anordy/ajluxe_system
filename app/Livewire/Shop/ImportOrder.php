<?php

namespace App\Livewire\Shop;

use App\Models\CarModel;
use App\Models\ImportRequest;
use App\Support\Phone;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** "Import for you": custom orders for engines, gearboxes and parts from abroad. */
#[Layout('components.layouts.shop')]
class ImportOrder extends Component
{
    #[Url(as: 'item', except: '')]
    public string $item_type = '';

    public string $description = '';
    public string $make = '';
    public string $model = '';
    public string $year = '';
    public string $engine_code = '';
    public string $vin = '';
    public string $source_country = '';
    public string $condition = '';
    public string $quantity = '1';
    public string $budget = '';
    public string $name = '';
    public string $phone = '';
    public string $email = '';

    public ?string $sentRef = null;

    public function mount(): void
    {
        if ($me = auth('shop')->user()) {
            $this->name = (string) $me->name;
            $this->phone = (string) $me->phone;
            $this->email = (string) $me->email;
        }
        if (! in_array($this->item_type, ImportRequest::ITEM_TYPES, true)) {
            $this->item_type = '';
        }
    }

    public function send(): void
    {
        $this->vin = strtoupper(preg_replace('/\s+/', '', $this->vin));
        $this->budget = preg_replace('/[^\d]/', '', $this->budget);
        $countries = [...ImportRequest::countries(), 'Any'];
        $data = $this->validate([
            'item_type'      => ['required', Rule::in(ImportRequest::ITEM_TYPES)],
            'description'    => 'required|string|min:3|max:1000',
            'make'           => 'required|string|max:60',
            'model'          => 'required|string|max:80',
            'year'           => 'nullable|integer|min:1960|max:'.(now()->year + 1),
            'engine_code'    => 'nullable|string|max:40',
            'vin'            => ['nullable', 'string', 'max:20', 'regex:/^[A-HJ-NPR-Z0-9-]{5,20}$/'],
            'source_country' => ['nullable', Rule::in($countries)],
            'condition'      => ['nullable', Rule::in(ImportRequest::CONDITIONS)],
            'quantity'       => 'required|integer|min:1|max:99',
            'budget'         => 'nullable|integer|min:0|max:999999999',
            'name'           => 'required|string|max:120',
            'phone'          => 'required|string|max:30',
            'email'          => 'nullable|email|max:150',
        ], [
            'description.required' => __('Tell us what part you need.'),
            'vin.regex'            => __('A chassis / VIN number has letters and numbers only (no I, O or Q).'),
            'item_type.required'   => __('Choose what you need.'),
        ], ['make' => 'car make', 'model' => 'car model']);

        $key = 'import-request:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('description', __('You have sent several requests already. Please try again in an hour or call us.'));

            return;
        }
        RateLimiter::hit($key, 3600);

        $r = ImportRequest::create([
            ...array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v), $data),
            'phone'            => Phone::normalize($data['phone']),
            'shop_customer_id' => auth('shop')->id(),
        ]);
        $this->sentRef = $r->fresh()->ref;
        $this->reset(['description', 'engine_code', 'vin', 'budget', 'year']);
        $this->quantity = '1';
    }

    public function another(): void
    {
        $this->sentRef = null;
    }

    public function render()
    {
        return view('livewire.shop.import-order', [
            'makes' => CarModel::where('is_active', true)->distinct()->orderBy('make')->pluck('make'),
        ])->title(setting('web_import_title', 'Import for you'));
    }
}
