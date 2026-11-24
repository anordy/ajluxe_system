<?php

namespace App\Livewire\Shop\Auth;

use App\Models\ShopCustomer;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.shop')]
class Login extends Component
{
    public string $login = '';
    public string $password = '';
    public bool $remember = true;

    public function signIn()
    {
        $this->validate(['login' => 'required|string|max:120', 'password' => 'required|string']);

        $key = 'shop-login:'.Str::lower($this->login).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('login', __('Too many attempts. Try again in :n seconds.', ['n' => RateLimiter::availableIn($key)]));

            return;
        }

        $field = filter_var($this->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $value = $field === 'email' ? strtolower(trim($this->login)) : Phone::normalize($this->login);

        if (! Auth::guard('shop')->attempt([$field => $value, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            $this->addError('login', __('Wrong phone/email or password.'));

            return;
        }

        RateLimiter::clear($key);
        session()->regenerate();
        Auth::guard('shop')->user()->forceFill(['last_login_at' => now()])->save();
        \App\Support\Wishlist::mergeGuest(Auth::guard('shop')->user());

        // came from "Continue with Google" with a number that already had an account: link Google now
        if ($g = session()->pull('google_signup')) {
            $me = Auth::guard('shop')->user();
            if (! $me->google_id && ! \App\Models\ShopCustomer::where('google_id', $g['sub'])->exists()) {
                $me->forceFill(['google_id' => $g['sub'], 'email' => $me->email ?: ($g['email'] !== '' && ! \App\Models\ShopCustomer::where('email', $g['email'])->exists() ? $g['email'] : null)])->save();
                \App\Support\Audit::log('Website', 'GOOGLE LINKED', $me->phone, $me->name);
            }
        }

        return redirect()->to(ShopIntended::pull());
    }

    public function render()
    {
        return view('livewire.shop.auth.login')->title(__('Log in'));
    }
}
