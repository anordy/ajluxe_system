<?php

namespace Database\Seeders;

use App\Models\ListOption;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * First-time setup. Safe to run again: it only adds what is missing and never overwrites your data.
 *   php artisan db:seed --force
 * Seeds: ONE admin login, default settings, dropdown lists and starter services. No demo customers/sales.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->admin();
        $this->settings();
        $this->lists();
        $this->services();
        $this->call(GarageTariffSeeder::class);   // paint price list (safe to re-run)
        $this->call(WebsiteSeeder::class);        // starter banners and car list (safe to re-run)
    }

    private function admin(): void
    {
        if (User::where('role', User::ADMIN)->exists()) {
            return;
        }
        User::create([
            'name'                 => 'Administrator',
            'username'             => 'admin',
            'role'                 => User::ADMIN,
            'is_active'            => true,
            'must_change_password' => true,   // forced to choose a new password at first sign-in
            'password'             => Hash::make('Admin@123'),
        ]);
        $this->command?->warn('  Admin login created: admin / Admin@123  (you will be asked to change it)');
    }

    private function settings(): void
    {
        $existing = DB::table('settings')->pluck('key')->all();
        foreach (Settings::DEFAULTS as $key => $value) {
            if (! in_array($key, $existing, true)) {
                DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Settings::flush();
    }

    private function lists(): void
    {
        $lists = [
            'vehicle_make'     => ['Toyota', 'Nissan', 'Mitsubishi', 'Subaru', 'Mazda', 'Honda', 'Suzuki', 'Isuzu', 'Mercedes-Benz',
                                   'BMW', 'Volkswagen', 'Land Rover', 'Hyundai', 'Kia', 'Ford', 'Lexus', 'Audi', 'Other'],
            'fuel_type'        => ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'Gas (CNG/LPG)'],
            'fuel_level'       => ['Empty', '1/4', '1/2', '3/4', 'Full'],
            'service_category' => ['General Service', 'Engine', 'Brakes', 'Suspension', 'Auto Electrical', 'AC Service',
                                   'Panel Beating', 'Body & Painting', 'Wheels & Tyres', 'Transmission', 'Diagnostics', 'Detailing'],
            // the first five are the website departments
            'part_category'    => ['Car Parts', 'Tyres', 'Lubricants', 'Filters', 'Car Accessories', 'Body Parts', 'Consumables'],
            'part_brand'       => ['Genuine (OEM)', 'Toyota Genuine', 'Denso', 'Bosch', 'NGK', 'KYB', 'Castrol', 'Total', 'Shell', 'Other'],
            'unit'             => ['Pcs', 'Set', 'Pair', 'Litre', 'Kg', 'Box', 'Metre'],
            'payment_mode'     => ['Cash', 'M-Pesa', 'Mixx by Yas', 'Airtel Money', 'HaloPesa', 'Bank Transfer', 'Card', 'Cheque'],
            'customer_type'    => ['Individual', 'Company', 'Fleet', 'Workshop', 'Government'],
            'priority'         => ['Low', 'Normal', 'High', 'Urgent'],
            'specialization'   => ['General Mechanic', 'Auto Electrician', 'Panel Beater', 'Painter', 'AC Technician', 'Diagnostics'],
        ];

        foreach ($lists as $list => $values) {
            if (ListOption::where('list', $list)->exists()) {
                continue; // never touch a list you have already edited
            }
            foreach ($values as $i => $v) {
                ListOption::create(['list' => $list, 'value' => $v, 'sort_order' => $i + 1]);
            }
        }
    }

    private function services(): void
    {
        if (DB::table('services')->exists()) {
            return;
        }
        // Prices start at 0 — set your own in Services. "hourly" services are charged per hour worked.
        $rows = [
            ['General Service (Small Car)', 'General Service', 'fixed', 3],
            ['General Service (SUV)', 'General Service', 'fixed', 4],
            ['Engine Oil & Filter Change', 'General Service', 'fixed', 1],
            ['Brake Pad Replacement', 'Brakes', 'fixed', 1.5],
            ['Brake Disc Skimming', 'Brakes', 'fixed', 2],
            ['Clutch Overhaul', 'Transmission', 'hourly', 6],
            ['AC Gas Top-up & Service', 'AC Service', 'fixed', 2],
            ['Wheel Alignment & Balancing', 'Wheels & Tyres', 'fixed', 1.5],
            ['Auto Electrical Repair', 'Auto Electrical', 'hourly', 1],
            ['Suspension Check & Repair', 'Suspension', 'hourly', 3],
            ['Panel Beating', 'Panel Beating', 'hourly', 4],
            ['Body Painting (Per Panel)', 'Body & Painting', 'fixed', 8],
            ['Engine Diagnostic Scan', 'Diagnostics', 'fixed', 1],
            ['Radiator Coolant Flush', 'Engine', 'fixed', 1],
            ['Full Wash & Interior Detailing', 'Detailing', 'fixed', 2],
        ];
        foreach ($rows as $i => [$name, $cat, $pricing, $hrs]) {
            DB::table('services')->insert([
                'code' => 'SRV-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'name' => $name, 'category' => $cat, 'pricing' => $pricing, 'rate' => 0, 'est_hours' => $hrs,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('sequences')->insertOrIgnore(['key' => 'service', 'year' => 0, 'last_number' => count($rows)]);
    }
}
