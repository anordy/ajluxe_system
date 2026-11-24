<?php

namespace App\Livewire\Website;

use App\Support\Audit;
use App\Support\Settings;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Headings and short texts on the website, stored in settings. */
#[Title('Website Texts')]
class Texts extends Component
{
    /** key => [label, max length, rows (0 = one line), hint] */
    public const FIELDS = [
        'web_topbar'           => ['Top bar', 120, 0, 'The thin black line at the very top of every page.'],
        'web_hero_title'       => ['Car finder heading', 60, 0, 'Big heading next to the banners on the home page.'],
        'web_hero_text'        => ['Car finder text', 160, 0, 'Line under that heading.'],
        'web_trust'            => ['Reasons to buy here', 600, 4, 'Shown under the banners. One per line: Title | short text (up to 4).'],
        'web_garage_title'     => ['Garage heading', 80, 0, 'Dark garage section on the home page.'],
        'web_garage_text'      => ['Garage text', 240, 2, 'Shown on the home page and at the top of the garage page.'],
        'web_garage_services'  => ['Garage: what we do', 900, 6, 'One per line: Title | short text. Shown on the home page and the garage page (no prices).'],
        'web_pay_methods'      => ['Footer: ways to pay', 300, 4, 'One per line, names only (e.g. M-Pesa, CRDB Bank). Account and Lipa Namba numbers are shown at checkout, not in the footer.'],
        'web_oil_brands'       => ['Lubricant manufacturers', 400, 7, 'One brand per line, in the order shown on the Lubricants page. Write each brand exactly as on your parts.'],
        'web_steps'            => ['How ordering works', 800, 4, 'One step per line: Title | short text.'],
        'web_car_request_note' => ['“Can’t find your car?” reply note', 160, 0, 'Shown in the request form and after sending.'],
        'web_import_title'     => ['Import for you: heading', 60, 0, 'The “Import for you” page.'],
        'web_import_text'      => ['Import for you: text', 400, 3, ''],
        'web_import_steps'     => ['Import for you: steps', 1000, 5, 'One step per line: Title | short text.'],
        'web_import_countries' => ['Import for you: countries', 800, 6, 'One per line: Country | what we bring from there. These are also the choices on the form.'],
        'web_import_note'      => ['Import for you: delivery times', 300, 2, ''],
        'business_tagline'     => ['About us (footer and search engines)', 300, 2, ''],
    ];

    /** Texts that are the same in both languages (brand names). */
    public const NO_SWAHILI = ['web_oil_brands'];

    public array $form = [];

    public static function hasSwahili(string $key): bool
    {
        return ! in_array($key, self::NO_SWAHILI, true);
    }

    public function mount(): void
    {
        foreach (array_keys(self::FIELDS) as $k) {
            $this->form[$k] = (string) setting($k, '');
            if (self::hasSwahili($k)) {
                $this->form[$k.'_sw'] = (string) setting($k.'_sw', '');
            }
        }
    }

    public function save(): void
    {
        $rules = [];
        $names = [];
        foreach (self::FIELDS as $k => [$label, $max]) {
            $rules["form.$k"] = ['nullable', 'string', "max:$max"];
            $names["form.$k"] = $label;
            if (self::hasSwahili($k)) {
                $rules["form.{$k}_sw"] = ['nullable', 'string', 'max:'.(int) ceil($max * 1.4)];   // Swahili runs a little longer
                $names["form.{$k}_sw"] = $label.' (Kiswahili)';
            }
        }
        $rules['form.web_hero_title'][0] = 'required';
        $this->validate($rules, [], $names);

        Settings::set(array_map(fn ($v) => trim((string) $v), $this->form));
        Audit::log('Website', 'TEXTS_UPDATE', 'Website texts');
        $this->dispatch('toast', message: 'Website texts saved.', type: 'success');
    }

    public function restore(string $key): void
    {
        abort_unless(isset(self::FIELDS[preg_replace('/_sw$/', '', $key)]), 404);
        $this->form[$key] = Settings::DEFAULTS[$key] ?? '';
    }

    public function render()
    {
        return view('livewire.website.texts');
    }
}
