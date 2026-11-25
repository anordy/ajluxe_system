<?php

namespace App\Support;

use App\Models\Part;
use Illuminate\Support\Collection;

/** The website shopping cart, kept in the visitor's session: [part_id => qty]. */
class Cart
{
    private const KEY = 'shop_cart';
    private const MAX_LINES = 50;

    public static function raw(): array
    {
        return session(self::KEY, []);
    }

    public static function count(): int
    {
        return (int) array_sum(self::raw());
    }

    /** Add $qty (capped at what is in stock). Returns the new quantity, or 0 if the part cannot be sold online. */
    public static function add(int $partId, int $qty = 1): int
    {
        $part = Part::online()->find($partId);
        if (! $part || $part->shopStock() <= 0) {
            return 0;
        }
        $cart = self::raw();
        if (! isset($cart[$partId]) && count($cart) >= self::MAX_LINES) {
            return 0;
        }

        return self::set($partId, ($cart[$partId] ?? 0) + max(1, $qty));
    }

    public static function set(int $partId, int $qty): int
    {
        $cart = self::raw();
        $part = Part::online()->find($partId);
        $qty = $part ? min(max(0, $qty), $part->shopStock()) : 0;
        if ($qty <= 0) {
            unset($cart[$partId]);
        } else {
            $cart[$partId] = $qty;
        }
        session([self::KEY => $cart]);

        return $qty;
    }

    public static function remove(int $partId): void
    {
        $cart = self::raw();
        unset($cart[$partId]);
        session([self::KEY => $cart]);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * Current lines with live prices and stock:
     * [part, qty, available, list, price, amount, problem]
     */
    public static function lines(): Collection
    {
        $cart = self::raw();
        if (! $cart) {
            return collect();
        }
        $parts = Part::whereIn('id', array_keys($cart))->get()->keyBy('id');

        return collect($cart)->map(function ($qty, $id) use ($parts) {
            $p = $parts[$id] ?? null;
            $online = $p && $p->is_active && $p->show_online && (float) $p->selling_price > 0;
            $available = $p ? $p->shopStock() : 0;
            $price = $p ? $p->salePrice() : 0;

            return (object) [
                'part'      => $p,
                'qty'       => (int) $qty,
                'available' => $available,
                'list'      => $p ? (float) $p->selling_price : 0,
                'price'     => $price,
                'amount'    => $price * $qty,
                'problem'   => ! $online ? 'No longer sold online' : ($available <= 0 ? 'Out of stock' : ($qty > $available ? "Only {$available} left" : null)),
            ];
        })->values();
    }

    public static function totals(?Collection $lines = null): array
    {
        $lines ??= self::lines();
        $list = $lines->sum(fn ($l) => $l->list * $l->qty);
        $total = $lines->sum('amount');

        return ['list' => $list, 'discount' => $list - $total, 'total' => $total];
    }
}
