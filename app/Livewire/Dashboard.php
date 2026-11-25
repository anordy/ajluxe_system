<?php

namespace App\Livewire;

use App\Models\JobCard;
use App\Models\Mechanic;
use App\Services\Analytics;
use App\Services\Reports;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Charts and figures only. Profit is counted when the money is collected. */
#[Title('Dashboard')]
class Dashboard extends Component
{
    public const RANGES = ['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year'];

    #[Url]
    public string $range = 'month';

    /** all | garage | spares — the two businesses can be looked at on their own */
    #[Url(except: 'all')]
    public string $business = 'all';

    public function updatedRange(): void
    {
        if (! isset(self::RANGES[$this->range])) {
            $this->range = 'month';
        }
    }

    public function updatedBusiness(): void
    {
        if (! isset(Analytics::BUSINESSES[$this->business])) {
            $this->business = 'all';
        }
    }

    /** [from, to, previous from, previous to] for the chosen range. */
    private function dates(): array
    {
        $t = today();
        [$from, $to] = match ($this->range) {
            'today'      => [$t->copy(), $t->copy()],
            '7d'         => [$t->copy()->subDays(6), $t->copy()],
            '30d'        => [$t->copy()->subDays(29), $t->copy()],
            'last_month' => [$t->copy()->subMonthNoOverflow()->startOfMonth(), $t->copy()->subMonthNoOverflow()->endOfMonth()],
            'year'       => [$t->copy()->startOfYear(), $t->copy()],
            default      => [$t->copy()->startOfMonth(), $t->copy()],
        };
        $days = $from->diffInDays($to) + 1;
        // the same number of days just before, to compare against
        $prevTo = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);

        return [$from->toDateString(), $to->toDateString(), $prevFrom->toDateString(), $prevTo->toDateString()];
    }

    public function render()
    {
        $user = auth()->user();
        $isBoss = $user->allows('dashboard.money');
        [$from, $to, $prevFrom, $prevTo] = $this->dates();
        // a single day has no shape: show the last 14 days in the trend chart instead
        $trendFrom = $this->range === 'today' ? today()->subDays(13)->toDateString() : $from;

        $analytics = app(Analytics::class);
        $data = null;
        if ($isBoss) {
            $b = isset(Analytics::BUSINESSES[$this->business]) ? $this->business : 'all';
            $now = $analytics->summary($from, $to, $b);
            $prev = app(Analytics::class)->summary($prevFrom, $prevTo, $b);
            $trend = $trendFrom === $from ? $now['trend'] : app(Analytics::class)->summary($trendFrom, $to, $b)['trend'];
            $data = [
                'now'   => $now,
                'prev'  => $prev,
                'trend' => $trend,
                'topParts'    => $analytics->topParts($from, $to, 8, $b === 'garage' ? 'garage' : null),
                'topServices' => app(Reports::class)->topServices($from, $to, 6),
                'stockValue'  => (float) DB::table('parts')->whereNull('deleted_at')->where('is_active', true)->where('stock_qty', '>', 0)->selectRaw('sum(stock_qty * purchase_price) as v')->value('v'),
                'payable'     => (float) DB::table('purchase_orders')->whereIn('status', ['ordered', 'partial', 'received'])->sum('balance'),
            ];
        }

        // simple counts everyone can see
        $mine = $user->hasRole('mechanic') && ! $isBoss ? Mechanic::where('user_id', $user->id)->value('id') : null;
        $jobs = DB::table('job_cards')->whereNull('deleted_at')->whereNotIn('status', JobCard::CLOSED)
            ->when($mine, fn ($q) => $q->where('mechanic_id', $mine))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $workshop = collect(JobCard::STATUSES)->reject(fn ($s) => in_array($s, JobCard::CLOSED, true))
            ->map(fn ($s) => ['label' => $s, 'value' => (int) ($jobs[$s] ?? 0), 'display' => (string) ($jobs[$s] ?? 0)])->values()->all();

        $counts = [
            'workshop'   => array_sum(array_column($workshop, 'value')),
            'low'        => DB::table('parts')->whereNull('deleted_at')->where('is_active', true)->where('sourced', false)->where('reorder_level', '>', 0)->whereColumn('stock_qty', '<=', 'reorder_level')->count(),
            'out'        => DB::table('parts')->whereNull('deleted_at')->where('is_active', true)->where('sourced', false)->where('stock_qty', '<=', 0)->count(),
            'newOrders'  => DB::table('orders')->where('status', 'pending')->count(),
            'salesToday' => DB::table('invoices')->whereNull('deleted_at')->where('status', '!=', 'cancelled')->where('type', 'counter')->whereDate('date', today())->count(),
            'shopToday'  => (float) DB::table('invoices')->whereNull('deleted_at')->where('status', '!=', 'cancelled')->where('type', 'counter')->whereDate('date', today())->sum('grand_total'),
        ];

        return view('livewire.dashboard', [
            'user' => $user, 'isBoss' => $isBoss, 'data' => $data, 'workshop' => $workshop, 'counts' => $counts,
            'from' => $from, 'to' => $to,
            'channels' => Analytics::channels($this->business), 'colors' => Analytics::colors(isset(Analytics::BUSINESSES[$this->business]) ? $this->business : 'all'),
        ]);
    }
}
