<?php

namespace App\Livewire\JobCards;

use App\Models\JobCard;
use App\Models\Mechanic;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Job Cards')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** 'active' (all open), a status name, 'closed' or 'all' */
    #[Url(except: 'active')]
    public string $tab = 'active';

    #[Url(except: '')]
    public string $mechanic = '';

    /** Opened from a customer's page: only that customer's job cards. */
    #[Url(except: '')]
    public string $customer = '';

    public function mount(): void
    {
        // mechanics start on their own jobs
        $me = auth()->user();
        if ($me->hasRole('mechanic') && $this->mechanic === '' && ($m = Mechanic::where('user_id', $me->id)->first())) {
            $this->mechanic = (string) $m->id;
        }
    }

    public function updating($field): void
    {
        if (in_array($field, ['search', 'tab', 'mechanic', 'customer'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $base = JobCard::query()->when($this->mechanic, fn ($q) => $q->where('mechanic_id', $this->mechanic))
            ->when($this->customer, fn ($q) => $q->where('customer_id', (int) $this->customer));

        $counts = (clone $base)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $jobs = (clone $base)
            ->with(['customer:id,name,phone', 'vehicle:id,reg_no,make,model', 'mechanic:id,name', 'invoice:id,job_card_id,number,status'])
            ->search($this->search)
            ->when($this->tab === 'active', fn ($q) => $q->open())
            ->when($this->tab === 'closed', fn ($q) => $q->whereIn('status', JobCard::CLOSED))
            ->when(! in_array($this->tab, ['active', 'closed', 'all'], true), fn ($q) => $q->where('status', $this->tab))
            ->orderByRaw("CASE priority WHEN 'Urgent' THEN 0 WHEN 'High' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate(20);

        return view('livewire.job-cards.index', [
            'jobs'      => $jobs,
            'counts'    => $counts,
            'activeCount' => $counts->except(JobCard::CLOSED)->sum(),
            'mechanics' => Mechanic::active()->orderBy('name')->get(['id', 'name']),
            'canCreate' => auth()->user()->allows('job_cards.manage'),
            'customerName' => $this->customer ? \App\Models\Customer::whereKey((int) $this->customer)->value('name') : null,
        ]);
    }
}
