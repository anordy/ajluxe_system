<?php

namespace App\Livewire\Orders;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\OrderManager;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Online Orders')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: 'open')]
    public string $tab = 'open';

    #[Url(except: '')]
    public string $search = '';

    public ?int $viewId = null;
    public bool $showView = false;

    // confirm form
    public string $paid = '';
    public string $mode = 'M-Pesa';
    public string $reference = '';
    public string $cancelReason = '';

    public function updating($f): void
    {
        if (in_array($f, ['tab', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function open(int $id): void
    {
        $o = Order::findOrFail($id);
        $this->viewId = $o->id;
        $this->paid = '';
        $this->mode = $o->payment_method === 'bank' ? 'Bank Transfer' : ($o->payment_method === 'lipa_namba' ? 'M-Pesa' : 'Cash');
        $modes = list_options('payment_mode');
        if (! in_array($this->mode, $modes, true)) {
            $this->mode = $modes[0] ?? 'Cash';
        }
        $this->reference = (string) $o->payment_reference;
        $this->cancelReason = '';
        $this->resetErrorBag();
        $this->showView = true;
    }

    private function act(callable $fn, string $ok): void
    {
        try {
            $fn(Order::findOrFail($this->viewId));
        } catch (BusinessRuleException $e) {
            $this->addError('action', $e->getMessage());

            return;
        }
        $this->resetErrorBag();
        $this->dispatch('toast', message: $ok, type: 'success');
    }

    public function confirm(OrderManager $m): void
    {
        $this->validate(['paid' => 'nullable|numeric|min:0', 'mode' => 'required|string|max:40', 'reference' => 'nullable|string|max:80']);
        $this->act(fn ($o) => $m->confirm($o, (float) ($this->paid ?: 0), $this->mode, strtoupper(trim($this->reference)) ?: null),
            'Order confirmed. A spare parts sale was created and stock was updated.');
    }

    public function ready(OrderManager $m): void
    {
        $this->act(fn ($o) => $m->markReady($o), 'Marked as ready. Call the customer.');
    }

    public function complete(OrderManager $m): void
    {
        $this->act(fn ($o) => $m->complete($o), 'Order completed.');
    }

    public function cancel(OrderManager $m): void
    {
        $this->validate(['cancelReason' => 'required|string|min:3|max:200'], [], ['cancelReason' => 'reason']);
        $this->act(fn ($o) => $m->cancel($o, trim($this->cancelReason)), 'Order cancelled.');
    }

    public function render()
    {
        $term = trim($this->search);
        $orders = Order::with('shopCustomer:id,name,phone')->withCount('items')
            ->when($this->tab === 'open', fn ($q) => $q->whereIn('status', [Order::PENDING, Order::CONFIRMED, Order::READY]))
            ->when(isset(Order::LABELS[$this->tab]), fn ($q) => $q->where('status', $this->tab))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', "%{$term}%")->orWhere('contact_phone', 'like', "%{$term}%")
                ->orWhereHas('shopCustomer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 WHEN 'ready' THEN 2 ELSE 3 END")
            ->latest('id')->paginate(25);

        $counts = Order::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('livewire.orders.index', [
            'orders' => $orders,
            'counts' => $counts,
            'view'   => $this->showView && $this->viewId ? Order::with(['items.part', 'shopCustomer', 'invoice', 'handler:id,name'])->find($this->viewId) : null,
            'modes'  => list_options('payment_mode'),
        ]);
    }
}
