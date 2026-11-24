<?php

namespace App\Livewire\Auth;

use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Sign in')]
class Login extends Component
{
    public string $username = '';
    public string $password = '';
    public bool $remember = false;

    public function login()
    {
        $this->validate([
            'username' => 'required|string|max:120',
            'password' => 'required|string',
        ]);

        // 5 wrong attempts per minute per username+IP, then a short lock-out
        $key = 'login:'.Str::lower($this->username).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'username' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        // sign in with username OR email
        $field = filter_var($this->username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [$field => trim($this->username), 'password' => $this->password, 'is_active' => true];

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'username' => 'Wrong username or password, or the account is inactive.',
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('Auth', 'LOGIN', $user->username);

        return redirect()->intended(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
