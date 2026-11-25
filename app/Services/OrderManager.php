<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Part;
use App\Models\ShopCustomer;
use App\Support\Audit;
use App\Support\Cart;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;

/**
 * Online orders.
 *  pending   — placed by the customer; stock is NOT taken yet
 *  confirmed — staff checked it: a spare-parts sale (invoice) is created and stock leaves
 *  ready     — ready for collection / out for delivery
 *  completed — collected / delivered
 *  cancelled — by the customer (while pending) or staff (a confirmed sale is voided, stock returns)
 */
class OrderManager
{
    public function __construct(private SalesManager $sales) {}

    /** @param array $data fulfilment, delivery_address?, contact_phone, payment_method, payment_reference?, notes? */
    public function place(ShopCustomer $customer, array $data): Order
    {
        $lines = Cart::lines();
        if ($lines->isEmpty()) {
            throw new BusinessRuleException('Your cart is empty.');
        }
        if ($bad = $lines->first(fn ($l) => $l->problem)) {
            throw new BusinessRuleException(($bad->part?->name ?? 'A part').': '.strtolower($bad->problem).'. Update your cart and try again.');
        }

        return DB::transaction(function () use ($customer, $data, $lines) {
            // re-check stock under a lock so two customers cannot both take the last item
            $parts = Part::whereIn('id', $lines->pluck('part.id'))->lockForUpdate()->get()->keyBy('id');
            foreach ($lines as $l) {
                if ($l->qty > $parts[$l->part->id]->shopStock()) {
                    throw new BusinessRuleException("{$l->part->name}: only ".$parts[$l->part->id]->shopStock().' left. Update your cart.');
                }
            }
            $totals = Cart::totals($lines);

            $order = Order::create([
                'number'            => Numbering::next('order'),
                'shop_customer_id'  => $customer->id,
                'customer_id'       => $customer->customer_id,
                'status'            => Order::PENDING,
                'fulfilment'        => $data['fulfilment'],
                'delivery_address'  => $data['fulfilment'] === 'delivery' ? trim((string) $data['delivery_address']) : null,
                'contact_phone'     => $data['contact_phone'],
                'payment_method'    => $data['payment_method'],
                'payment_reference' => strtoupper(trim((string) ($data['payment_reference'] ?? ''))) ?: null,
                'notes'             => trim((string) ($data['notes'] ?? '')) ?: null,
                'subtotal'          => $totals['list'],
                'discount'          => $totals['discount'],
                'total'             => $totals['total'],
            ]);
            foreach ($lines as $l) {
                $order->items()->create([
                    'part_id'          => $l->part->id,
                    'name'             => trim($l->part->name.($l->part->brand ? ' ('.$l->part->brand.')' : '')),
                    'qty'              => $l->qty,
                    'list_price'       => $l->list,
                    'discount_percent' => $l->part->discountShown(),
                    'price'            => $l->price,
                    'amount'           => $l->amount,
                ]);
            }
            Cart::clear();
            Audit::log('Online Orders', 'PLACED', $order->number, $customer->name.' — '.money($order->total));

            return $order;
        });
    }

    /** Staff confirm: creates the spare-parts sale (stock leaves) and records any payment already received. */
    public function confirm(Order $order, float $paid = 0, string $mode = 'Cash', ?string $reference = null): void
    {
        if ($order->status !== Order::PENDING) {
            throw new BusinessRuleException('Only orders waiting for confirmation can be confirmed.');
        }

        DB::transaction(function () use ($order, $paid, $mode, $reference) {
            $invoice = $this->sales->createCounterSale(
                $order->items->map(fn ($i) => ['part_id' => $i->part_id, 'qty' => (float) $i->qty, 'rate' => (float) $i->price])->all(),
                [
                    'customer_id' => $order->customer_id,
                    'walk_in_name' => $order->customer_id ? null : $order->shopCustomer?->name,
                    'discount'    => 0,
                    'tax_rate'    => 0,
                    'paid'        => $paid,
                    'mode'        => $mode,
                    'reference'   => $reference ?: $order->payment_reference,
                    'notes'       => 'Online order '.$order->number,
                ],
            );
            $order->forceFill(['status' => Order::CONFIRMED, 'invoice_id' => $invoice->id, 'handled_by' => auth()->id(), 'confirmed_at' => now()])->save();
            Audit::log('Online Orders', 'CONFIRMED', $order->number, 'Sale '.$invoice->number);
        });
    }

    public function markReady(Order $order): void
    {
        if ($order->status !== Order::CONFIRMED) {
            throw new BusinessRuleException('Confirm the order first.');
        }
        $order->forceFill(['status' => Order::READY])->save();
        Audit::log('Online Orders', 'READY', $order->number);
    }

    public function complete(Order $order): void
    {
        if (! in_array($order->status, [Order::CONFIRMED, Order::READY], true)) {
            throw new BusinessRuleException('Only confirmed orders can be completed.');
        }
        $order->forceFill(['status' => Order::COMPLETED, 'completed_at' => now()])->save();
        Audit::log('Online Orders', 'COMPLETED', $order->number);
    }

    public function cancel(Order $order, string $reason, bool $byCustomer = false): void
    {
        if (! $order->isOpen()) {
            throw new BusinessRuleException('This order is already '.strtolower($order->label()).'.');
        }
        if ($byCustomer && $order->status !== Order::PENDING) {
            throw new BusinessRuleException('This order is already being prepared. Call us to change it.');
        }

        DB::transaction(function () use ($order, $reason, $byCustomer) {
            if ($order->invoice && $order->invoice->status !== \App\Models\Invoice::CANCELLED) {
                $this->sales->voidCounterSale($order->invoice, 'Online order '.$order->number.' cancelled: '.$reason);
            }
            $order->forceFill(['status' => Order::CANCELLED, 'cancel_reason' => $reason, 'handled_by' => $byCustomer ? $order->handled_by : auth()->id()])->save();
            Audit::log('Online Orders', 'CANCELLED', $order->number, ($byCustomer ? 'By customer: ' : '').$reason);
        });
    }
}
