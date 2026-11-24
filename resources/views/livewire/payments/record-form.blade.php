<div>
    <x-modal show="show" :title="$invoice ? 'Record payment' : 'Record advance'">
        <form wire:submit="save" id="paymentForm" class="space-y-4">
            @if ($invoice)
                <div class="grid grid-cols-3 gap-2 rounded-lg bg-ink-50 p-3 text-center text-sm">
                    <div><p class="text-[11px] font-semibold uppercase text-ink-400">Invoice</p><p class="font-mono font-bold">{{ $invoice->number }}</p></div>
                    <div><p class="text-[11px] font-semibold uppercase text-ink-400">Total</p><p class="font-bold">{{ amount($invoice->grand_total) }}</p></div>
                    <div><p class="text-[11px] font-semibold uppercase text-ink-400">Balance</p><p class="font-bold text-brand-600">{{ amount($invoice->balance) }}</p></div>
                </div>
                <p class="text-sm text-ink-600">{{ $invoice->customerName() }}</p>
            @elseif ($job)
                <p class="rounded-lg bg-ink-50 p-3 text-sm text-ink-600">Advance on <span class="font-mono font-bold">{{ $job->number }}</span> — {{ $job->customer?->name }}. It will be counted on the invoice when it is generated.</p>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Amount ({{ setting('currency') }}) *</label>
                    <input type="number" step="any" min="0" wire:model="amount" class="input text-lg font-bold" autofocus>
                    @error('amount') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Paid by *</label>
                    <select wire:model="mode" class="input">@foreach ($modes as $m) <option>{{ $m }}</option> @endforeach</select>
                </div>
            </div>
            <div><label class="label">Reference (M-Pesa code, bank slip, cheque no)</label><input wire:model="reference" class="input font-mono uppercase"></div>
            <div><label class="label">Note</label><input wire:model="note" class="input"></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="paymentForm" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save payment</span><span wire:loading wire:target="save">Saving…</span>
            </button>
        </x-slot:footer>
    </x-modal>
</div>
