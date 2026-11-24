<?php

namespace App\Livewire\PurchaseOrders;

use App\Exceptions\BusinessRuleException;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseManager;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Create or edit a draft purchase order. */
class Form extends Component
{
    public ?PurchaseOrder $po = null;

    #[Url(as: 'supplier')]
    public ?int $supplier_id = null;
    public string $date = '';
    public string $expected_date = '';
    public string $tax_rate = '0';
    public string $other_charges = '0';
    public string $notes = '';

    /** [[part_id, name, sku, unit, qty, rate, stock]] */
    public array $lines = [];
    public string $partSearch = '';

    public function mount(?PurchaseOrder $purchaseOrder = null): void
    {
        $this->date = now()->toDateString();
        if ($purchaseOrder?->exists) {
            abort_unless($purchaseOrder->isDraft(), 403, 'Only draft purchase orders can be edited.');
            $this->po = $purchaseOrder->load('items.part');
            $this->supplier_id = $purchaseOrder->supplier_id;
            $this->date = $purchaseOrder->date->toDateString();
            $this->expected_date = $purchaseOrder->expected_date?->toDateString() ?? '';
            $this->tax_rate = (string) $purchaseOrder->taxRate();
            $this->other_charges = (string) (float) $purchaseOrder->other_charges;
            $this->notes = (string) $purchaseOrder->notes;
            foreach ($purchaseOrder->items as $i) {
                $this->lines[] = $this->lineFor($i->part, (float) $i->qty, (float) $i->rate);
            }
        }
    }

    private function lineFor(Part $p, float $qty, float $rate): array
    {
        return ['part_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit, 'qty' => $qty, 'rate' => $rate, 'stock' => (float) $p->stock_qty];
    }

    public function addPart(int $id): void
    {
        $p = Part::findOrFail($id);
        foreach ($this->lines as $i => $l) {
            if ($l['part_id'] === $p->id) {
                $this->lines[$i]['qty']++;
                $this->partSearch = '';

                return;
            }
        }
        $suggest = max(1, (float) $p->reorder_level * 2 - (float) $p->stock_qty);
        $this->lines[] = $this->lineFor($p, $p->isLow() ? $suggest : 1, (float) $p->purchase_price);
        $this->partSearch = '';
    }

    /** Add every low-stock part (from this supplier's past orders first, else all). */
    public function addLowStock(): void
    {
        $have = collect($this->lines)->pluck('part_id');
        $low = Part::active()->lowStock()->whereNotIn('id', $have)->orderBy('name')->limit(50)->get();
        foreach ($low as $p) {
            $this->lines[] = $this->lineFor($p, max(1, (float) $p->reorder_level * 2 - (float) $p->stock_qty), (float) $p->purchase_price);
        }
        $this->dispatch('toast', message: $low->count() ? "Added {$low->count()} low-stock parts." : 'No low-stock parts to add.', type: $low->count() ? 'success' : 'error');
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    public function save(PurchaseManager $manager, bool $order = false)
    {
        $this->validate([
            'supplier_id'     => 'required|exists:suppliers,id',
            'date'            => 'required|date',
            'expected_date'   => 'nullable|date',
            'tax_rate'        => 'nullable|numeric|min:0|max:100',
            'other_charges'   => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string|max:1000',
            'lines.*.qty'     => 'required|numeric|min:0.01',
            'lines.*.rate'    => 'required|numeric|min:0',
        ], ['supplier_id.required' => 'Choose the supplier.'], ['lines.*.qty' => 'quantity', 'lines.*.rate' => 'price']);

        try {
            $po = $manager->saveDraft($this->po, [
                'supplier_id' => $this->supplier_id, 'date' => $this->date, 'expected_date' => $this->expected_date,
                'tax_rate' => $this->tax_rate, 'other_charges' => $this->other_charges, 'notes' => $this->notes,
            ], $this->lines);
            if ($order) {
                $manager->markOrdered($po);
            }
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        session()->flash('toast', ['message' => "Purchase order {$po->number} ".($order ? 'saved and marked as ordered.' : 'saved as draft.'), 'type' => 'success']);

        return $this->redirect(route('purchase-orders.show', $po), navigate: true);
    }

    public function render()
    {
        $sub = collect($this->lines)->sum(fn ($l) => (float) $l['qty'] * (float) $l['rate']);
        $tax = round($sub * (float) $this->tax_rate / 100, 2);

        return view('livewire.purchase-orders.form', [
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'matches'   => strlen(trim($this->partSearch)) >= 2 ? Part::active()->search($this->partSearch)->orderBy('name')->limit(8)->get() : collect(),
            'subtotal'  => $sub,
            'tax'       => $tax,
            'total'     => $sub + $tax + (float) $this->other_charges,
        ])->title($this->po ? 'Edit '.$this->po->number : 'New Purchase Order');
    }
}
