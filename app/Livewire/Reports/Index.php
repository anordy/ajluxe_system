<?php

namespace App\Livewire\Reports;

use App\Services\Reports;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Reports')]
class Index extends Component
{
    #[Url(except: 'sales')]
    public string $report = 'sales';

    #[Url(except: 'this_month')]
    public string $range = 'this_month';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public const RANGES = [
        'today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This week', 'this_month' => 'This month',
        'last_month' => 'Last month', 'last_90' => 'Last 90 days', 'this_year' => 'This year', 'custom' => 'Custom',
    ];

    public function mount(): void
    {
        if (! isset(Reports::REPORTS[$this->report])) {
            $this->report = 'sales';
        }
        $this->applyRange();
    }

    public function updatedRange(): void
    {
        $this->applyRange();
    }

    public function updatedFrom(): void
    {
        $this->range = 'custom';
    }

    public function updatedTo(): void
    {
        $this->range = 'custom';
    }

    private function applyRange(): void
    {
        if ($this->range === 'custom' && $this->from && $this->to) {
            return;
        }
        [$f, $t] = match ($this->range) {
            'today'      => [today(), today()],
            'yesterday'  => [today()->subDay(), today()->subDay()],
            'this_week'  => [today()->startOfWeek(), today()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'last_90'    => [today()->subDays(89), today()],
            'this_year'  => [today()->startOfYear(), today()],
            default      => [today()->startOfMonth(), today()],
        };
        $this->from = $f->toDateString();
        $this->to = $t->toDateString();
    }

    public function render()
    {
        $reports = app(Reports::class);
        $from = $this->from ?: today()->startOfMonth()->toDateString();
        $to = $this->to ?: today()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $data = match ($this->report) {
            'sales'       => $reports->sales($from, $to),
            'profit'      => $reports->profit($from, $to) + ['collected' => app(\App\Services\Analytics::class)->summary($from, $to)],
            'payments'    => $reports->payments($from, $to),
            'receivables' => $reports->receivables(),
            'payables'    => $reports->payables(),
            'stock'       => $reports->stock($from, $to),
            'mechanics'   => $reports->mechanics($from, $to),
            'services'    => $reports->services($from, $to),
        };

        return view('livewire.reports.index', [
            'data'     => $data,
            'dated'    => in_array($this->report, Reports::DATED, true),
            'period'   => Carbon::parse($from)->format('d M Y').($from !== $to ? ' – '.Carbon::parse($to)->format('d M Y') : ''),
            'fromDate' => $from,
            'toDate'   => $to,
        ]);
    }
}
