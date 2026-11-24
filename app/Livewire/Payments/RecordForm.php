<?php

namespace App\Livewire\Payments;

use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Services\JobCardManager;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reusable "Record payment" dialog.
 *   $dispatch('open-payment-form', { invoiceId: 5 })   payment on an invoice
 *   $dispatch('open-payment-form', { jobCardId: 9 })   advance on a job card (before invoicing)
 * Emits "payment-saved" afterwards.
 */
class RecordForm extends Component
{
    public bool $show = false;
    public ?int $invoiceId = null;
    public ?int $jobCardId = null;
    public string $amount = '';
    public string $mode = 'Cash';
    public string $reference = '';
    public string $note = '';

    #[On('open-payment-form')]
    public function open(?int $invoiceId = null, ?int $jobCardId = null): void
    {
        $this->authorizeUser();
        $this->resetErrorBag();
        $this->invoiceId = $invoiceId;
        $this->jobCardId = $invoiceId ? null : $jobCardId;
        $invoice = $this->invoice();
        $this->amount = $invoice ? (string) (float) $invoice->balance : '';
        $this->mode = 'Cash';
        $this->reference = '';
        $this->note = '';
        $this->show = true;
    }

    public function save(JobCardManager $manager): void
    {
        $this->authorizeUser();
        $this->validate([
            'amount'    => 'required|numeric|min:1',
            'mode'      => 'required|string|max:40',
            'reference' => 'nullable|string|max:80',
            'note'      => 'nullable|string|max:200',
        ]);

        $invoice = $this->invoice();
        $job = $invoice ? null : JobCard::find($this->jobCardId);
        if (! $invoice && ! $job) {
            $this->addError('amount', 'Nothing to pay against.');

            return;
        }
        if ($job && $job->isClosed()) {
            $this->addError('amount', 'This job card is closed.');

            return;
        }

        try {
            $payment = $manager->recordPayment($job, $invoice, (float) $this->amount, $this->mode,
                strtoupper(trim($this->reference)) ?: null, trim($this->note) ?: ($job ? 'Advance' : null));
        } catch (BusinessRuleException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }

        $this->show = false;
        $this->dispatch('payment-saved', id: $payment->id);
        $this->dispatch('toast', message: "Payment {$payment->number} of ".money($payment->amount).' recorded.', type: 'success');
    }

    private function invoice(): ?Invoice
    {
        return $this->invoiceId ? Invoice::find($this->invoiceId) : null;
    }

    private function authorizeUser(): void
    {
        abort_unless(auth()->user()->hasRole('admin', 'manager', 'staff'), 403);
    }

    public function render()
    {
        $invoice = $this->show ? $this->invoice() : null;

        return view('livewire.payments.record-form', [
            'invoice' => $invoice,
            'job'     => $this->show && ! $invoice && $this->jobCardId ? JobCard::with('customer')->find($this->jobCardId) : null,
            'modes'   => list_options('payment_mode'),
        ]);
    }
}
