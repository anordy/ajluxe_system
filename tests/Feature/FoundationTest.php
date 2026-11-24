<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use App\Support\Money;
use App\Support\Numbering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1 checks. Run on the server with:  php artisan test
 */
class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_is_formatted_as_tzs_without_decimals(): void
    {
        $this->assertSame('TZS 364,000', Money::format(364000, 'TZS'));
        $this->assertSame('-TZS 1,234,568', Money::format(-1234567.6, 'TZS'));
        $this->assertSame('1,000', Money::amount(999.5));
    }

    public function test_document_numbers_increase_and_include_the_year(): void
    {
        $year = date('Y');
        $this->assertSame("JC-{$year}-0001", Numbering::next('job_card'));
        $this->assertSame("JC-{$year}-0002", Numbering::next('job_card'));
        $this->assertSame("INV-{$year}-0001", Numbering::next('invoice'));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Welcome back');
    }

    public function test_user_can_sign_in_with_username(): void
    {
        $user = User::factory()->create(['username' => 'juma']);

        Livewire::test(Login::class)
            ->set('username', 'juma')->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['username' => 'old']);

        Livewire::test(Login::class)
            ->set('username', 'old')->set('password', 'password')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    public function test_temporary_password_forces_password_change(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.change'));
    }

    public function test_only_admin_can_open_users_and_settings(): void
    {
        $staff = User::factory()->role(User::STAFF)->create();
        $this->actingAs($staff)->get('/users')->assertForbidden();
        $this->actingAs($staff)->get('/settings')->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/users')->assertOk();
        $this->actingAs($admin)->get('/settings')->assertOk();
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Money collected')->assertDontSee('Getting Started');
    }

    public function test_seeder_creates_one_admin_and_is_safe_to_rerun(): void
    {
        $this->seed();
        $this->seed();
        $this->assertSame(1, User::where('role', User::ADMIN)->count());
        $this->assertContains('M-Pesa', list_options('payment_mode'));
    }

    public function test_admin_can_save_settings(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(\App\Livewire\Settings\Edit::class)
            ->set('s.phone', '+255 712 345 678')
            ->set('s.address', 'Mikocheni B, Dar es Salaam')
            ->set('s.currency', 'tzs')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('+255 712 345 678', setting('phone'));
        $this->assertSame('TZS', setting('currency'));
    }

    public function test_cannot_remove_the_last_active_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(\App\Livewire\Users\Index::class)
            ->call('edit', $admin->id)->set('role', User::STAFF)->call('save')
            ->assertHasErrors('role');

        $this->assertSame(User::ADMIN, $admin->fresh()->role);
    }
}
