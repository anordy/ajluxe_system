<?php

namespace App\Livewire\Shop;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\OrderManager;
use App\Support\Cart;
use App\Support\Phone;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.shop')]
class Checkout extends Component
{
    public string $fulfilment = 'pickup';
    public string $delivery_address = '';
    public string $contact_phone = '';
    public string $payment_method = 'lipa_namba';
    public string $payment_reference = '';
    public string $notes = '';

    public function mount()
    {
        if (Cart::count() === 0) {
            return redirect()->route('shop.cart');
        }
        $me = auth('shop')->user();
        $this->contact_phone = $me->phone;
        $this->delivery_address = (string) $me->customer?->address;
    }

    public function place(OrderManager $orders)
    {
        $this->contact_phone = Phone::normalize($this->contact_phone);
        $this->validate([
            'fulfilment'        => ['required', Rule::in(array_keys(Order::FULFILMENT))],
            'delivery_address'  => 'required_if:fulfilment,delivery|nullable|string|max:255',
            'contact_phone'     => ['required', 'regex:/^0[67]\d{8}$/'],
            'payment_method'    => ['required', Rule::in(array_keys(Order::PAYMENT_METHODS))],
            'payment_reference' => 'nullable|string|max:60',
            'notes'             => 'nullable|string|max:500',
        ], [
            'delivery_address.required_if' => __('Tell us where to deliver.'),
            'contact_phone.regex'          => __('Enter a Tanzanian mobile number, e.g. 0754 123 456.'),
        ]);

        try {
            $order = $orders->place(auth('shop')->user(), $this->only(['fulfilment', 'delivery_address', 'contact_phone', 'payment_method', 'payment_reference', 'notes']));
        } catch (BusinessRuleException $e) {
            $this->addError('order', $e->getMessage());

            return;
        }

        session()->flash('placed', true);

        return $this->redirect(route('shop.order', $order), navigate: true);
    }

    public function render()
    {
        $lines = Cart::lines();

        return view('livewire.shop.checkout', ['lines' => $lines, 'totals' => Cart::totals($lines)])->title(__('Checkout'));
    }
}
