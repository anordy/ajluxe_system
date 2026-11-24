<?php

namespace Tests\Feature;

use App\Livewire\Customers\Index as Customers;
use App\Livewire\Mechanics\Index as Mechanics;
use App\Livewire\Parts\Index as Parts;
use App\Livewire\Services\Index as Services;
use App\Livewire\Suppliers\Index as Suppliers;
use App\Livewire\Vehicles\Form as VehicleForm;
use App\Models\Customer;
use App\Models\Part;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2 checks: customers, vehicles, parts, suppliers, services, mechanics.
 */
class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function as(string $role): User
    {
        $u = User::factory()->role($role)->create();
        $this->actingAs($u);

        return $u;
    }

    private function customer(string $name = 'Juma Hassan'): Customer
    {
        return Customer::create(['code' => 'CUS-'.random_int(1000, 9999), 'name' => $name, 'type' => 'Individual']);
    }

    public function test_pages_open_for_the_right_roles(): void
    {
        $this->as(User::ADMIN);
        foreach (['/customers', '/vehicles', '/parts', '/suppliers', '/services', '/mechanics'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->as(User::STAFF);
        $this->get('/customers')->assertOk();
        $this->get('/parts')->assertOk();
        $this->get('/suppliers')->assertForbidden();
        $this->get('/services')->assertForbidden();

        $this->as(User::MECHANIC);
        $this->get('/vehicles')->assertOk();
        $this->get('/customers')->assertForbidden();
    }

    public function test_new_customer_gets_a_running_code(): void
    {
        $this->as(User::STAFF);

        Livewire::test(Customers::class)
            ->call('create')->set('form.name', 'Neema Mushi')->set('form.phone', '0712 000 111')
            ->call('save')->assertHasNoErrors();
        Livewire::test(Customers::class)
            ->call('create')->set('form.name', 'Kilimanjaro Tours Ltd')->set('form.type', 'Company')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(['CUS-0001', 'CUS-0002'], Customer::orderBy('id')->pluck('code')->all());
    }

    public function test_vehicle_reg_is_normalised_and_unique(): void
    {
        $this->as(User::MANAGER);
        $c = $this->customer();

        Livewire::test(VehicleForm::class)
            ->call('open', null, $c->id)->set('form.reg_no', '  t 123   abc ')->set('form.make', 'Toyota')
            ->call('save')->assertHasNoErrors()->assertDispatched('vehicle-saved');
        $this->assertDatabaseHas('vehicles', ['reg_no' => 'T 123 ABC', 'customer_id' => $c->id]);

        Livewire::test(VehicleForm::class)
            ->call('open', null, $c->id)->set('form.reg_no', 'T 123 abc')
            ->call('save')->assertHasErrors('form.reg_no');
    }

    public function test_customer_with_vehicles_cannot_be_deleted(): void
    {
        $this->as(User::ADMIN);
        $c = $this->customer();
        Vehicle::create(['customer_id' => $c->id, 'reg_no' => 'T 555 XYZ']);

        Livewire::test(Customers::class)->call('delete', $c->id)->assertDispatched('toast');
        $this->assertNotSoftDeleted($c);
    }

    public function test_part_with_opening_stock_records_a_movement(): void
    {
        $this->as(User::MANAGER);

        Livewire::test(Parts::class)
            ->call('create')
            ->set('form.name', 'Oil Filter')->set('form.oem_no', '90915-YZZE1')
            ->set('form.purchase_price', 9000)->set('form.selling_price', 15000)->set('form.reorder_level', 5)
            ->set('opening_qty', 12)
            ->call('save')->assertHasNoErrors();

        $part = Part::firstWhere('name', 'Oil Filter');
        $this->assertStringStartsWith('PRT-', $part->sku);
        $this->assertEquals(12, (float) $part->stock_qty);
        $this->assertDatabaseHas('stock_movements', ['part_id' => $part->id, 'type' => 'opening', 'qty_in' => 12, 'balance_after' => 12]);
        $this->assertFalse($part->isLow());
    }

    public function test_sales_staff_can_view_parts_but_not_add_them(): void
    {
        $this->as(User::STAFF);
        Livewire::test(Parts::class)->call('create')->assertForbidden();
    }

    public function test_hourly_service_default_charge_is_rate_times_hours(): void
    {
        $this->as(User::ADMIN);
        Livewire::test(Services::class)
            ->call('create')->set('form.name', 'Panel Beating (custom)')->set('form.pricing', 'hourly')
            ->set('form.rate', 25000)->set('form.est_hours', 4)
            ->call('save')->assertHasNoErrors();

        $s = Service::firstWhere('name', 'Panel Beating (custom)');
        $this->assertSame('TZS 25,000 / hr', $s->priceLabel());
        $this->assertEquals(100000, $s->defaultAmount());
        $this->assertSame('SRV-016', $s->code); // 15 seeded services come first
    }

    public function test_supplier_and_mechanic_can_be_added(): void
    {
        $this->as(User::MANAGER);
        Livewire::test(Suppliers::class)->call('create')->set('form.name', 'Kariakoo Spares')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('suppliers', ['name' => 'Kariakoo Spares', 'code' => 'SUP-0001']);

        $login = User::factory()->role(User::MECHANIC)->create(['name' => 'Baraka Said']);
        Livewire::test(Mechanics::class)->call('create')->set('form.user_id', $login->id)
            ->assertSet('form.name', 'Baraka Said')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('mechanics', ['name' => 'Baraka Said', 'user_id' => $login->id]);
    }
}
