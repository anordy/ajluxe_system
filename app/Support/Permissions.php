<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What each role may open and do. The Administrator always has everything (and alone runs
 * Users, Settings, Audit log and Import data, so nobody can be locked out). The other three
 * roles get the ticks saved on Roles & permissions; until saved, the defaults below.
 */
class Permissions
{
    /** key => [label, group, default roles] */
    public const ALL = [
        'dashboard.money'  => ['See sales, profit and stock value on the Dashboard', 'Dashboard', ['manager']],
        'dashboard.garage' => ['See garage sales and profit on the Dashboard (garage only)', 'Dashboard', ['workshop_manager']],

        'bookings'         => ['Garage bookings from the website', 'Garage', ['manager', 'staff', 'workshop_manager']],
        'job_cards.view'   => ['Open job cards', 'Garage', ['manager', 'mechanic', 'workshop_manager']],
        'job_cards.manage' => ['Create job cards, set prices, close and invoice them', 'Garage', ['manager', 'workshop_manager']],
        'customers'        => ['Customers (view, add, edit)', 'Garage', ['manager', 'staff', 'workshop_manager']],
        'vehicles.view'    => ['See vehicles', 'Garage', ['manager', 'staff', 'mechanic', 'workshop_manager']],
        'vehicles.manage'  => ['Add and edit vehicles', 'Garage', ['manager', 'staff', 'workshop_manager']],
        'services'         => ['Services & labour rates', 'Garage', ['manager', 'workshop_manager']],
        'mechanics'        => ['Mechanics list', 'Garage', ['manager', 'workshop_manager']],

        'pos'              => ['POS — sell spare parts', 'Spare parts', ['manager', 'staff']],
        'orders'           => ['Website orders', 'Spare parts', ['manager', 'staff']],
        'parts.view'       => ['See the parts catalogue', 'Spare parts', ['manager', 'staff', 'mechanic', 'workshop_manager']],
        'parts.manage'     => ['Add and edit parts and prices', 'Spare parts', ['manager']],
        'price.edit'       => ['Change prices when selling (POS)', 'Spare parts', ['manager']],
        'price.below_min'  => ['Sell below a part\'s minimum price', 'Spare parts', []],
        'stock.view'       => ['Stock register', 'Spare parts', ['manager', 'staff', 'workshop_manager']],
        'stock.adjust'     => ['Adjust stock counts', 'Spare parts', ['manager']],
        'purchases'        => ['Purchase orders', 'Spare parts', ['manager']],
        'suppliers'        => ['Suppliers', 'Spare parts', ['manager']],
        'returns'          => ['Returns (customer and supplier)', 'Spare parts', ['manager']],

        'sales.view'       => ['Invoices & payments: view and record payments', 'Money', ['manager', 'staff', 'workshop_manager']],
        'sales.manage'     => ['Cancel sales and undo payments', 'Money', ['manager']],
        'reports'          => ['Reports and exports', 'Money', ['manager']],

        'website.requests' => ['Car requests and import orders', 'Website', ['manager', 'staff']],
        'website.manage'   => ['Banners, car list, website texts; quote import orders', 'Website', ['manager']],
        'lists'            => ['Edit drop-down lists', 'Website', ['manager']],
    ];

    /** Roles whose ticks can be changed (the Administrator has everything). */
    public const EDITABLE = [User::MANAGER, User::WORKSHOP, User::STAFF, User::MECHANIC];

    private const CACHE = 'role_permissions.v1';

    private static ?array $loaded = null;

    public static function defaults(): array
    {
        $out = array_fill_keys(self::EDITABLE, []);
        foreach (self::ALL as $key => [, , $roles]) {
            foreach ($roles as $r) {
                $out[$r][] = $key;
            }
        }

        return $out;
    }

    /** role => [permission keys] */
    public static function map(): array
    {
        return self::$loaded ??= Cache::rememberForever(self::CACHE, function () {
            if (! Schema::hasTable('role_permissions') || ! DB::table('role_permissions')->exists()) {
                return self::defaults();
            }
            $rows = DB::table('role_permissions')->get(['role', 'permission']);
            $savedRoles = $rows->where('permission', '_saved')->pluck('role')->all();
            $known = $rows->where('role', '_known')->pluck('permission')->all();
            $defaults = self::defaults();
            $out = array_fill_keys(self::EDITABLE, []);
            foreach ($rows as $r) {
                if (isset(self::ALL[$r->permission]) && isset($out[$r->role])) {
                    $out[$r->role][] = $r->permission;
                }
            }
            foreach (self::EDITABLE as $role) {
                if (! in_array($role, $savedRoles, true)) {
                    $out[$role] = $defaults[$role];                 // a role added after the last save
                    continue;
                }
                foreach ($defaults[$role] as $perm) {                // a permission added after the last save
                    if (! in_array($perm, $known, true) && ! in_array($perm, $out[$role], true)) {
                        $out[$role][] = $perm;
                    }
                }
            }

            return $out;
        });
    }

    public static function roleHas(string $role, string $permission): bool
    {
        if ($role === User::ADMIN) {
            return true;
        }

        return in_array($permission, self::map()[$role] ?? [], true);
    }

    /** Save the ticks for the editable roles. */
    public static function save(array $byRole): void
    {
        DB::transaction(function () use ($byRole) {
            DB::table('role_permissions')->delete();
            $rows = [];
            foreach (self::EDITABLE as $role) {
                foreach (array_unique($byRole[$role] ?? []) as $perm) {
                    if (isset(self::ALL[$perm])) {
                        $rows[] = ['role' => $role, 'permission' => $perm];
                    }
                }
            }
            // markers: which roles were saved (so "nothing ticked" is kept) and which permissions existed then
            foreach (self::EDITABLE as $role) {
                $rows[] = ['role' => $role, 'permission' => '_saved'];
            }
            foreach (array_keys(self::ALL) as $perm) {
                $rows[] = ['role' => '_known', 'permission' => $perm];
            }
            DB::table('role_permissions')->insert($rows);
        });
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
        self::$loaded = null;
    }
}
