<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Owner analytics on a "money collected" basis.
 *
 * Profit is recorded only when the customer pays: each payment carries the same share
 * of profit as its invoice (a 50% part-payment books 50% of that invoice's profit, a
 * refund takes it back). Sales are split by channel:
 *   garage = garage job invoices, online = website orders, shop = counter sales (POS).
 * Parts on garage jobs are costed at the buying price, so the business total counts
 * the shop's margin and the garage's margin once.
 */
class Analytics
{
    public const CHANNELS = ['garage' => 'Garage', 'shop' => 'Shop (POS)', 'online' => 'Online'];

    /** Colour follows the channel everywhere (validated categorical slots 1–3). */
    public const COLORS = ['garage' => '#2a78d6', 'shop' => '#eb6834', 'online' => '#1baf7a'];

    /** Invoice facts: channel, total, revenue/cost split into parts and labour. */
    private array $invoices = [];

    private function invoiceFacts(array $ids): void
    {
        $ids = array_values(array_diff(array_unique(array_filter($ids)), array_keys($this->invoices)));
        if (! $ids) {
            return;
        }
        foreach (array_chunk($ids, 500) as $chunk) {
            $online = DB::table('orders')->whereIn('invoice_id', $chunk)->pluck('invoice_id')->flip();
            $lines = DB::table('invoice_items')->whereIn('invoice_id', $chunk)
                ->selectRaw('invoice_id, CASE WHEN part_id IS NOT NULL THEN 1 ELSE 0 END as is_part, sum(amount) as amount, sum(COALESCE(stock_cost, cost)) as cost, sum(cost) as shop_cost, '
                    .'sum(CASE WHEN qty > 0 THEN amount * sourced_qty / qty ELSE 0 END) as src_amount, '
                    .'sum(CASE WHEN qty > 0 THEN COALESCE(stock_cost, cost) * sourced_qty / qty ELSE 0 END) as src_cost')
                ->groupBy('invoice_id', 'is_part')->get()->groupBy('invoice_id');
            // bought-when-sold quantities, per supplier
            $bySup = DB::table('invoice_items')->whereIn('invoice_id', $chunk)->where('sourced_qty', '>', 0)->where('qty', '>', 0)
                ->selectRaw('invoice_id, source_supplier_id as sup, sum(amount * sourced_qty / qty) as rev, sum(COALESCE(stock_cost, cost) * sourced_qty / qty) as cost')
                ->groupBy('invoice_id', 'source_supplier_id')->get()->groupBy('invoice_id');

            foreach (DB::table('invoices')->whereIn('id', $chunk)->get(['id', 'type', 'subtotal', 'discount', 'grand_total', 'status']) as $inv) {
                $l = $lines[$inv->id] ?? collect();
                $parts = $l->firstWhere('is_part', 1);
                $other = $l->firstWhere('is_part', 0);
                $lineTotal = (float) ($parts->amount ?? 0) + (float) ($other->amount ?? 0);
                // discount on the invoice is shared over its lines
                $keep = $lineTotal > 0 ? ((float) $inv->subtotal - (float) $inv->discount) / $lineTotal : 1;
                $partsRev = (float) ($parts->amount ?? 0) * $keep;
                $otherRev = (float) ($other->amount ?? 0) * $keep;
                $partsCost = (float) ($parts->cost ?? 0);
                $otherCost = (float) ($other->cost ?? 0);
                // garage invoices: parts are bought from the shop at its selling price
                $partsShop = $inv->type === 'garage' ? (float) ($parts->shop_cost ?? 0) : 0;
                // parts bought from a supplier for this sale
                $srcRev = (float) ($parts->src_amount ?? 0) * $keep;

                $this->invoices[$inv->id] = [
                    'channel'   => $inv->type === 'garage' ? 'garage' : (isset($online[$inv->id]) ? 'online' : 'shop'),
                    'total'     => (float) $inv->grand_total,
                    'cancelled' => $inv->status === Invoice::CANCELLED,
                    'partsRev'  => $partsRev, 'partsProfit' => $partsRev - $partsCost,
                    'otherRev'  => $otherRev, 'otherProfit' => $otherRev - $otherCost,
                    'srcRev'    => $srcRev, 'srcProfit' => $srcRev - (float) ($parts->src_cost ?? 0),
                    'srcBy'     => ($bySup[$inv->id] ?? collect())->mapWithKeys(fn ($r) => [(int) $r->sup => [(float) $r->rev * $keep, (float) $r->rev * $keep - (float) $r->cost]])->all(),
                    'partsShop' => $partsShop, 'partsShopProfit' => $partsShop > 0 ? $partsShop - $partsCost : 0,
                ];
            }
        }
    }

    /** Each payment in the period, with its share of sales and profit. */
    private function collected(string $from, string $to): array
    {
        $payments = DB::table('payments as p')
            ->leftJoin('invoices as ji', function ($j) {
                $j->on('ji.job_card_id', '=', 'p.job_card_id')->whereNull('ji.deleted_at');
            })
            ->whereDate('p.date', '>=', $from)->whereDate('p.date', '<=', $to)
            ->get(['p.id', 'p.date', 'p.amount', 'p.mode', 'p.invoice_id', 'ji.id as job_invoice']);

        $this->invoiceFacts($payments->map(fn ($p) => $p->invoice_id ?: $p->job_invoice)->all());

        $out = [];
        foreach ($payments as $p) {
            $amount = (float) $p->amount;
            $f = $this->invoices[$p->invoice_id ?: $p->job_invoice] ?? null;
            // an advance on a job card that is not invoiced yet: money in, profit counted once invoiced
            $share = $f && ! $f['cancelled'] && $f['total'] > 0 ? $amount / $f['total'] : 0;
            $total = $f ? $f['partsProfit'] + $f['otherProfit'] : 0;
            $garage = ($f['channel'] ?? 'garage') === 'garage';
            $shopMargin = $f ? $f['partsShopProfit'] * $share : 0;     // shop's margin on parts it supplied to this garage job
            $out[] = [
                'date'        => Carbon::parse($p->date)->toDateString(),
                'mode'        => $p->mode,
                'channel'     => $f['channel'] ?? 'garage',
                'amount'      => $amount,
                'profit'      => $total * $share,
                'partsSales'  => $f ? $f['partsRev'] * $share : 0,
                'partsProfit' => $f ? $f['partsProfit'] * $share : 0,
                'otherSales'  => $f ? $f['otherRev'] * $share : 0,
                'sourcedSales'  => $f ? $f['srcRev'] * $share : 0,
                'sourcedProfit' => $f ? $f['srcProfit'] * $share : 0,
                'sourcedBy'     => $f ? array_map(fn ($v) => [$v[0] * $share, $v[1] * $share], $f['srcBy']) : [],
                'otherProfit' => $f ? $f['otherProfit'] * $share : 0,
                // the two businesses on their own
                'garageValue'  => $garage ? $amount : 0,
                'garageProfit' => $garage ? $total * $share - $shopMargin : 0,
                'garagePartsProfit' => $garage && $f ? ($f['partsProfit'] * $share - $shopMargin) : 0,
                'sparesValue'  => $garage ? ($f ? $f['partsShop'] * $share : 0) : $amount,
                'sparesProfit' => $garage ? $shopMargin : $total * $share,
            ];
        }

        return $out;
    }

    public const BUSINESSES = ['all' => 'Whole business', 'garage' => 'Garage', 'spares' => 'Spare parts'];

    /** Channel names as each business sees them. */
    public static function channels(string $business): array
    {
        return match ($business) {
            'garage' => ['garage' => 'Garage'],
            'spares' => ['shop' => 'Shop (POS)', 'online' => 'Online', 'garage' => 'Supplied to garage'],
            default  => self::CHANNELS,
        };
    }

    public static function colors(string $business): array
    {
        return array_map(fn ($ch) => self::COLORS[$ch], array_combine(array_keys(self::channels($business)), array_keys(self::channels($business))));
    }

    /**
     * @param  string  $business  all | garage | spares
     *  - garage: garage payments; profit after paying the shop's price for parts
     *  - spares: shop + online payments, plus parts supplied to garage jobs once the garage customer pays
     *  - all:    every payment; parts counted once at the buying price
     */
    public function summary(string $from, string $to, string $business = 'all'): array
    {
        [$vField, $pField] = match ($business) {
            'garage' => ['garageValue', 'garageProfit'],
            'spares' => ['sparesValue', 'sparesProfit'],
            default  => ['amount', 'profit'],
        };
        $channels = self::channels($business);
        $all = collect($this->collected($from, $to));
        $rows = $all->filter(fn ($r) => isset($channels[$r['channel']]))
            ->map(fn ($r) => ['value' => $r[$vField], 'p' => $r[$pField]] + $r)
            ->filter(fn ($r) => $r['value'] != 0 || $r['p'] != 0)->values();
        $by = fn (string $field) => collect($channels)->map(fn ($label, $ch) => round($rows->where('channel', $ch)->sum($field), 2))->all();

        $money = $rows->sum('value');
        $profit = $rows->sum('p');
        // payments customers actually made to this business (parts supplied to the garage are paid by the garage)
        $customerPayments = $business === 'spares' ? $rows->where('channel', '!=', 'garage') : $rows;

        // what is still to come in: unpaid balances and the profit inside them
        $open = DB::table('invoices')->whereNull('deleted_at')->where('status', '!=', Invoice::CANCELLED)->where('balance', '>', 0)->get(['id', 'balance']);
        $this->invoiceFacts($open->pluck('id')->all());
        $owed = array_fill_keys(array_keys($channels), 0.0);
        $owedProfit = 0.0;
        foreach ($open as $inv) {
            $f = $this->invoices[$inv->id];
            $ch = $f['channel'];
            if (! isset($owed[$ch]) || ($business === 'spares' && $ch === 'garage')) {
                continue;
            }
            $share = $f['total'] > 0 ? (float) $inv->balance / $f['total'] : 0;
            $p = $f['partsProfit'] + $f['otherProfit'] - ($business === 'garage' ? $f['partsShopProfit'] : 0);
            $owed[$ch] += (float) $inv->balance;
            $owedProfit += $p * $share;
        }
        if ($business === 'spares') {
            unset($owed['garage']);
        }

        $garageRows = $rows->where('channel', 'garage');
        // spare parts sold in the shop and online: from our own shelf vs bought from suppliers when sold
        $sold = $all->whereIn('channel', ['shop', 'online']);
        $srcSales = $sold->sum('sourcedSales');
        $srcProfit = $sold->sum('sourcedProfit');
        $perSup = [];
        foreach ($sold as $r) {
            foreach ($r['sourcedBy'] as $sid => [$sales, $profit]) {
                $perSup[$sid] = [($perSup[$sid][0] ?? 0) + $sales, ($perSup[$sid][1] ?? 0) + $profit];
            }
        }
        $names = DB::table('suppliers')->whereIn('id', array_keys($perSup))->pluck('name', 'id');

        return [
            'business'    => $business,
            'channels'    => $channels,
            'money'       => round($money, 2),
            'profit'      => round($profit, 2),
            'margin'      => $money > 0 ? round($profit / $money * 100, 1) : 0,
            'moneyBy'     => $by('value'),
            'profitBy'    => $by('p'),
            'partsBy'     => array_intersect_key($by('partsSales'), self::CHANNELS),
            'partsProfitBy' => $by('partsProfit'),
            'partsSales'  => round($rows->sum('partsSales'), 2),
            'supply'      => [
                'own'     => ['sales' => round($sold->sum('partsSales') - $srcSales, 2), 'profit' => round($sold->sum('partsProfit') - $srcProfit, 2)],
                'suppliers' => ['sales' => round($srcSales, 2), 'profit' => round($srcProfit, 2)],
                'bySupplier' => collect($perSup)->map(fn ($v, $sid) => ['name' => $names[$sid] ?? 'Supplier', 'sales' => round($v[0], 2), 'profit' => round($v[1], 2)])
                    ->sortByDesc('sales')->values()->all(),
            ],
            'labourSales' => round($garageRows->sum('otherSales'), 2),
            'labourProfit' => round($garageRows->sum('otherProfit'), 2),
            'garagePartsSales' => round($garageRows->sum('partsSales'), 2),
            'garagePartsProfit' => round($business === 'all' ? $garageRows->sum('partsProfit') : $garageRows->sum('garagePartsProfit'), 2),
            'count'       => $customerPayments->where('amount', '>', 0)->count(),
            'modes'       => $customerPayments->groupBy('mode')->map(fn ($g, $m) => ['mode' => $m, 'amount' => round($g->sum('amount'), 2), 'count' => $g->count()])
                ->sortByDesc('amount')->values()->all(),
            'owed'        => array_map(fn ($v) => round($v, 2), $owed),
            'owedTotal'   => round(array_sum($owed), 2),
            'owedProfit'  => round($owedProfit, 2),
            'trend'       => $this->trend($rows, $from, $to, array_keys($channels)),
        ];
    }

    /** Money collected per day (or per month for long ranges), split by channel. */
    private function trend($rows, string $from, string $to, array $channels): array
    {
        $byMonth = Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 92;
        $fmt = $byMonth ? 'Y-m' : 'Y-m-d';
        $sums = [];
        foreach ($rows as $r) {
            $k = Carbon::parse($r['date'])->format($fmt);
            $sums[$k][$r['channel']] = ($sums[$k][$r['channel']] ?? 0) + $r['value'];
            $sums[$k]['profit'] = ($sums[$k]['profit'] ?? 0) + $r['p'];
        }
        $period = $byMonth ? CarbonPeriod::create(Carbon::parse($from)->startOfMonth(), '1 month', Carbon::parse($to)) : CarbonPeriod::create($from, $to);
        $out = [];
        foreach ($period as $d) {
            $k = $d->format($fmt);
            $row = ['label' => $byMonth ? $d->format('M Y') : $d->format('d M'), 'profit' => round($sums[$k]['profit'] ?? 0, 2)];
            foreach ($channels as $ch) {
                $row[$ch] = round(max(0, $sums[$k][$ch] ?? 0), 2);
            }
            $out[] = $row;
        }

        return $out;
    }

    /** Parts sold in the period by channel (by invoice date), most sold first. */
    public function topParts(string $from, string $to, int $limit = 8, ?string $type = null): array
    {
        return DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')->join('parts as p', 'p.id', '=', 'ii.part_id')
            ->whereNull('i.deleted_at')->where('i.status', '!=', Invoice::CANCELLED)
            ->whereDate('i.date', '>=', $from)->whereDate('i.date', '<=', $to)
            ->when($type, fn ($q) => $q->where('i.type', $type))
            ->selectRaw('p.name, p.unit, sum(ii.qty) as qty, sum(ii.amount) as revenue')
            ->groupBy('p.id', 'p.name', 'p.unit')->orderByDesc('revenue')->limit($limit)->get()
            ->map(fn ($r) => ['name' => $r->name, 'unit' => $r->unit, 'qty' => (float) $r->qty, 'revenue' => (float) $r->revenue])->all();
    }
}
