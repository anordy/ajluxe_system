<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** With ADMIN_DOMAIN set, the staff portal answers only on its own address. */
class AdminDomainTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://ajluxeauto.co.tz';
    private const ADMIN = 'https://dashboard.ajluxeauto.co.tz';

    public function test_staff_portal_only_on_its_own_address(): void
    {
        $this->seed();
        config(['ajluxe.admin_domain' => 'dashboard.ajluxeauto.co.tz']);

        // public website: shop works, staff pages do not exist, no staff link
        $this->get(self::SITE.'/')->assertOk()->assertDontSee('Staff login');
        $this->get(self::SITE.'/login')->assertNotFound();
        $this->get(self::SITE.'/dashboard')->assertNotFound();
        $this->get(self::SITE.'/job-cards')->assertNotFound();
        $this->get(self::SITE.'/account/login')->assertOk();

        // admin address: login and dashboard; the shop is not served there
        $this->get(self::ADMIN.'/')->assertRedirect('/dashboard');
        $this->get(self::ADMIN.'/dashboard')->assertRedirect();
        $this->get(self::ADMIN.'/login')->assertOk();
        $this->get(self::ADMIN.'/shop')->assertNotFound();

        $this->actingAs(User::factory()->role(User::ADMIN)->create(), 'web');
        $this->get(self::ADMIN.'/dashboard')->assertOk();
        $this->get(self::SITE.'/dashboard')->assertNotFound();   // even when signed in
    }

    public function test_without_admin_domain_everything_stays_on_one_address(): void
    {
        $this->seed();
        config(['ajluxe.admin_domain' => null]);
        $this->get('/')->assertOk()->assertSee('Staff login');
        $this->get('/login')->assertOk();
    }
}
