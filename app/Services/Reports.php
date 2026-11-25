<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * All report figures, calculated in one place (screens, CSV export and tests use the same numbers).
 * Dates are inclusive "Y-m-d" strings. Money excludes cancelled invoices.
 */
class Reports
{
    public const REPORTS = [
        'sales'       => 'Sales summary',
        'profit'      => 'Profit',
        'payments'    => 'Money in & out',
        'receivables' => 'Customers owing',
        'payables'    => 'Suppliers owed',
        'stock'       => 'Stock & top parts',
        'mechanics'   => 'Mechanics',
        'services'    => 'Services & jobs',
    ];

    /** Reports that use the date range (the rest are "as of today"). */
    public const DATED = ['sales', 'profit', 'payments', 'stock', 'mechanics', 'services'];

    private function invoices(string $from, string $to)
    {
        return DB::table('invoices')->whereNull('deleted_at')->where('status', '!=', Invoice::CANCELLED)
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);
    }

    // ------------------------------------------------------------------ sales

    public function sales(string $from, string $to): array
    {
        $byType = $this->invoices($from, $to)
            ->selectRaw('type, count(*) as n, sum(subtotal) as subtotal, sum(discount) as discount, sum(tax_amount) as tax, sum(grand_total) as total, sum(credited) as credited, sum(balance) as balance')
            ->groupBy('type')->get()->keyBy('type');

        // labour / parts / other split of what was sold
        $lines = DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->whereNull('i.deleted_at')->where('i.status', '!=', Invoice::CANCELLED)
            ->whereDate('i.date', '>=', $from)->whereDate('i.date', '<=', $to)
            ->selectRaw("i.type, CASE WHEN ii.part_id IS NOT NULL THEN 'parts' WHEN ii.service_id IS NOT NULL THEN 'labour' ELSE 'other' END as kind, sum(ii.amount) as amount")
            ->groupBy('i.type', 'kind')->get();

        $returns = (float) DB::table('returns')->where('type', 'customer')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->sum('total');

        $row = fn ($t) => [
            'count'    => (int) ($byType[$t]->n ?? 0),
            'subtotal' => (float) ($byType[$t]->subtotal ?? 0),
            'discount' => (float) ($byType[$t]->discount ?? 0),
            'tax'      => (float) ($byType[$t]->tax ?? 0),
            'total'    => (float) ($byType[$t]->total ?? 0),
            'balance'  => (float) ($byType[$t]->balance ?? 0),
            'labour'   => (float) $lines->where('type', $t)->where('kind', 'labour')->sum('amount'),
            'parts'    => (float) $lines->where('type', $t)->where('kind', 'parts')->sum('amount'),
            'other'    => (float) $lines->where('type', $t)->where('kind', 'other')->sum('amount'),
        ];
        $garage = $row('garage');
        $counter = $row('counter');

        return [
            'garage'  => $garage,
            'counter' => $counter,
            'returns' => $returns,
            'total'   => $garage['total'] + $counter['total'],
            'net'     => $garage['total'] + $counter['total'] - $returns,
            'unpaid'  => $garage['balance'] + $counter['balance'],
            'daily'   => $this->daily($from, $to),
        ];
    }

    /** Per-day totals: [['date' => 'Y-m-d', 'garage' => x, 'counter' => y], ...] (max 92 days, else by month). */
    public function daily(string $from, string $to): array
    {
        $byMonth = Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 92;
        $fmt = $byMonth ? 'Y-m' : 'Y-m-d';

        $sums = [];
        foreach ($this->invoices($from, $to)->get(['date', 'type', 'grand_total']) as $r) {
            $k = Carbon::parse($r->date)->format($fmt);
            $sums[$k][$r->type] = ($sums[$k][$r->type] ?? 0) + (float) $r->grand_total;
        }

        $out = [];
        $period = $byMonth ? CarbonPeriod::create(Carbon::parse($from)->startOfMonth(), '1 month', Carbon::parse($to)) : CarbonPeriod::create($from, $to);
        foreach ($period as $d) {
            $k = $d->format($fmt);
            $out[] = ['date' => $k, 'label' => $byMonth ? $d->format('M Y') : $d->format('d M'),
                'garage' => round($sums[$k]['garage'] ?? 0, 2), 'counter' => round($sums[$k]['counter'] ?? 0, 2)];
        }

        return $out;
    }

    // ------------------------------------------------------------------ profit

    public function profit(string $from, string $to): array
    {
        $rows = [];
        foreach (['garage' => 'Garage jobs', 'counter' => 'Spare parts — counter sales'] as $type => $label) {
            $inv = $this->invoices($from, $to)->where('type', $type);
            $revenue = (float) (clone $inv)->sum('subtotal') - (float) (clone $inv)->sum('discount');
            $cost = (float) DB::table('invoice_items')->whereIn('invoice_id', (clone $inv)->select('id'))->sum('cost');
            $rows[$type] = ['label' => $label, 'revenue' => $revenue, 'cost' => $cost, 'profit' => $revenue - $cost,
                'margin' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : 0];
        }

        // spare-parts shop's margin on parts it supplied to garage jobs:
        // the garage's cost on those lines is the shop's revenue; the shop's cost is the buying price
        $toGarage = DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->whereNull('i.deleted_at')->where('i.status', '!=', Invoice::CANCELLED)->where('i.type', 'garage')->whereNotNull('ii.part_id')
            ->whereDate('i.date', '>=', $from)->whereDate('i.date', '<=', $to)
            ->selectRaw('sum(ii.cost) as revenue, sum(COALESCE(ii.stock_cost, ii.cost)) as cost')->first();
        $tr = (float) ($toGarage->revenue ?? 0);
        $tc = (float) ($toGarage->cost ?? 0);
        $rows['to_garage'] = ['label' => 'Spare parts — supplied to garage', 'revenue' => $tr, 'cost' => $tc, 'profit' => $tr - $tc,
            'margin' => $tr > 0 ? round(($tr - $tc) / $tr * 100, 1) : 0, 'internal' => true];

        // customer returns in the period take back revenue and put the cost back on the shelf
        $retRevenue = (float) DB::table('returns')->where('type', 'customer')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->sum('total');
        $retCost = (float) DB::table('stock_movements')->where('type', 'return_in')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->selectRaw('sum(qty_in * unit_cost) as c')->value('c');

        // the internal sale is revenue for the shop and cost for the garage, so it cancels out in the business total
        $revenue = $rows['garage']['revenue'] + $rows['counter']['revenue'] - $retRevenue;
        $cost = $rows['garage']['cost'] - $tr + $tc + $rows['counter']['cost'] - $retCost;

        // what kind of work earns the garage its profit
        $garageMix = DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->whereNull('i.deleted_at')->where('i.status', '!=', Invoice::CANCELLED)->where('i.type', 'garage')
            ->whereDate('i.date', '>=', $from)->whereDate('i.date', '<=', $to)
            ->selectRaw("CASE WHEN ii.part_id IS NOT NULL THEN 'Parts' WHEN ii.service_id IS NOT NULL THEN 'Labour' ELSE 'Other' END as kind, sum(ii.amount) as revenue, sum(ii.cost) as cost")
            ->groupBy('kind')->get()
            ->map(fn ($r) => ['kind' => $r->kind, 'revenue' => (float) $r->revenue, 'cost' => (float) $r->cost, 'profit' => (float) $r->revenue - (float) $r->cost])
            ->sortByDesc('profit')->values()->all();

        return [
            'rows'      => $rows,
            'returns'   => ['revenue' => $retRevenue, 'cost' => $retCost],
            'revenue'   => $revenue,
            'cost'      => $cost,
            'profit'    => $revenue - $cost,
            'margin'    => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : 0,
            'garageMix' => $garageMix,
            'garageProfit' => $rows['garage']['profit'],
            'sparesProfit' => $rows['counter']['profit'] + $rows['to_garage']['profit'] - ($retRevenue - $retCost),
        ];
    }

    // ------------------------------------------------------------------ money in / out

    public function payments(string $from, string $to): array
    {
        $in = DB::table('payments')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->selectRaw('mode, sum(CASE WHEN amount > 0 THEN amount ELSE 0 END) as received, sum(CASE WHEN amount < 0 THEN -amount ELSE 0 END) as refunded, count(*) as n')
            ->groupBy('mode')->orderByDesc('received')->get()
            ->map(fn ($r) => ['mode' => $r->mode, 'count' => (int) $r->n, 'received' => (float) $r->received, 'refunded' => (float) $r->refunded, 'net' => (float) $r->received - (float) $r->refunded])->all();

        $out = DB::table('supplier_payments')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->selectRaw('mode, sum(amount) as amount, count(*) as n')->groupBy('mode')->orderByDesc('amount')->get()
            ->map(fn ($r) => ['mode' => $r->mode, 'count' => (int) $r->n, 'amount' => (float) $r->amount])->all();

        $days = DB::table('payments')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->selectRaw('date(date) as d, sum(amount) as amount')->groupBy('d')->orderBy('d')->get()
            ->map(fn ($r) => ['date' => $r->d, 'amount' => (float) $r->amount])->all();

        $totalIn = array_sum(array_column($in, 'net'));
        $totalOut = array_sum(array_column($out, 'amount'));

        return ['in' => $in, 'out' => $out, 'days' => $days, 'totalIn' => $totalIn, 'totalOut' => $totalOut, 'net' => $totalIn - $totalOut];
    }

    // ------------------------------------------------------------------ owing (as of today)

    private function aging($date, float $amount, array &$b): void
    {
        $days = Carbon::parse($date)->startOfDay()->diffInDays(today());
        $key = $days <= 30 ? 'd30' : ($days <= 60 ? 'd60' : ($days <= 90 ? 'd90' : 'older'));
        $b[$key] += $amount;
        $b['total'] += $amount;
    }

    public function receivables(): array
    {
        $blank = ['d30' => 0, 'd60' => 0, 'd90' => 0, 'older' => 0, 'total' => 0];
        $customers = [];
        $all = $blank;

        $open = Invoice::with('customer:id,name,phone,opening_balance')->active()->where('balance', '>', 0.009)->get();
        foreach ($open as $inv) {
            $key = $inv->customer_id ?: 'walkin-'.$inv->id;
            $customers[$key] ??= ['name' => $inv->customerName(), 'phone' => $inv->customer?->phone, 'invoices' => 0, 'oldest' => $inv->date] + $blank;
            $customers[$key]['invoices']++;
            $customers[$key]['oldest'] = min($customers[$key]['oldest'], $inv->date);
            $this->aging($inv->date, (float) $inv->balance, $customers[$key]);
            $this->aging($inv->date, (float) $inv->balance, $all);
        }
        // opening balances brought from the old system count as "older"
        foreach (DB::table('customers')->whereNull('deleted_at')->where('opening_balance', '>', 0)->get(['id', 'name', 'phone', 'opening_balance']) as $c) {
            $customers[$c->id] ??= ['name' => $c->name, 'phone' => $c->phone, 'invoices' => 0, 'oldest' => null] + $blank;
            $customers[$c->id]['older'] += (float) $c->opening_balance;
            $customers[$c->id]['total'] += (float) $c->opening_balance;
            $all['older'] += (float) $c->opening_balance;
            $all['total'] += (float) $c->opening_balance;
        }

        return ['rows' => collect($customers)->sortByDesc('total')->values()->all(), 'totals' => $all];
    }

    public function payables(): array
    {
        $blank = ['d30' => 0, 'd60' => 0, 'd90' => 0, 'older' => 0, 'total' => 0];
        $suppliers = [];
        $all = $blank;
        foreach (PurchaseOrder::with('supplier:id,name,phone')->whereIn('status', PurchaseOrder::OWING)->where('balance', '>', 0.009)->get() as $po) {
            $k = $po->supplier_id;
            $suppliers[$k] ??= ['name' => $po->supplier?->name, 'phone' => $po->supplier?->phone, 'orders' => 0] + $blank;
            $suppliers[$k]['orders']++;
            $this->aging($po->date, (float) $po->balance, $suppliers[$k]);
            $this->aging($po->date, (float) $po->balance, $all);
        }
        foreach (DB::table('suppliers')->whereNull('deleted_at')->where('opening_balance', '>', 0)->get(['id', 'name', 'phone', 'opening_balance']) as $s) {
            $suppliers[$s->id] ??= ['name' => $s->name, 'phone' => $s->phone, 'orders' => 0] + $blank;
            $suppliers[$s->id]['older'] += (float) $s->opening_balance;
            $suppliers[$s->id]['total'] += (float) $s->opening_balance;
            $all['older'] += (float) $s->opening_balance;
            $all['total'] += (float) $s->opening_balance;
        }

        return ['rows' => collect($suppliers)->sortByDesc('total')->values()->all(), 'totals' => $all];
    }

    // ------------------------------------------------------------------ stock

    public function stock(string $from, string $to): array
    {
        $byCategory = DB::table('parts')->whereNull('deleted_at')->where('is_active', true)
            ->selectRaw("COALESCE(NULLIF(category, ''), 'Uncategorised') as category, count(*) as items, sum(CASE WHEN stock_qty > 0 THEN stock_qty ELSE 0 END) as qty, sum(CASE WHEN stock_qty > 0 THEN stock_qty * purchase_price ELSE 0 END) as cost_value, sum(CASE WHEN stock_qty > 0 THEN stock_qty * selling_price ELSE 0 END) as sell_value")
            ->groupBy('category')->orderByDesc('cost_value')->get()
            ->map(fn ($r) => ['category' => $r->category, 'items' => (int) $r->items, 'qty' => (float) $r->qty, 'cost_value' => (float) $r->cost_value, 'sell_value' => (float) $r->sell_value])->all();

        // best sellers across garage + counter (net of nothing — returns shown separately)
        $top = DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')->join('parts as p', 'p.id', '=', 'ii.part_id')
            ->whereNull('i.deleted_at')->where('i.status', '!=', Invoice::CANCELLED)
            ->whereDate('i.date', '>=', $from)->whereDate('i.date', '<=', $to)
            ->selectRaw('p.id, p.sku, p.name, p.unit, p.stock_qty, sum(ii.qty) as qty, sum(ii.amount) as revenue, sum(ii.cost) as cost')
            ->groupBy('p.id', 'p.sku', 'p.name', 'p.unit', 'p.stock_qty')->orderByDesc('revenue')->limit(20)->get()
            ->map(fn ($r) => ['sku' => $r->sku, 'name' => $r->name, 'unit' => $r->unit, 'stock' => (float) $r->stock_qty, 'qty' => (float) $r->qty,
                'revenue' => (float) $r->revenue, 'profit' => (float) $r->revenue - (float) $r->cost])->all();

        $low = DB::table('parts')->whereNull('deleted_at')->where('is_active', true)->where('sourced', false)->where('reorder_level', '>', 0)
            ->whereColumn('stock_qty', '<=', 'reorder_level')->orderBy('name')
            ->get(['sku', 'name', 'unit', 'stock_qty', 'reorder_level', 'purchase_price'])
            ->map(fn ($r) => ['sku' => $r->sku, 'name' => $r->name, 'unit' => $r->unit, 'stock' => (float) $r->stock_qty, 'reorder' => (float) $r->reorder_level, 'cost' => (float) $r->purchase_price])->all();

        // in stock but nothing went out for 90 days
        $moving = DB::table('stock_movements')->where('qty_out', '>', 0)->where('date', '>=', now()->subDays(90))->distinct()->pluck('part_id');
        $dead = DB::table('parts')->whereNull('deleted_at')->where('is_active', true)->where('stock_qty', '>', 0)->whereNotIn('id', $moving)
            ->orderByRaw('stock_qty * purchase_price desc')->limit(30)->get(['sku', 'name', 'unit', 'stock_qty', 'purchase_price'])
            ->map(fn ($r) => ['sku' => $r->sku, 'name' => $r->name, 'unit' => $r->unit, 'stock' => (float) $r->stock_qty, 'value' => (float) $r->stock_qty * (float) $r->purchase_price])->all();

        return [
            'byCategory' => $byCategory,
            'costValue'  => array_sum(array_column($byCategory, 'cost_value')),
            'sellValue'  => array_sum(array_column($byCategory, 'sell_value')),
            'top'        => $top,
            'low'        => $low,
            'dead'       => $dead,
        ];
    }

    // ------------------------------------------------------------------ people & work

    public function mechanics(string $from, string $to): array
    {
        // jobs invoiced in the period, credited to the job's mechanic
        $rows = DB::table('invoices as i')->join('job_cards as j', 'j.id', '=', 'i.job_card_id')->leftJoin('mechanics as m', 'm.id', '=', 'j.mechanic_id')
            ->whereNull('i.deleted_at')->where('i.status', '!=', Invoice::CANCELLED)->where('i.type', 'garage')
            ->whereDate('i.date', '>=', $from)->whereDate('i.date', '<=', $to)
            ->selectRaw("COALESCE(m.name, 'Not assigned') as name, j.mechanic_id, count(*) as jobs, sum(i.grand_total) as value")
            ->groupBy('name', 'j.mechanic_id')->get()->keyBy('mechanic_id');

        $labour = DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')->join('job_cards as j', 'j.id', '=', 'i.job_card_id')
            ->whereNull('i.deleted_at')->where('i.status', '!=', Invoice::CANCELLED)->whereNotNull('ii.service_id')
            ->whereDate('i.date', '>=', $from)->whereDate('i.date', '<=', $to)
            ->selectRaw('j.mechanic_id, sum(ii.amount) as labour, sum(CASE WHEN ii.qty > 0 THEN ii.qty ELSE 0 END) as hours')
            ->groupBy('j.mechanic_id')->get()->keyBy('mechanic_id');

        $open = DB::table('job_cards')->whereNull('deleted_at')->whereNotIn('status', JobCard::CLOSED)
            ->selectRaw('mechanic_id, count(*) as n')->groupBy('mechanic_id')->pluck('n', 'mechanic_id');

        return $rows->map(fn ($r, $id) => [
            'name'   => $r->name,
            'jobs'   => (int) $r->jobs,
            'value'  => (float) $r->value,
            'labour' => (float) ($labour[$id]->labour ?? 0),
            'open'   => (int) ($open[$id] ?? 0),
        ])->sortByDesc('value')->values()->all();
    }

    public function services(string $from, string $to): array
    {
        $top = DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')->join('services as s', 's.id', '=', 'ii.service_id')
            ->whereNull('i.deleted_at')->where('i.status', '!=', Invoice::CANCELLED)
            ->whereDate('i.date', '>=', $from)->whereDate('i.date', '<=', $to)
            ->selectRaw('s.name, s.pricing, count(*) as times, sum(ii.qty) as qty, sum(ii.amount) as revenue')
            ->groupBy('s.id', 's.name', 's.pricing')->orderByDesc('revenue')->limit(20)->get()
            ->map(fn ($r) => ['name' => $r->name, 'hourly' => $r->pricing === 'hourly', 'times' => (int) $r->times, 'qty' => (float) $r->qty, 'revenue' => (float) $r->revenue])->all();

        $jobs = DB::table('job_cards')->whereNull('deleted_at')->whereDate('received_at', '>=', $from)->whereDate('received_at', '<=', $to);
        $byStatus = (clone $jobs)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $delivered = (clone $jobs)->whereNotNull('delivered_at')->get(['received_at', 'delivered_at']);
        $avgDays = $delivered->count() ? round($delivered->avg(fn ($j) => Carbon::parse($j->received_at)->diffInHours(Carbon::parse($j->delivered_at)) / 24), 1) : null;

        $makes = DB::table('job_cards as j')->join('vehicles as v', 'v.id', '=', 'j.vehicle_id')->whereNull('j.deleted_at')
            ->whereDate('j.received_at', '>=', $from)->whereDate('j.received_at', '<=', $to)
            ->selectRaw("COALESCE(NULLIF(v.make, ''), 'Unknown') as make, count(*) as n")->groupBy('make')->orderByDesc('n')->limit(10)->pluck('n', 'make')->all();

        return ['top' => $top, 'byStatus' => $byStatus, 'jobsIn' => array_sum($byStatus), 'avgDays' => $avgDays, 'delivered' => $delivered->count(), 'makes' => $makes];
    }

    /** Most frequent garage jobs (services) by number of times done. */
    public function topServices(string $from, string $to, int $limit = 8): array
    {
        return DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')->join('services as s', 's.id', '=', 'ii.service_id')
            ->whereNull('i.deleted_at')->where('i.status', '!=', Invoice::CANCELLED)
            ->whereDate('i.date', '>=', $from)->whereDate('i.date', '<=', $to)
            ->selectRaw('s.name, count(*) as times, sum(ii.amount) as revenue')
            ->groupBy('s.id', 's.name')->orderByDesc('times')->orderByDesc('revenue')->limit($limit)->get()
            ->map(fn ($r) => ['name' => $r->name, 'times' => (int) $r->times, 'revenue' => (float) $r->revenue])->all();
    }

    // ------------------------------------------------------------------ CSV

    /** Flat rows for CSV export: first row is the header. */
    public function csv(string $report, string $from, string $to): array
    {
        $r = fn ($n) => round((float) $n, 2);

        return match ($report) {
            'sales' => array_merge(
                [['Date', 'Garage', 'Spare parts', 'Total']],
                array_map(fn ($d) => [$d['date'], $r($d['garage']), $r($d['counter']), $r($d['garage'] + $d['counter'])], $this->sales($from, $to)['daily'])
            ),
            'profit' => (function () use ($from, $to, $r) {
                $p = $this->profit($from, $to);
                $rows = [['Source', 'Revenue (excl. tax)', 'Cost', 'Profit', 'Margin %']];
                foreach ($p['rows'] as $row) {
                    $rows[] = [$row['label'], $r($row['revenue']), $r($row['cost']), $r($row['profit']), $row['margin']];
                }
                $rows[] = ['Customer returns', -$r($p['returns']['revenue']), -$r($p['returns']['cost']), -$r($p['returns']['revenue'] - $p['returns']['cost']), ''];
                $rows[] = ['TOTAL', $r($p['revenue']), $r($p['cost']), $r($p['profit']), $p['margin']];

                return $rows;
            })(),
            'payments' => array_merge(
                [['Direction', 'Mode', 'Count', 'Received / paid', 'Refunded', 'Net']],
                array_map(fn ($m) => ['In (customers)', $m['mode'], $m['count'], $r($m['received']), $r($m['refunded']), $r($m['net'])], $this->payments($from, $to)['in']),
                array_map(fn ($m) => ['Out (suppliers)', $m['mode'], $m['count'], $r($m['amount']), '', -$r($m['amount'])], $this->payments($from, $to)['out'])
            ),
            'receivables' => array_merge(
                [['Customer', 'Phone', 'Invoices', '0-30 days', '31-60 days', '61-90 days', 'Over 90 days', 'Total owed']],
                array_map(fn ($c) => [$c['name'], $c['phone'], $c['invoices'], $r($c['d30']), $r($c['d60']), $r($c['d90']), $r($c['older']), $r($c['total'])], $this->receivables()['rows'])
            ),
            'payables' => array_merge(
                [['Supplier', 'Phone', 'Orders', '0-30 days', '31-60 days', '61-90 days', 'Over 90 days', 'Total owed']],
                array_map(fn ($c) => [$c['name'], $c['phone'], $c['orders'], $r($c['d30']), $r($c['d60']), $r($c['d90']), $r($c['older']), $r($c['total'])], $this->payables()['rows'])
            ),
            'stock' => array_merge(
                [['SKU', 'Part', 'Qty sold', 'Revenue', 'Profit', 'In stock now']],
                array_map(fn ($p) => [$p['sku'], $p['name'], $p['qty'], $r($p['revenue']), $r($p['profit']), $p['stock']], $this->stock($from, $to)['top'])
            ),
            'mechanics' => array_merge(
                [['Mechanic', 'Jobs invoiced', 'Job value', 'Labour', 'Open jobs now']],
                array_map(fn ($m) => [$m['name'], $m['jobs'], $r($m['value']), $r($m['labour']), $m['open']], $this->mechanics($from, $to))
            ),
            'services' => array_merge(
                [['Service', 'Times', 'Qty / hours', 'Revenue']],
                array_map(fn ($s) => [$s['name'], $s['times'], $s['qty'], $r($s['revenue'])], $this->services($from, $to)['top'])
            ),
            default => [['Unknown report']],
        };
    }
}
