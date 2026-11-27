<?php

namespace Tests\Feature;

use App\Livewire\Settings\Edit;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_is_only_on_the_website_when_an_id_is_set(): void
    {
        $this->seed();
        config(['ajluxe.launch_at' => null]);

        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com', false);

        Settings::set(['ga_measurement_id' => 'G-AB12CD34EF']);
        $this->get('/')->assertOk()->assertSee('googletagmanager.com/gtag/js?id=G-AB12CD34EF', false)->assertSee('livewire:navigated', false);
        $this->get(route('shop.catalog'))->assertSee('G-AB12CD34EF', false);
    }

    public function test_settings_page_saves_and_checks_the_id(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin);

        Livewire::test(Edit::class)->set('s.ga_measurement_id', 'not-an-id')->call('save')->assertHasErrors('s.ga_measurement_id');
        Livewire::test(Edit::class)->set('s.ga_measurement_id', ' g-ab12cd34ef ')->call('save')->assertHasNoErrors();
        $this->assertSame('G-AB12CD34EF', Settings::get('ga_measurement_id'));

        Livewire::test(Edit::class)->set('s.ga_measurement_id', '')->call('save')->assertHasNoErrors();
        $this->assertSame('', (string) Settings::get('ga_measurement_id'));
    }
}
