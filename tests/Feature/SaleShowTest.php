<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\User;
use App\Services\SalesManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every sale can be opened: customer, exactly what was bought, payments, prints. */
class SaleShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_counter_sale_page_shows_customer_items_and_payments(): void
    {
        $this->seed();
        $staff = User::factory()->role(User::STAFF)->create();
        $this->actingAs($staff);
        $p = Part::create(['sku' => 'S-1', 'name' => 'Brake Pads Hilux', 'brand' => 'Bosch', 'category' => 'Car Parts', 'unit' => 'Set',
            'purchase_price' => 40000, 'selling_price' => 65000, 'is_active' => true]);
        $p->forceFill(['stock_qty' => 10])->save();
        $c = Customer::create(['code' => 'CUS-7001', 'name' => 'Neema Traders', 'type' => 'Business', 'phone' => '0713000000', 'email' => 'neema@example.com', 'address' => 'Kariakoo']);

        $inv = app(SalesManager::class)->createCounterSale(
            [['part_id' => $p->id, 'qty' => 2, 'rate' => 65000]],
            ['customer_id' => $c->id, 'discount' => 0, 'tax_rate' => 0, 'paid' => 100000, 'mode' => 'M-Pesa'],
        );

        $this->get(route('invoices.show', $inv))->assertOk()
            ->assertSee($inv->number)->assertSee('Shop counter (POS)')
            ->assertSee('Neema Traders')->assertSee('0713000000')->assertSee('neema@example.com')->assertSee('Kariakoo')
            ->assertSee('Brake Pads Hilux')->assertSee('S-1')->assertSee('Bosch')->assertSee('2 Set')
            ->assertSee('130,000')->assertSee('100,000')->assertSee('30,000')     // total, paid, balance
            ->assertSee('M-Pesa')->assertSee('Record payment')->assertSee('Receipt (80mm)');

        // linked from the POS and the invoices list
        $this->get(route('pos'))->assertSee(route('invoices.show', $inv), false)->assertSee('Recent sales');
        $this->get(route('invoices.index'))->assertSee(route('invoices.show', $inv), false);

        // mechanics cannot open shop sales
        $this->actingAs(User::factory()->role(User::MECHANIC)->create());
        $this->get(route('invoices.show', $inv))->assertForbidden();
    }

    public function test_manager_can_undo_a_payment_entered_by_mistake(): void
    {
        $this->seed();
        $this->actingAs(User::factory()->role(User::MANAGER)->create());
        $p = Part::create(['sku' => 'S-2', 'name' => 'Wiper Blade', 'category' => 'Car Parts', 'unit' => 'Pcs',
            'purchase_price' => 5000, 'selling_price' => 10000, 'is_active' => true]);
        $p->forceFill(['stock_qty' => 5])->save();
        $inv = app(SalesManager::class)->createCounterSale([['part_id' => $p->id, 'qty' => 1, 'rate' => 10000]], ['paid' => 10000, 'mode' => 'Cash']);
        $this->assertSame(Invoice::PAID, $inv->fresh()->status);
        $pay = $inv->payments()->first();

        \Livewire\Livewire::test(\App\Livewire\Sales\Show::class, ['invoice' => $inv])
            ->call('undoPayment', $pay->id)->assertDispatched('toast')
            ->set('undoReason', 'Marked paid by mistake')->call('undoPayment', $pay->id)
            ->assertSee('Undone by');

        $inv->refresh();
        $this->assertSame(Invoice::UNPAID, $inv->status);
        $this->assertEquals(0, (float) $inv->paid);
        $this->assertEquals(10000, (float) $inv->balance);
        $this->assertSame(2, $inv->payments()->count());

        \Livewire\Livewire::test(\App\Livewire\Sales\Show::class, ['invoice' => $inv])
            ->set('undoReason', 'again')->call('undoPayment', $pay->id);
        $this->assertSame(2, $inv->payments()->count());

        $this->actingAs(User::factory()->role(User::STAFF)->create());
        \Livewire\Livewire::test(\App\Livewire\Sales\Show::class, ['invoice' => $inv])
            ->set('undoReason', 'x')->call('undoPayment', $pay->id)->assertForbidden();
    }
}
