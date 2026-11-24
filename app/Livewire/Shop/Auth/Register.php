<?php

namespace App\Livewire\Shop\Auth;

use App\Models\Customer;
use App\Models\ShopCustomer;
use App\Support\Audit;
use App\Support\Numbering;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.shop')]
class Register extends Component
{
    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register()
    {
        $key = 'shop-register:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('phone', __('Too many sign-ups from this connection. Try again in :n minutes.', ['n' => ceil(RateLimiter::availableIn($key) / 60)]));

            return;
        }

        $this->phone = Phone::normalize($this->phone);
        $this->email = strtolower(trim($this->email));
        $this->validate([
            'name'     => 'required|string|min:2|max:120',
            'phone'    => ['required', 'regex:/^0[67]\d{8}$/', 'unique:shop_customers,phone'],
            'email'    => 'nullable|email|max:120|unique:shop_customers,email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'phone.regex'  => __('Enter a Tanzanian mobile number, e.g. 0754 123 456.'),
            'phone.unique' => __('An account with this phone number already exists. Log in instead.'),
            'email.unique' => __('An account with this email already exists. Log in instead.'),
        ]);
        RateLimiter::hit($key, 3600);

        $account = DB::transaction(function () {
            // link to the customer record the shop already has for this phone (so past invoices connect)
            $customer = Customer::where('phone', $this->phone)->first()
                ?? Customer::create([
                    'code' => Numbering::code('customer', 'CUS'), 'name' => trim($this->name), 'phone' => $this->phone,
                    'email' => $this->email ?: null, 'type' => 'Individual', 'is_active' => true, 'notes' => 'Signed up on the website',
                ]);

            return ShopCustomer::create([
                'customer_id' => $customer->id,
                'name'        => trim($this->name),
                'phone'       => $this->phone,
                'email'       => $this->email ?: null,
                'password'    => $this->password,
            ]);
        });

        Auth::guard('shop')->login($account, true);
        \App\Support\Wishlist::mergeGuest($account);
        session()->regenerate();
        Audit::log('Website', 'SIGN UP', $account->phone, $account->name);
        session()->flash('toast', ['message' => __('Welcome, :name. Your account is ready.', ['name' => $account->firstName()]), 'type' => 'success']);

        return redirect()->to(ShopIntended::pull());
    }

    public function render()
    {
        return view('livewire.shop.auth.register')->title(__('Create an account'));
    }
}
