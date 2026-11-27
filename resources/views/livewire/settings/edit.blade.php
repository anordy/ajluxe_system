<form wire:submit="save" class="mx-auto max-w-4xl space-y-6">
    {{-- business profile --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title">Business Profile</h2><span class="text-xs text-ink-400">Printed on invoices and receipts</span></div>
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Business name *</label>
                <input wire:model="s.business_name" class="input">
                @error('s.business_name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Phone</label>
                <input wire:model="s.phone" class="input" placeholder="+255 7xx xxx xxx">
            </div>
            <div class="sm:col-span-2">
                <label class="label">Business description (tagline)</label>
                <input wire:model="s.business_tagline" class="input">
            </div>
            <div>
                <label class="label">WhatsApp number (website)</label>
                <input wire:model="s.whatsapp" class="input" placeholder="0754 000 000">
            </div>
            <div>
                <label class="label">Opening hours (website)</label>
                <input wire:model="s.opening_hours" class="input" placeholder="Mon – Sat, 8:00 – 18:00">
            </div>
            <div class="sm:col-span-2">
                <label class="label">Google Analytics Measurement ID (website visits)</label>
                <input wire:model="s.ga_measurement_id" class="input font-mono" placeholder="G-XXXXXXXXXX">
                @error('s.ga_measurement_id') <p class="error">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-ink-400">From analytics.google.com › Admin › Data streams › your website. Leave empty to turn tracking off. Only the online shop is counted, not this staff portal.</p>
            </div>
            <div class="sm:col-span-2">
                <label class="label">Address</label>
                <input wire:model="s.address" class="input" placeholder="Street, area, city">
            </div>
            <div>
                <label class="label">Email</label>
                <input type="email" wire:model="s.email" class="input">
                @error('s.email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">TIN</label><input wire:model="s.tin" class="input"></div>
                <div><label class="label">VRN</label><input wire:model="s.vrn" class="input"></div>
            </div>
        </div>
    </div>

    {{-- logo --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title">Logo</h2></div>
        <div class="card-body flex flex-col gap-5 sm:flex-row sm:items-center">
            <div class="space-y-2">
                <x-photo-input model="logoData" :current="logo_url()" :max="800" format="image/png" reset="logo-saved" size="h-24 w-56 p-2" />
                <p class="hint">PNG with a white or transparent background works best. Click <b>Save Settings</b> to apply.</p>
                @error('logoData') <p class="error">{{ $message }}</p> @enderror
                @if (setting('logo_path'))
                    <button type="button" class="btn-danger btn-sm" wire:click="removeLogo" wire:confirm="Remove the uploaded logo?">Remove uploaded logo</button>
                @endif
            </div>
        </div>
    </div>

    {{-- invoices --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title">Invoices &amp; Money</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-3">
            <div>
                <label class="label">Currency *</label>
                <input wire:model="s.currency" class="input uppercase">
                <p class="hint">Shown as <b>{{ $s['currency'] ?: 'TZS' }} 364,000</b></p>
            </div>
            <div>
                <label class="label">Default tax / VAT %</label>
                <input type="number" step="0.01" min="0" max="100" wire:model="s.default_tax_rate" class="input">
                @error('s.default_tax_rate') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Print size</label>
                <select wire:model="s.print_size" class="input">
                    <option>A4</option><option>4 inch</option><option>3 inch</option>
                </select>
            </div>
            <div class="sm:col-span-3">
                <label class="label">Payment details (one per line)</label>
                <textarea wire:model="s.payment_details" rows="3" class="input font-mono"></textarea>
                <p class="hint">Printed at the bottom of every proforma and tax invoice.</p>
            </div>
            <div class="sm:col-span-3">
                <label class="label">Invoice footer</label>
                <input wire:model="s.invoice_footer" class="input">
            </div>
        </div>
    </div>

    {{-- numbering --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title">Document Numbers</h2><span class="text-xs text-ink-400">e.g. {{ $s['prefix_invoice'] ?: 'INV' }}-{{ date('Y') }}-0001</span></div>
        <div class="card-body grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ([
                'prefix_job_card' => 'Job card', 'prefix_invoice' => 'Invoice', 'prefix_receipt' => 'Receipt',
                'prefix_po' => 'Purchase order', 'prefix_supplier_payment' => 'Supplier payment',
                'prefix_credit_note' => 'Credit note', 'prefix_debit_note' => 'Debit note',
            ] as $key => $label)
                <div>
                    <label class="label">{{ $label }}</label>
                    <input wire:model="s.{{ $key }}" class="input font-mono uppercase">
                    @error('s.'.$key) <p class="error">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </div>

    <div class="sticky bottom-0 -mx-1 flex items-center justify-end gap-3 rounded-xl border border-ink-100 bg-white/95 px-4 py-3 shadow-lg backdrop-blur">
        <span class="mr-auto text-xs text-ink-400">Changes apply to new documents immediately.</span>
        <button type="submit" class="btn-primary px-6" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Save Settings</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </div>
</form>
