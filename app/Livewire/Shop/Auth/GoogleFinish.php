<?php

namespace App\Livewire\Shop\Auth;

use App\Models\Customer;
use App\Models\ShopCustomer;
use App\Support\Audit;
use App\Support\Numbering;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Last step of a first "Continue with Google": Google gives name and e-mail but no phone,
 * and the shop needs a mobile number to confirm orders (and to find past invoices).
 */
#[Layout('components.layouts.shop')]
class GoogleFinish extends Component
{
    public string $name = '';
    public string $phone = '';
    public bool $taken = false;

    public function mount()
    {
        $g = session('google_signup');
        if (! $g) {
            return redirect()->route('shop.login');
        }
        $this->name = $g['name'];
    }

    public function finish()
    {
        $g = session('google_signup');
        if (! $g) {
            return redirect()->route('shop.login');
        }
        $this->phone = Phone::normalize($this->phone);
        $this->taken = false;
        $this->validate([
            'name'  => 'required|string|min:2|max:120',
            'phone' => ['required', 'regex:/^0[67]\d{8}$/'],
        ], ['phone.regex' => __('Enter a Tanzanian mobile number, e.g. 0754 123 456.')]);

        // a website account with this number already exists: it must log in with its password once
        if (ShopCustomer::where('phone', $this->phone)->exists()) {
            $this->taken = true;
            $this->addError('phone', __('This number already has an account. Log in with your password once and Google will be linked to it.'));

            return;
        }

        $account = DB::transaction(function () use ($g) {
            $email = $g['email'] !== '' && ! ShopCustomer::where('email', $g['email'])->exists() ? $g['email'] : null;
            $customer = Customer::where('phone', $this->phone)->first()
                ?? Customer::create([
                    'code' => Numbering::code('customer', 'CUS'), 'name' => trim($this->name), 'phone' => $this->phone,
                    'email' => $email, 'type' => 'Individual', 'is_active' => true, 'notes' => 'Signed up on the website with Google',
                ]);

            return ShopCustomer::create([
                'customer_id' => $customer->id,
                'name'        => trim($this->name),
                'phone'       => $this->phone,
                'email'       => $email,
                'google_id'   => $g['sub'],
                'password'    => null,
            ]);
        });

        session()->forget('google_signup');
        Auth::guard('shop')->login($account, true);
        \App\Support\Wishlist::mergeGuest($account);
        session()->regenerate();
        $account->forceFill(['last_login_at' => now()])->save();
        Audit::log('Website', 'SIGN UP (GOOGLE)', $account->phone, $account->name);
        session()->flash('toast', ['message' => __('Welcome, :name. Your account is ready.', ['name' => $account->firstName()]), 'type' => 'success']);

        return redirect()->to(ShopIntended::pull());
    }

    public function render()
    {
        return view('livewire.shop.auth.google-finish', ['email' => session('google_signup.email')])->title(__('Finish signing up'));
    }
}
