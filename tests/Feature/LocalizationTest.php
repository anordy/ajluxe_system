<?php

namespace Tests\Feature;

use App\Livewire\Shop\CarRequestForm;
use App\Livewire\Website\Texts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The public website in English or Kiswahili; the staff portal stays English. */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_switches_the_website_to_kiswahili(): void
    {
        $this->seed();
        $this->get('/')->assertOk()->assertSee('Find parts for your car')->assertSee('Kiswahili')->assertSee('<html lang="en">', false);

        $this->from('/shop')->get(route('shop.language', 'sw'))->assertRedirect('/shop')->assertCookie('shop_locale', 'sw', false);
        $this->get('/')->assertOk()->assertSee('<html lang="sw">', false)
            ->assertSee('Tafuta vipuri vya gari lako')        // database text, Kiswahili copy
            ->assertSee('Onyesha vipuri vinavyofaa')          // template text
            ->assertSee('Hupati gari lako?')
            ->assertDontSee('Show parts that fit');
        // banners: the Kiswahili wording, English when none was written
        \App\Models\SiteBanner::create(['layout' => 'text', 'title' => 'Big brake sale', 'title_sw' => 'Ofa kubwa ya breki', 'tone' => 'red', 'is_active' => true]);
        \App\Models\SiteBanner::create(['layout' => 'text', 'title' => 'Wipers for the rains', 'tone' => 'ink', 'is_active' => true]);
        $this->get('/')->assertSee('Ofa kubwa ya breki')->assertDontSee('Big brake sale')->assertSee('Wipers for the rains');
        $this->get('/shop')->assertOk()->assertSee('Vipuri vyote');
        $this->get('/shop?category=Lubricants')->assertOk()->assertSee('Vilainishi');   // department name
        $this->get(route('shop.cart'))->assertOk()->assertSee('Kikapu chako ni tupu');

        // the choice is remembered on the device after the session ends
        $this->flushSession();
        $this->withUnencryptedCookie('shop_locale', 'sw')->get('/')->assertSee('Tafuta vipuri vya gari lako');

        // staff portal is not translated
        $this->actingAs(User::factory()->role(User::ADMIN)->create());
        $this->withSession(['shop_locale' => 'sw'])->get(route('dashboard'))->assertOk()->assertSee('Money collected');

        // back to English; unknown languages are refused
        $this->get(route('shop.language', 'en'));
        $this->get('/')->assertSee('Find parts for your car');
        $this->get('/language/fr')->assertNotFound();
    }

    public function test_messages_and_form_errors_in_kiswahili(): void
    {
        $this->seed();
        app()->setLocale('sw');
        Livewire::test(CarRequestForm::class)->call('show', 'Toyota', '')->call('send')
            ->assertHasErrors(['model', 'phone'])
            ->assertSee('Modeli inahitajika.')
            ->assertSee('Tupe namba ya simu au barua pepe ili tukujibu.');
        $this->assertSame('Habari, Asha', __('Hello, :name', ['name' => 'Asha']));
        $this->assertSame('matairi 3', trans_choice(':count tyre|:count tyres', 3));
    }

    public function test_staff_write_the_kiswahili_website_texts(): void
    {
        $this->seed();
        $this->actingAs(User::factory()->role(User::ADMIN)->create());
        Livewire::test(Texts::class)
            ->assertSet('form.web_hero_title_sw', 'Tafuta vipuri vya gari lako')
            ->set('form.web_hero_title_sw', 'Vipuri halisi kwa gari lako')
            ->set('form.web_topbar_sw', '')
            ->call('save')->assertHasNoErrors();

        $this->withSession(['shop_locale' => 'sw'])->get('/')
            ->assertSee('Vipuri halisi kwa gari lako')
            ->assertSee(setting('web_topbar'));   // empty Kiswahili box → English text
        $this->get(route('website.texts'))->assertOk()->assertSee('Kiswahili');
    }
}
