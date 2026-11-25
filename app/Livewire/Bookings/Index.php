<?php

namespace App\Livewire\Bookings;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\GarageBooking;
use App\Support\Audit;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Cars booked in from the website's garage page: call, confirm the day, open a job card when the car arrives. */
#[Title('Garage Bookings')]
class Index extends Component
{
    use ManagesRecords, WithPagination;

    protected string $managePermission = 'bookings';

    #[Url(except: 'open')]
    public string $tab = 'open';

    #[Url(except: '')]
    public string $search = '';

    public ?int $viewId = null;
    public bool $showView = false;
    public string $note = '';
    public string $day = '';

    public function updating($f): void
    {
        if (in_array($f, ['tab', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function open(int $id): void
    {
        $b = GarageBooking::findOrFail($id);
        $this->viewId = $b->id;
        $this->note = (string) $b->staff_note;
        $this->day = (string) $b->preferred_date?->toDateString();
        $this->showView = true;
    }

    public function setStatus(string $status): void
    {
        $this->authorizeManage();
        abort_unless(in_array($status, ['new', 'confirmed', 'cancelled'], true), 422);
        $this->validate(['day' => 'nullable|date', 'note' => 'nullable|string|max:500']);
        $b = GarageBooking::findOrFail($this->viewId);
        $b->update(['status' => $status, 'staff_note' => trim($this->note) ?: null, 'preferred_date' => $this->day ?: null,
            'handled_by' => auth()->id(), 'handled_at' => now()]);
        Audit::log('Garage', 'BOOKING_'.strtoupper($status), $b->car.($b->plate ? ' '.$b->plate : ''), $b->name.' '.$b->phone);
        $this->toast('Booking '.strtolower(GarageBooking::STATUSES[$status]).'.');
        $this->showView = false;
    }

    public function render()
    {
        $s = trim($this->search);
        $q = GarageBooking::with('customer:id,name', 'jobCard:id,number')
            ->when($this->tab === 'open', fn ($q) => $q->whereIn('status', ['new', 'confirmed']))
            ->when(! in_array($this->tab, ['open', 'all'], true), fn ($q) => $q->where('status', $this->tab))
            ->when($s !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")
                ->orWhere('car', 'like', "%{$s}%")->orWhere('plate', 'like', '%'.str_replace(' ', '', $s).'%')->orWhere('work', 'like', "%{$s}%")))
            ->when($this->tab === 'open', fn ($q) => $q->orderByRaw('preferred_date is null')->orderBy('preferred_date'))
            ->latest('id');
        $counts = GarageBooking::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('livewire.bookings.index', [
            'bookings' => $q->paginate(20),
            'counts'   => $counts,
            'tabs'     => ['open' => ['Open', ($counts['new'] ?? 0) + ($counts['confirmed'] ?? 0)]] + collect(GarageBooking::STATUSES)
                ->map(fn ($l, $k) => [$l, $counts[$k] ?? 0])->all() + ['all' => ['All', $counts->sum()]],
            'view'     => $this->viewId ? GarageBooking::with('handler:id,name', 'customer', 'jobCard:id,number')->find($this->viewId) : null,
            'canJob'   => auth()->user()->allows('job_cards.manage'),
        ]);
    }
}
