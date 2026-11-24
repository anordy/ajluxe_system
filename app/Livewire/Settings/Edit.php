<?php

namespace App\Livewire\Settings;

use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Settings')]
class Edit extends Component
{
    /** All editable settings, keyed like Settings::DEFAULTS */
    public array $s = [];

    /** new logo as a data URL (prepared in the browser, sent with Save) */
    public ?string $logoData = null;

    private const FIELDS = [
        'business_name', 'business_tagline', 'address', 'phone', 'whatsapp', 'opening_hours', 'email', 'tin', 'vrn',
        'currency', 'default_tax_rate', 'payment_details', 'invoice_footer', 'print_size',
        'prefix_job_card', 'prefix_invoice', 'prefix_receipt', 'prefix_po',
        'prefix_supplier_payment', 'prefix_credit_note', 'prefix_debit_note',
    ];

    public function mount(): void
    {
        foreach (self::FIELDS as $f) {
            $this->s[$f] = (string) Settings::get($f, '');
        }
    }

    public function save(): void
    {
        $prefixRules = collect(self::FIELDS)->filter(fn ($f) => str_starts_with($f, 'prefix_'))
            ->mapWithKeys(fn ($f) => ['s.'.$f => 'required|alpha_dash|max:8'])->all();

        $this->validate($prefixRules + [
            's.business_name'    => 'required|string|max:120',
            's.business_tagline' => 'nullable|string|max:255',
            's.address'          => 'nullable|string|max:255',
            's.phone'            => 'nullable|string|max:80',
            's.whatsapp'         => 'nullable|string|max:20',
            's.opening_hours'    => 'nullable|string|max:120',
            's.email'            => 'nullable|email|max:120',
            's.tin'              => 'nullable|string|max:40',
            's.vrn'              => 'nullable|string|max:40',
            's.currency'         => 'required|string|max:8',
            's.default_tax_rate' => 'required|numeric|min:0|max:100',
            's.payment_details'  => 'nullable|string|max:1000',
            's.invoice_footer'   => 'nullable|string|max:500',
            's.print_size'       => 'required|in:A4,4 inch,3 inch',
        ], [], ['s.business_name' => 'business name']);

        $values = $this->s;
        $values['currency'] = strtoupper(trim($values['currency']));

        if ($this->logoData) {
            try {
                $path = \App\Support\ImageData::store($this->logoData, 'branding');
            } catch (\InvalidArgumentException $e) {
                $this->addError('logoData', $e->getMessage());

                return;
            }
            $old = Settings::get('logo_path');
            $values['logo_path'] = $path;
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            $this->logoData = null;
            $this->dispatch('logo-saved');
        }

        Settings::set($values);
        Audit::log('Settings', 'UPDATE', null, 'Business settings saved');
        $this->dispatch('toast', message: 'Settings saved.', type: 'success');
    }

    public function removeLogo(): void
    {
        if ($path = Settings::get('logo_path')) {
            Storage::disk('public')->delete($path);
        }
        Settings::set(['logo_path' => '']);
        $this->dispatch('toast', message: 'Logo removed — the default AJ LUXE AUTO logo will be used.', type: 'success');
    }

    public function render()
    {
        return view('livewire.settings.edit');
    }
}
