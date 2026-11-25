<?php

namespace Tests\Feature;

use App\Livewire\Users\Roles;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_defaults_match_the_old_fixed_roles(): void
    {
        $staff = User::factory()->role(User::STAFF)->create();
        $this->actingAs($staff);
        $this->get(route('pos'))->assertOk();
        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Stock value');

        $this->actingAs(User::factory()->role(User::MECHANIC)->create());
        $this->get(route('job-cards.index'))->assertOk();
        $this->get(route('pos'))->assertForbidden();
    }

    public function test_admin_changes_ticks_and_they_apply(): void
    {
        $admin = User::factory()->role(User::ADMIN)->create();
        $this->actingAs($admin);
        $this->get(route('roles.index'))->assertOk()->assertSee('Save permissions');

        Livewire::test(Roles::class)
            ->set('ticks.staff.reports', true)
            ->set('ticks.staff.dashboard__money', true)
            ->set('ticks.staff.pos', false)
            ->call('save');

        $staff = User::factory()->role(User::STAFF)->create();
        $this->actingAs($staff);
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('pos'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertSee('Stock value');
        $this->assertTrue($admin->allows('pos'));                     // admin always has all

        // only the administrator may open the page
        $this->get(route('roles.index'))->assertForbidden();
    }

    public function test_fill_in_defaults_restores_ticks(): void
    {
        $this->actingAs(User::factory()->role(User::ADMIN)->create());
        Permissions::save(['staff' => [], 'manager' => [], 'mechanic' => []]);
        $this->assertFalse(Permissions::roleHas('manager', 'pos'));
        Livewire::test(Roles::class)->call('defaults')->call('save');
        $this->assertTrue(Permissions::roleHas('manager', 'pos'));
    }
}
