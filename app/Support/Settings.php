<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Business settings stored as key/value rows, cached for speed.
 * Usage: Settings::get('business_name')  or the helper  setting('business_name')
 */
class Settings
{
    private const CACHE_KEY = 'app.settings';

    /** Default values used when a key has never been saved. */
    public const DEFAULTS = [
        'business_name'    => 'AJ LUXE AUTO',
        'business_tagline' => 'Dealers in Motor Vehicle Repair, Services, Auto Electrical, Panel Beating, Body & Painting, Spare Parts and Accessories',
        'address'          => '',
        'phone'            => '',
        'email'            => '',
        'tin'              => '',
        'vrn'              => '',
        'currency'         => 'TZS',
        'default_tax_rate' => '0',
        'payment_details'  => "CRDB BANK : 10259295578 - AJ LUXE AUTO\nLIPA NAMBA M-PESA : 351540391 - AJ LUXE AUTO",
        'invoice_footer'   => 'Thank you for choosing AJ LUXE AUTO!',
        'logo_path'        => '',
        'print_size'       => 'A4',
        'prefix_job_card'  => 'JC',
        'prefix_invoice'   => 'INV',
        'prefix_receipt'   => 'RCT',
        'prefix_po'        => 'PO',
        'prefix_supplier_payment' => 'SPY',
        'prefix_credit_note' => 'CRN',
        'prefix_debit_note'  => 'DBN',
        'prefix_order'       => 'ORD',
        'whatsapp'           => '',
        'opening_hours'      => 'Mon – Sat, 8:00 – 18:00',
        'low_stock_alert'  => '1',
        'ga_measurement_id' => '',   // Google Analytics 4 (G-XXXXXXX); empty = no tracking on the website

        // ---- website texts (Admin › Website › Website texts). Lists: one item per line, "Title | text".
        'web_topbar'        => 'Order online, collect the same day at our Mbezi Beach shop',
        'web_hero_title'    => 'Find parts for your car',
        'web_hero_text'     => 'Choose your car and we show only the parts that fit it.',
        'web_trust'         => "Genuine brands | Bosch, Denso, NGK, Toyota, Michelin and more\nFitted at our garage | Buy the part and we fit it the same day\nPay your way | M-Pesa Lipa Namba, bank transfer or cash\nCollect today | We put your order aside and call when it is ready",
        'web_garage_title'  => 'Buy the part here. We fit it, fix it, paint it.',
        'web_garage_text'   => 'Servicing, repairs, auto electrical, panel beating and painting in our workshop and spray booth.',
        'web_garage_services' => "Servicing & repairs | Oil service, brakes, suspension, engine and gearbox work\nAuto electrical | Diagnosis, wiring, starters, alternators, AC and lights\nPanel beating | Dents, accident repairs and bumper fixing\nPainting | Single panels or the full car, in our workshop or spray booth\nTyres & wheels | Fitting, balancing, alignment and rim painting\nPolishing | Half or full body polish to bring the shine back",
        'web_pay_methods'   => "M-Pesa\nCRDB Bank\nCash when you collect",
        'web_oil_brands'    => "Atlantic Oil\nLiqui Moly\nTotal\nCastrol\nMotul\nPuma\nToyota Genuine",
        'web_steps'         => "Add parts to your cart | Prices and stock on this site come straight from our shelves.\nPlace your order | Create an account, choose collection or delivery, and how you will pay.\nWe confirm it | We check every part, put it aside for you and call when it is ready.\nCollect or receive it | Pay by M-Pesa Lipa Namba, bank transfer, or when you collect.",
        'web_car_request_note' => 'We usually reply within one working day by phone or WhatsApp.',
        'web_import_title'     => 'Import for you',
        'web_import_text'      => 'Engines, gearboxes and parts we do not stock, brought in from Japan, China, Dubai and more. You get one price delivered to Dar es Salaam, with shipping and duty included, before you pay anything.',
        'web_import_steps'     => "Tell us what you need | The part, your car and, if you have it, the chassis number or engine code.\nGet a full price | We send one price delivered to Dar, including shipping, duty and clearing.\nPay a deposit | Usually half. We order only after you agree to the price.\nWe ship and clear it | We track it from the supplier to our shop and keep you updated.\nCollect or we fit it | Pick it up or let our garage fit and test it.",
        'web_import_countries' => "Japan | Used engines and gearboxes, genuine Toyota, Nissan and Subaru parts\nChina | New aftermarket parts, body parts, lights and accessories\nUAE (Dubai) | Fast shipping for genuine and OEM parts\nThailand | Hilux, Fortuner and pick-up parts\nUK | Land Rover, BMW and Mercedes parts\nSouth Africa | Parts for local models, by road",
        'web_import_note'      => 'Japan: about 4–6 weeks by sea, 7–10 days by air. China and Dubai: about 3–5 weeks. Prices depend on the exchange rate on the day we order.',

        // ---- the same texts in Swahili (used when a visitor chooses Kiswahili; empty = English text)
        'web_topbar_sw'        => 'Agiza mtandaoni, chukua siku hiyo hiyo dukani kwetu Mbezi Beach',
        'web_hero_title_sw'    => 'Tafuta vipuri vya gari lako',
        'web_hero_text_sw'     => 'Chagua gari lako, tutakuonyesha vipuri vinavyolifaa tu.',
        'web_trust_sw'         => "Bidhaa halisi | Bosch, Denso, NGK, Toyota, Michelin na zaidi\nTunafunga gereji kwetu | Nunua kipuri, tunakufungia siku hiyo hiyo\nLipa upendavyo | M-Pesa, benki au taslimu\nChukua leo | Tunakuwekea oda yako na kukupigia ikiwa tayari",
        'web_garage_title_sw'  => 'Nunua kipuri hapa. Tunakifunga, tunatengeneza, tunapaka rangi.',
        'web_garage_text_sw'   => 'Huduma ya gari, matengenezo, umeme wa magari, ukarabati wa bodi na upakaji rangi katika karakana yetu na chumba cha kupaka rangi.',
        'web_garage_services_sw' => "Huduma na matengenezo | Kubadilisha oili, breki, suspension, injini na gia\nUmeme wa magari | Uchunguzi, nyaya, stata, alternator, AC na taa\nUkarabati wa bodi | Kunyoosha mabonyeo, matengenezo ya ajali na bampa\nKupaka rangi | Sehemu moja au gari zima, karakana au chumba cha rangi\nMatairi na magurudumu | Kufunga, balancing, alignment na kupaka rangi rimu\nKung'arisha | Polishi ya nusu au bodi nzima kurudisha mng'ao",
        'web_pay_methods_sw'   => "M-Pesa\nCRDB Bank\nTaslimu unapochukua",
        'web_steps_sw'         => "Weka vipuri kwenye kikapu | Bei na idadi iliyopo kwenye tovuti hii zinatoka moja kwa moja kwenye rafu zetu.\nWeka oda yako | Fungua akaunti, chagua kuchukua au kuletewa, na jinsi utakavyolipa.\nTunathibitisha | Tunakagua kila kipuri, tunakuwekea na kukupigia ikiwa tayari.\nChukua au pokea | Lipa kwa M-Pesa, benki, au unapochukua.",
        'web_car_request_note_sw' => 'Kwa kawaida tunajibu ndani ya siku moja ya kazi kwa simu au WhatsApp.',
        'web_import_title_sw'     => 'Tunakuagizia',
        'web_import_text_sw'      => 'Injini, gia na vipuri tusivyokuwa navyo, tunaviagiza kutoka Japani, China, Dubai na kwingineko. Unapata bei moja hadi Dar es Salaam, ikijumuisha usafirishaji na ushuru, kabla hujalipa chochote.',
        'web_import_steps_sw'     => "Tuambie unachohitaji | Kipuri, gari lako na, ukiwa nayo, namba ya chasisi au code ya injini.\nPata bei kamili | Tunakutumia bei moja hadi Dar, ikijumuisha usafirishaji, ushuru na kutoa bandarini.\nLipa malipo ya awali | Kwa kawaida nusu. Tunaagiza baada tu ya kukubaliana bei.\nTunasafirisha na kutoa mzigo | Tunaufuatilia kutoka kwa msambazaji hadi dukani kwetu na kukujulisha.\nChukua au tukufungie | Kichukue au gereji yetu ikifunge na kukijaribu.",
        'web_import_countries_sw' => "Japani | Injini na gia zilizotumika, vipuri halisi vya Toyota, Nissan na Subaru\nChina | Vipuri vipya, vipuri vya bodi, taa na vifaa\nUAE (Dubai) | Usafirishaji wa haraka wa vipuri halisi na OEM\nThailand | Vipuri vya Hilux, Fortuner na pick-up\nUingereza | Vipuri vya Land Rover, BMW na Mercedes\nAfrika Kusini | Vipuri vya magari ya hapa, kwa barabara",
        'web_import_note_sw'      => 'Japani: takriban wiki 4–6 kwa meli, siku 7–10 kwa ndege. China na Dubai: takriban wiki 3–5. Bei hutegemea kiwango cha kubadilisha fedha siku tunapoagiza.',
        'business_tagline_sw'     => 'Wauzaji wa vipuri na vifaa vya magari; matengenezo na huduma za magari, umeme wa magari, ukarabati wa bodi na upakaji rangi',
    ];

    /** "Title | text" lines of a website setting → [[title, text], ...] */
    public static function pairs(string $key): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) (str_starts_with($key, 'web_') ? webtext($key, '') : self::get($key, ''))) as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$a, $b] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $out[] = [$a, $b];
        }

        return $out;
    }

    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $saved = Schema::hasTable('settings') ? Setting::pluck('value', 'key')->all() : [];

            return array_merge(self::DEFAULTS, $saved);
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    /** Save several settings at once. */
    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
