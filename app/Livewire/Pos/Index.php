<?php

namespace App\Livewire\Pos;

use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Services\SalesManager;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('POS — New Sale')]
class Index extends Component
{
    public string $search = '';

    /** part_id => [part_id, name, sku, unit, qty, rate, stock] */
    public array $cart = [];

    public ?int $customer_id = null;
    public string $customerSearch = '';
    public string $walk_in_name = '';
    public string $discount = '0';
    public string $tax_rate = '0';
    public string $paid = '';
    public string $mode = 'Cash';
    public string $reference = '';
    public string $notes = '';

    public ?int $lastSaleId = null;

    public function mount(): void
    {
        $this->tax_rate = (string) (float) setting('default_tax_rate', 0);
    }

    public function canEditPrice(): bool
    {
        return auth()->user()->allows('price.edit');
    }

    // ---------------------------------------------------------- cart

    public function add(int $partId): void
    {
        $p = Part::active()->find($partId);
        if (! $p) {
            return;
        }
        $key = (string) $p->id;
        $inCart = (float) ($this->cart[$key]['qty'] ?? 0);
        if (! $p->sourced && $inCart + 1 > (float) $p->stock_qty + 0.0001) {
            $this->toast("No more {$p->name} in stock.", 'error');

            return;
        }
        if (isset($this->cart[$key])) {
            $this->cart[$key]['qty'] = $inCart + 1;
        } else {
            $this->cart[$key] = [
                'part_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit,
                'qty' => 1, 'rate' => (float) $p->selling_price, 'stock' => (float) $p->stock_qty, 'sourced' => (bool) $p->sourced, 'supplier' => $p->sourced ? $p->sourceSupplier?->name : null, 'min' => (float) $p->min_selling_price,
            ];
        }
        $this->lastSaleId = null;
    }

    /** Enter in the search box: add an exact SKU / OEM match, or the only result. */
    public function addFirst(): void
    {
        $term = trim($this->search);
        if ($term === '') {
            return;
        }
        $exact = Part::active()->where(fn ($q) => $q->where('sku', $term)->orWhere('oem_no', $term))->first();
        $results = $exact ? collect([$exact]) : Part::active()->search($term)->limit(2)->get();
        if ($results->count() === 1) {
            $this->add($results->first()->id);
            $this->search = '';
        }
    }

    public function setQty(string $key, $qty): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }
        $qty = round(max(0, (float) $qty), 2);
        if ($qty <= 0) {
            unset($this->cart[$key]);

            return;
        }
        $part = Part::whereKey($key)->first(['id', 'stock_qty', 'sourced']);
        $stock = (float) $part?->stock_qty;
        if (! $part?->sourced && $qty > $stock + 0.0001) {
            $qty = $stock;
            $this->toast('Only '.rtrim(rtrim(number_format($stock, 2), '0'), '.').' in stock.', 'error');
        }
        $this->cart[$key]['qty'] = $qty;
        $this->cart[$key]['stock'] = $stock;
    }

    public function setRate(string $key, $rate): void
    {
        abort_unless($this->canEditPrice(), 403);
        if (isset($this->cart[$key])) {
            $rate = round(max(0, (float) $rate), 2);
            $min = (float) ($this->cart[$key]['min'] ?? 0);
            if ($min > 0 && $rate < $min && ! auth()->user()->allows('price.below_min')) {
                $rate = $min;
                $this->toast('The lowest price for '.$this->cart[$key]['name'].' is '.money($min).'.', 'error');
            }
            $this->cart[$key]['rate'] = $rate;
        }
    }

    public function remove(string $key): void
    {
        unset($this->cart[$key]);
    }

    public function clear(): void
    {
        $this->reset(['cart', 'customer_id', 'customerSearch', 'walk_in_name', 'discount', 'paid', 'reference', 'notes', 'search']);
        $this->mode = 'Cash';
        $this->tax_rate = (string) (float) setting('default_tax_rate', 0);
    }

    // ---------------------------------------------------------- customer

    public function pickCustomer(?int $id): void
    {
        $this->customer_id = $id;
        $this->customerSearch = '';
    }

    // ---------------------------------------------------------- checkout

    private function totals(): array
    {
        $sub = round(collect($this->cart)->sum(fn ($l) => (float) $l['qty'] * (float) $l['rate']), 2);
        $disc = min(max(0, (float) $this->discount), $sub);
        $tax = round(($sub - $disc) * max(0, (float) $this->tax_rate) / 100, 2);

        return ['subtotal' => $sub, 'discount' => $disc, 'tax' => $tax, 'total' => round($sub - $disc + $tax, 2)];
    }

    public function checkout(SalesManager $sales): void
    {
        abort_unless(auth()->user()->allows('pos'), 403);
        $this->validate([
            'discount'     => 'nullable|numeric|min:0',
            'tax_rate'     => 'nullable|numeric|min:0|max:100',
            'paid'         => 'nullable|numeric|min:0',
            'mode'         => 'required|string|max:40',
            'reference'    => 'nullable|string|max:80',
            'walk_in_name' => 'nullable|string|max:120',
            'notes'        => 'nullable|string|max:500',
        ]);
        if (! $this->canEditPrice()) {
            $this->discount = '0'; // only managers give discounts or change prices
        }
        $t = $this->totals();
        $paid = $this->paid === '' ? $t['total'] : (float) $this->paid;

        try {
            $invoice = $sales->createCounterSale(
                collect($this->cart)->map(fn ($l) => ['part_id' => $l['part_id'], 'qty' => $l['qty'], 'rate' => $l['rate']])->values()->all(),
                [
                    'customer_id'  => $this->customer_id,
                    'walk_in_name' => $this->walk_in_name,
                    'discount'     => $this->discount,
                    'tax_rate'     => $this->tax_rate,
                    'paid'         => $paid,
                    'mode'         => $this->mode,
                    'reference'    => strtoupper(trim($this->reference)),
                    'notes'        => $this->notes,
                ],
            );
        } catch (BusinessRuleException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->clear();
        $this->lastSaleId = $invoice->id;
        $this->toast("Sale {$invoice->number} completed.");
    }

    private function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }

    public function render()
    {
        $term = trim($this->search);
        $results = Part::active()
            ->when($term !== '', fn ($q) => $q->search($term), fn ($q) => $q->where('stock_qty', '>', 0)->latest('updated_at'))
            ->with('sourceSupplier:id,name')
            ->orderByRaw('(stock_qty <= 0 AND sourced = 0)')->orderBy('name')
            ->limit(12)->get();

        return view('livewire.pos.index', [
            'results'   => $results,
            'totals'    => $this->totals(),
            'customer'  => $this->customer_id ? Customer::find($this->customer_id) : null,
            'matches'   => strlen(trim($this->customerSearch)) >= 2 ? Customer::active()->search($this->customerSearch)->orderBy('name')->limit(6)->get() : collect(),
            'modes'     => list_options('payment_mode'),
            'lastSale'  => $this->lastSaleId ? Invoice::find($this->lastSaleId) : null,
            'todayCount'=> Invoice::where('type', 'counter')->active()->whereDate('date', today())->count(),
            'todayTotal'=> (float) Invoice::where('type', 'counter')->active()->whereDate('date', today())->sum('grand_total'),
            'recent'    => Invoice::where('type', 'counter')->with(['customer:id,name,phone', 'items:id,invoice_id,description,qty'])
                ->latest('id')->limit(10)->get(),
        ]);
    }
}
