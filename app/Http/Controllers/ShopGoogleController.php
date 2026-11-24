<?php

namespace App\Http\Controllers;

use App\Livewire\Shop\Auth\ShopIntended;
use App\Models\ShopCustomer;
use App\Support\Audit;
use App\Support\GoogleSignIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/** "Continue with Google" on the website's log-in and sign-up pages. */
class ShopGoogleController extends Controller
{
    public function redirect()
    {
        abort_unless(GoogleSignIn::enabled(), 404);

        // opened on another address (www., http): go to the APP_URL one first, so the return lands in the same session
        $start = GoogleSignIn::startUrl();
        if (rtrim(request()->url(), '/') !== rtrim($start, '/')) {
            return redirect()->away($start);
        }

        return redirect()->away(GoogleSignIn::redirectUrl());
    }

    public function callback(Request $request)
    {
        abort_unless(GoogleSignIn::enabled(), 404);
        $key = 'shop-google:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return $this->fail(__('Too many attempts. Try again in :n seconds.', ['n' => RateLimiter::availableIn($key)]));
        }
        RateLimiter::hit($key, 600);

        if ($request->filled('error')) {          // the visitor pressed Cancel at Google
            return redirect()->route('shop.login');
        }
        try {
            $g = GoogleSignIn::user($request->query('code'), $request->query('state'));
        } catch (RuntimeException $e) {
            report($e);

            return $this->fail(__('Google sign-in did not finish. Please try again.'));
        }

        // 1. already linked  2. same e-mail that Google has checked → link it
        $account = ShopCustomer::where('google_id', $g['sub'])->first();
        if (! $account && $g['email_verified'] && $g['email'] !== '') {
            $account = ShopCustomer::where('email', $g['email'])->first();
            if ($account && ! $account->google_id) {
                $account->forceFill(['google_id' => $g['sub']])->save();
                Audit::log('Website', 'GOOGLE LINKED', $account->phone, $account->name);
            } elseif ($account) {
                $account = null;                  // that e-mail belongs to another Google account
            }
        }
        if ($account) {
            return $this->signIn($account);
        }

        // 3. new here: Google gives no phone number, so ask for it once
        session(['google_signup' => [
            'sub'   => $g['sub'],
            'email' => $g['email_verified'] ? $g['email'] : '',
            'name'  => $g['name'],
        ]]);

        return redirect()->route('shop.google.finish');
    }

    private function signIn(ShopCustomer $account)
    {
        Auth::guard('shop')->login($account, true);
        \App\Support\Wishlist::mergeGuest($account);
        session()->regenerate();
        $account->forceFill(['last_login_at' => now()])->save();
        session()->flash('toast', ['message' => __('Welcome back, :name.', ['name' => $account->firstName()]), 'type' => 'success']);

        return redirect()->to(ShopIntended::pull());
    }

    private function fail(string $message)
    {
        return redirect()->route('shop.login')->with('toast', ['message' => $message, 'type' => 'error']);
    }
}
