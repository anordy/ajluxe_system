<?php

namespace Tests\Feature;

use App\Livewire\Shop\Auth\GoogleFinish;
use App\Livewire\Shop\Auth\Login;
use App\Models\Customer;
use App\Models\ShopCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** "Continue with Google" on the website. */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['services.google.client_id' => 'test-client.apps.googleusercontent.com', 'services.google.client_secret' => 'test-secret']);
    }

    private function signOut(): void
    {
        Auth::guard('shop')->logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    /** Go to Google and come back with a code for this Google profile. */
    private function backFromGoogle(array $profile)
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at-123', 'id_token' => 'x']),
            'openidconnect.googleapis.com/*' => Http::response($profile + ['email_verified' => true]),
        ]);
        $to = $this->get(route('shop.google'))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $to);
        parse_str(parse_url($to, PHP_URL_QUERY), $q);
        $this->assertSame(route('shop.google.callback'), $q['redirect_uri']);
        $this->assertSame('S256', $q['code_challenge_method']);

        return $this->get(route('shop.google.callback', ['code' => 'abc', 'state' => $q['state']]));
    }

    public function test_new_customer_signs_up_with_google_and_adds_a_phone(): void
    {
        $this->get(route('shop.login'))->assertSee('Continue with Google');
        $this->get(route('shop.register'))->assertSee('Continue with Google');

        $this->backFromGoogle(['sub' => 'g-111', 'email' => 'Asha@Gmail.com', 'name' => 'Asha Juma'])
            ->assertRedirect(route('shop.google.finish'));
        $this->assertGuest('shop');

        Livewire::test(GoogleFinish::class)->assertSet('name', 'Asha Juma')
            ->set('phone', '0712 345 678')->call('finish')->assertHasNoErrors()->assertRedirect(route('shop.account'));
        $a = ShopCustomer::where('google_id', 'g-111')->first();
        $this->assertSame('0712345678', $a->phone);
        $this->assertSame('asha@gmail.com', $a->email);
        $this->assertNull($a->password);
        $this->assertTrue(Customer::where('phone', '0712345678')->exists());
        $this->assertAuthenticatedAs($a, 'shop');

        // next time: straight in
        $this->signOut();
        $this->backFromGoogle(['sub' => 'g-111', 'email' => 'asha@gmail.com', 'name' => 'Asha Juma'])->assertRedirect(route('shop.account'));
        $this->assertAuthenticatedAs($a, 'shop');
        // a Google-only account cannot be opened with an empty password
        $this->signOut();
        Livewire::test(Login::class)->set('login', '0712345678')->set('password', 'anything')->call('signIn')->assertHasErrors('login');
    }

    public function test_existing_account_is_linked_by_email_or_by_logging_in_once(): void
    {
        $byEmail = ShopCustomer::create(['name' => 'Juma', 'phone' => '0754000001', 'email' => 'juma@example.com', 'password' => 'secret-pass-1']);
        $this->backFromGoogle(['sub' => 'g-222', 'email' => 'juma@example.com', 'name' => 'Juma'])->assertRedirect(route('shop.account'));
        $this->assertSame('g-222', $byEmail->fresh()->google_id);
        $this->signOut();

        // different e-mail, same phone → must log in with the password once
        $byPhone = ShopCustomer::create(['name' => 'Neema', 'phone' => '0754000002', 'password' => 'secret-pass-2']);
        $this->backFromGoogle(['sub' => 'g-333', 'email' => 'neema@gmail.com', 'name' => 'Neema']);
        Livewire::test(GoogleFinish::class)->set('phone', '0754000002')->call('finish')->assertHasErrors('phone')->assertSee('Log in with your password');
        $this->assertSame(2, ShopCustomer::count());
        Livewire::test(Login::class)->set('login', '0754000002')->set('password', 'secret-pass-2')->call('signIn')->assertHasNoErrors();
        $this->assertSame('g-333', $byPhone->fresh()->google_id);
        $this->assertSame('neema@gmail.com', $byPhone->fresh()->email);
    }

    public function test_forged_or_failed_answers_are_refused(): void
    {
        Http::fake();
        $this->get(route('shop.google'));
        $this->get(route('shop.google.callback', ['code' => 'abc', 'state' => 'made-up']))->assertRedirect(route('shop.login'))->assertSessionHas('toast');
        $this->assertGuest('shop');
        Http::assertNothingSent();
        $this->get(route('shop.google.callback', ['error' => 'access_denied']))->assertRedirect(route('shop.login'));
        $this->get(route('shop.google.finish'))->assertRedirect(route('shop.login'));

        // hidden when not set up
        config(['services.google.client_id' => null]);
        $this->get(route('shop.login'))->assertDontSee('Continue with Google');
        $this->get(route('shop.google'))->assertNotFound();
    }
}
