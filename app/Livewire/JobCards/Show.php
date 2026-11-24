<?php

namespace App\Livewire\JobCards;

use App\Exceptions\BusinessRuleException;
use App\Models\JobCard;
use App\Models\JobCardItem;
use App\Models\Mechanic;
use App\Models\Part;
use App\Models\Service;
use App\Services\JobCardManager;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    public JobCard $job;

    // ---- add lines (many at once) / edit one line
    public bool $showItem = false;
    public string $itemType = 'service';
    public string $itemSearch = '';
    /** lines waiting to be saved: [type, service_id, part_id, description, qty, rate, cost (per unit for parts), unit, stock] */
    public array $pending = [];
    /** the free-text "Other" form */
    public array $other = ['description' => '', 'qty' => '1', 'rate' => '', 'cost' => ''];

    public bool $showEdit = false;
    public ?int $editingItemId = null;
    public array $line = [];

    // ---- discount & tax
    public string $discount = '0';
    public string $taxRate = '0';

    // ---- status
    public bool $showCancel = false;
    public string $cancelReason = '';

    // ---- details
    public bool $showDetails = false;
    public array $details = [];
    public string $workNotes = '';

    public function mount(JobCard $jobCard): void
    {
        $this->job = $jobCard;
        $this->syncFromJob();
    }

    // ------------------------------------------------------------ permissions

    public function canPrice(): bool
    {
        return auth()->user()->hasRole('admin', 'manager');
    }

    private function guardPrice(): void
    {
        abort_unless($this->canPrice(), 403);
    }

    // ------------------------------------------------------------ items

    /** Open the add dialog on a tab. Lines already picked stay in the list (even if the dialog is closed) until saved or cleared. */
    public function openAdd(string $type = 'service'): void
    {
        $this->guardPrice();
        $this->resetErrorBag();
        $this->itemType = in_array($type, ['service', 'part', 'other'], true) ? $type : 'service';
        $this->itemSearch = '';
        $this->showItem = true;
    }

    /** Switching tabs keeps everything picked so far. */
    public function updatedItemType(): void
    {
        $this->itemSearch = '';
        $this->resetErrorBag();
    }

    public function pickService(int $id): void
    {
        $this->guardPrice();
        $s = Service::findOrFail($id);
        foreach ($this->pending as $i => $p) {
            if ($p['type'] === 'service' && $p['service_id'] === $s->id) {
                $this->pending[$i]['qty'] = (string) ((float) $p['qty'] + 1);
                $this->itemSearch = '';

                return;
            }
        }
        $this->pending[] = [
            'type' => 'service', 'service_id' => $s->id, 'part_id' => null, 'description' => $s->name,
            'qty' => $s->isHourly() ? (string) max(1, (float) $s->est_hours) : '1',
            'rate' => (string) (float) $s->rate, 'cost' => '0', 'unit' => $s->isHourly() ? 'hrs' : '', 'stock' => null,
        ];
        $this->itemSearch = '';
    }

    public function pickPart(int $id): void
    {
        $this->guardPrice();
        $p = Part::findOrFail($id);
        foreach ($this->pending as $i => $l) {
            if ($l['type'] === 'part' && $l['part_id'] === $p->id) {
                $this->pending[$i]['qty'] = (string) ((float) $l['qty'] + 1);
                $this->itemSearch = '';

                return;
            }
        }
        $this->pending[] = [
            'type' => 'part', 'service_id' => null, 'part_id' => $p->id,
            'description' => $p->name.($p->brand ? ' ('.$p->brand.')' : ''),
            'qty' => '1', 'rate' => (string) (float) $p->selling_price, 'cost' => (string) (float) $p->selling_price,
            'unit' => $p->unit, 'stock' => (float) $p->stock_qty, 'min' => (float) $p->min_selling_price,
        ];
        $this->itemSearch = '';
    }

    public function addOther(): void
    {
        $this->guardPrice();
        $this->validate([
            'other.description' => 'required|string|max:255',
            'other.qty'         => 'required|numeric|min:0.01',
            'other.rate'        => 'required|numeric|min:0',
            'other.cost'        => 'nullable|numeric|min:0',
        ], [], ['other.description' => 'description', 'other.qty' => 'quantity', 'other.rate' => 'price', 'other.cost' => 'cost']);
        $this->pending[] = [
            'type' => 'other', 'service_id' => null, 'part_id' => null, 'description' => trim($this->other['description']),
            'qty' => (string) $this->other['qty'], 'rate' => (string) $this->other['rate'], 'cost' => (string) ($this->other['cost'] ?: 0),
            'unit' => '', 'stock' => null,
        ];
        $this->other = ['description' => '', 'qty' => '1', 'rate' => '', 'cost' => ''];
    }

    public function clearPending(): void
    {
        $this->pending = [];
        $this->resetErrorBag();
    }

    public function removePending(int $i): void
    {
        unset($this->pending[$i]);
        $this->pending = array_values($this->pending);
    }

    /** Save every pending line in one go — all or nothing (e.g. if one part is short of stock). */
    public function savePending(JobCardManager $manager): void
    {
        $this->guardPrice();
        if (! $this->pending) {
            $this->addError('pending', 'Pick at least one service, part or other item.');

            return;
        }
        $this->validate([
            'pending.*.description' => 'required|string|max:255',
            'pending.*.qty'         => 'required|numeric|min:0.01|max:100000',
            'pending.*.rate'        => 'required|numeric|min:0',
            'pending.*.cost'        => 'nullable|numeric|min:0',
        ], [], ['pending.*.description' => 'description', 'pending.*.qty' => 'quantity', 'pending.*.rate' => 'price', 'pending.*.cost' => 'cost']);

        try {
            DB::transaction(function () use ($manager) {
                foreach ($this->pending as $l) {
                    $qty = (float) $l['qty'];
                    $cost = (float) ($l['cost'] ?: 0);
                    $manager->addItem($this->job, [
                        'type'        => $l['type'],
                        'service_id'  => $l['service_id'],
                        'part_id'     => $l['part_id'],
                        'description' => $l['description'],
                        'qty'         => $qty,
                        'rate'        => $l['rate'],
                        'cost'        => $l['type'] === 'part' ? $cost * $qty : $cost,
                    ]);
                }
            });
        } catch (BusinessRuleException $e) {
            $this->addError('pending', $e->getMessage().' Nothing was saved — fix the line and try again.');

            return;
        }

        $n = count($this->pending);
        $this->pending = [];
        $this->showItem = false;
        $this->refreshJob();
        $this->toast($n === 1 ? 'Line added to the job card.' : "{$n} lines added to the job card.");
    }

    public function editItem(int $id): void
    {
        $this->guardPrice();
        $this->resetErrorBag();
        $item = $this->job->items()->findOrFail($id);
        $this->editingItemId = $item->id;
        $qty = (float) $item->qty;
        $this->line = [
            'type'        => $item->type,
            'description' => $item->description,
            'qty'         => (string) $qty,
            'rate'        => (string) (float) $item->rate,
            // the form edits cost per unit for parts, total cost otherwise
            'cost'        => (string) ($item->type === 'part' && $qty > 0 ? round((float) $item->cost / $qty, 2) : (float) $item->cost),
        ];
        $this->showEdit = true;
    }

    public function saveItem(JobCardManager $manager): void
    {
        $this->guardPrice();
        $this->validate([
            'line.description' => 'required|string|max:255',
            'line.qty'         => 'required|numeric|min:0.01|max:100000',
            'line.rate'        => 'required|numeric|min:0',
            'line.cost'        => 'nullable|numeric|min:0',
        ], [], ['line.description' => 'description', 'line.qty' => 'quantity', 'line.rate' => 'price', 'line.cost' => 'cost']);

        $qty = (float) $this->line['qty'];
        $costInput = (float) ($this->line['cost'] ?: 0);
        try {
            $manager->updateItem($this->job->items()->findOrFail($this->editingItemId), [
                'description' => $this->line['description'],
                'qty'         => $qty,
                'rate'        => $this->line['rate'],
                'cost'        => $this->line['type'] === 'part' ? $costInput * $qty : $costInput,
            ]);
        } catch (BusinessRuleException $e) {
            $this->addError('line.qty', $e->getMessage());

            return;
        }
        $this->showEdit = false;
        $this->refreshJob();
        $this->toast('Line updated.');
    }

    public function removeItem(int $id, JobCardManager $manager): void
    {
        $this->guardPrice();
        $this->attempt(fn () => $manager->removeItem($this->job->items()->findOrFail($id)), 'Line removed.');
    }

    public function saveTotals(JobCardManager $manager): void
    {
        $this->guardPrice();
        $this->validate(['discount' => 'required|numeric|min:0|max:100', 'taxRate' => 'required|numeric|min:0|max:100'], [], ['discount' => 'discount %', 'taxRate' => 'tax rate']);
        $this->attempt(fn () => $manager->setDiscountAndTax($this->job, (float) $this->discount, (float) $this->taxRate), 'Totals updated.');
    }

    // ------------------------------------------------------------ status / invoice

    public function setStatus(string $status, JobCardManager $manager): void
    {
        $user = auth()->user();
        // mechanics move the job through the workshop; delivery & cancelling stay with the office
        if (! $this->canPrice()) {
            abort_unless($user->hasRole('mechanic') && ! in_array($status, [JobCard::DELIVERED, JobCard::CANCELLED], true), 403);
        }
        $this->attempt(fn () => $manager->changeStatus($this->job, $status), "Status changed to {$status}.");
    }

    public function confirmCancel(JobCardManager $manager): void
    {
        $this->guardPrice();
        $this->validate(['cancelReason' => 'required|string|min:3|max:200'], [], ['cancelReason' => 'reason']);
        if ($this->attempt(fn () => $manager->changeStatus($this->job, JobCard::CANCELLED, 'Cancelled: '.trim($this->cancelReason)), 'Job card cancelled. Any parts went back to stock.')) {
            $this->showCancel = false;
            $this->cancelReason = '';
        }
    }

    public function generateInvoice(JobCardManager $manager): void
    {
        $this->guardPrice();
        $this->attempt(function () use ($manager) {
            $inv = $manager->generateInvoice($this->job);
            $this->toast("Invoice {$inv->number} created.");
        });
    }

    #[On('payment-saved')]
    public function paymentSaved(): void
    {
        $this->refreshJob();
    }

    // ------------------------------------------------------------ details & notes

    public function openDetails(): void
    {
        $this->guardPrice();
        $this->resetErrorBag();
        $this->details = [
            'mechanic_id'      => $this->job->mechanic_id,
            'priority'         => $this->job->priority,
            'promised_at'      => $this->job->promised_at?->format('Y-m-d\TH:i') ?? '',
            'odometer_in'      => (string) ($this->job->odometer_in ?? ''),
            'fuel_level'       => $this->job->fuel_level ?? '',
            'complaints'       => $this->job->complaints ?? '',
            'inspection_notes' => $this->job->inspection_notes ?? '',
        ];
        $this->showDetails = true;
    }

    public function saveDetails(): void
    {
        $this->guardPrice();
        $data = $this->validate([
            'details.mechanic_id'      => 'nullable|exists:mechanics,id',
            'details.priority'         => 'required|string|max:20',
            'details.promised_at'      => 'nullable|date',
            'details.odometer_in'      => 'nullable|integer|min:0',
            'details.fuel_level'       => 'nullable|string|max:10',
            'details.complaints'       => 'required|string|max:3000',
            'details.inspection_notes' => 'nullable|string|max:3000',
        ], [], ['details.complaints' => 'complaint'])['details'];

        $this->job->update([
            'mechanic_id'      => $data['mechanic_id'] ?: null,
            'priority'         => $data['priority'],
            'promised_at'      => $data['promised_at'] ?: null,
            'odometer_in'      => $data['odometer_in'] !== '' && $data['odometer_in'] !== null ? (int) $data['odometer_in'] : null,
            'fuel_level'       => $data['fuel_level'] ?: null,
            'complaints'       => trim($data['complaints']),
            'inspection_notes' => trim((string) $data['inspection_notes']) ?: null,
        ]);
        Audit::log('Job Cards', 'UPDATE', $this->job->number, 'Details edited');
        $this->showDetails = false;
        $this->refreshJob();
        $this->toast('Job details saved.');
    }

    public function saveWorkNotes(): void
    {
        abort_unless(auth()->user()->hasRole('admin', 'manager', 'mechanic'), 403);
        $this->validate(['workNotes' => 'nullable|string|max:5000']);
        if ($this->job->status === JobCard::CANCELLED) {
            return;
        }
        $this->job->update(['work_notes' => trim($this->workNotes) ?: null]);
        $this->toast('Work notes saved.');
    }

    // ------------------------------------------------------------ helpers

    /** Run an action; show business-rule errors as a toast. Returns true on success. */
    private function attempt(callable $fn, ?string $success = null): bool
    {
        try {
            $fn();
        } catch (BusinessRuleException $e) {
            $this->toast($e->getMessage(), 'error');
            $this->refreshJob();

            return false;
        }
        $this->refreshJob();
        if ($success) {
            $this->toast($success);
        }

        return true;
    }

    private function refreshJob(): void
    {
        $this->job->refresh();
        $this->job->unsetRelation('invoice');
        $this->syncFromJob();
    }

    private function syncFromJob(): void
    {
        $this->discount = (string) (float) $this->job->discount_rate;
        $this->taxRate = (string) (float) $this->job->tax_rate;
        $this->workNotes = (string) $this->job->work_notes;
    }

    private function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }

    public function render()
    {
        $job = $this->job->load([
            'customer', 'vehicle', 'mechanic', 'creator:id,name',
            'items.part:id,name,unit,stock_qty', 'items.service:id,pricing',
            'history.user:id,name', 'payments.creator:id,name', 'invoice',
        ]);

        $term = trim($this->itemSearch);
        $results = collect();
        if ($this->showItem && $term !== '') {
            $results = match ($this->itemType) {
                'service' => Service::active()->search($term)->orderBy('name')->limit(8)->get(),
                'part'    => Part::active()->search($term)->orderBy('name')->limit(8)->get(),
                default   => collect(),
            };
        }

        $paid = (float) $job->payments->sum('amount');

        return view('livewire.job-cards.show', [
            'results'    => $results,
            'paid'       => $paid,
            'invoice'    => $job->invoice,
            'editable'   => $job->isEditable(),
            'canPrice'   => $this->canPrice(),
            'isMechanic' => auth()->user()->hasRole('mechanic'),
            'mechanics'  => Mechanic::active()->orderBy('name')->get(['id', 'name']),
            'priorities' => list_options('priority'),
            'fuelLevels' => list_options('fuel_level'),
            'pendingTotal' => collect($this->pending)->sum(fn ($l) => (float) $l['qty'] * (float) $l['rate']),
        ])->title($job->number.' · Job Card');
    }
}
