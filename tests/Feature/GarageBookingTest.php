<?php

namespace Tests\Feature;

use App\Livewire\Bookings\Index as Bookings;
use App\Livewire\JobCards\Create as NewJob;
use App\Livewire\Shop\Garage;
use App\Models\Customer;
use App\Models\GarageBooking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GarageBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_booking_from_website_reaches_the_garage_and_becomes_a_job_card(): void
    {
        $c = Customer::create(['code' => 'CUS-9', 'name' => 'Anord Patrick', 'phone' => '0754 711 117', 'type' => 'Individual']);
        $v = Vehicle::create(['customer_id' => $c->id, 'reg_no' => 'T340EHH', 'make' => 'Subaru', 'model' => 'Forester']);

        Livewire::test(Garage::class)->call('book', ['name' => 'Anord Patrick', 'phone' => '0754711117', 'car' => 'Subaru Forester XT',
            'plate' => 'T 340 EHH', 'job' => 'Servicing & repairs, polishing', 'day' => today()->addDay()->toDateString()])->assertReturned(true);
        $b = GarageBooking::firstOrFail();
        $this->assertSame($c->id, $b->customer_id);

        // workshop manager sees it on the dashboard and the bookings page
        $wm = User::factory()->role(User::WORKSHOP)->create();
        $this->actingAs($wm);
        $this->get(route('dashboard'))->assertOk()->assertSee('Next garage bookings')->assertSee('Subaru Forester XT')
            ->assertSee('Garage money collected')->assertDontSee('Stock value');
        $this->get(route('bookings.index'))->assertOk()->assertSee('Subaru Forester XT');
        $this->get(route('pos'))->assertForbidden();

        Livewire::test(Bookings::class)->call('open', $b->id)->call('setStatus', 'confirmed');
        $this->assertSame('confirmed', $b->fresh()->status);

        // car arrives: job card filled from the booking, booking linked
        Livewire::test(NewJob::class, ['booking_id' => $b->id])
            ->assertSet('vehicle_id', $v->id)->assertSet('complaints', 'Servicing & repairs, polishing')
            ->call('save')->assertHasNoErrors();
        $b->refresh();
        $this->assertSame('booked_in', $b->status);
        $this->assertNotNull($b->job_card_id);
    }

    public function test_new_role_gets_defaults_even_after_earlier_save(): void
    {
        \App\Support\Permissions::save(['manager' => ['pos'], 'staff' => [], 'mechanic' => []]);   // saved before the role existed
        \Illuminate\Support\Facades\DB::table('role_permissions')->where('role', User::WORKSHOP)->delete();
        \Illuminate\Support\Facades\DB::table('role_permissions')->where('permission', 'bookings')->delete();
        \App\Support\Permissions::flush();
        $this->assertTrue(\App\Support\Permissions::roleHas(User::WORKSHOP, 'job_cards.manage'));
        $this->assertTrue(\App\Support\Permissions::roleHas(User::MANAGER, 'bookings'));        // permission added later
        $this->assertFalse(\App\Support\Permissions::roleHas(User::MANAGER, 'reports'));       // saved choice kept
    }
}
