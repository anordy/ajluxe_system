<?php

namespace App\Support;

use App\Models\ShopCustomer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Website wishlist: saved in the account when logged in, in the session for guests.
 * A guest's list moves into the account when they log in or sign up.
 */
class Wishlist
{
    private const SESSION = 'wishlist';

    private const MAX = 200;

    /** @return int[] part ids, newest first */
    public static function ids(): array
    {
        $memo = request()->attributes->get('wishlist_ids');
        if (is_array($memo)) {
            return $memo;
        }
        $me = Auth::guard('shop')->user();
        $ids = $me
            ? DB::table('wishlist_items')->where('shop_customer_id', $me->id)->orderByDesc('id')->pluck('part_id')->map(fn ($v) => (int) $v)->all()
            : array_values(array_map('intval', (array) session(self::SESSION, [])));
        request()->attributes->set('wishlist_ids', $ids);

        return $ids;
    }

    public static function has(int $partId): bool
    {
        return in_array($partId, self::ids(), true);
    }

    public static function count(): int
    {
        return count(self::ids());
    }

    /** Add or remove; returns true when the part is now on the list. */
    public static function toggle(int $partId): bool
    {
        $on = ! self::has($partId);
        $me = Auth::guard('shop')->user();
        if ($me) {
            $on
                ? DB::table('wishlist_items')->insertOrIgnore(['shop_customer_id' => $me->id, 'part_id' => $partId, 'created_at' => now(), 'updated_at' => now()])
                : DB::table('wishlist_items')->where('shop_customer_id', $me->id)->where('part_id', $partId)->delete();
        } else {
            $ids = array_values(array_diff(self::ids(), [$partId]));
            if ($on) {
                array_unshift($ids, $partId);
            }
            session([self::SESSION => array_slice($ids, 0, self::MAX)]);
        }
        request()->attributes->remove('wishlist_ids');

        return $on;
    }

    /** After logging in or signing up: keep what the visitor saved as a guest. */
    public static function mergeGuest(ShopCustomer $account): void
    {
        $guest = array_map('intval', (array) session()->pull(self::SESSION, []));
        $valid = DB::table('parts')->whereIn('id', $guest)->pluck('id')->all();
        foreach (array_reverse($guest) as $id) {
            if (in_array($id, $valid)) {
                DB::table('wishlist_items')->insertOrIgnore(['shop_customer_id' => $account->id, 'part_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        request()->attributes->remove('wishlist_ids');
    }
}
