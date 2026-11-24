<?php

namespace App\Livewire\Imports;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\ImportRequest;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** "Import for you" requests: quote, deposit, shipping and arrival. */
#[Title('Import Orders')]
class Index extends Component
{
    use ManagesRecords, WithPagination;

    protected array $manageRoles = ['admin', 'manager', 'staff'];

    #[Url(except: 'open')]
    public string $tab = 'open';

    #[Url(except: '')]
    public string $search = '';

    public bool $showView = false;
    public ?int $viewId = null;
    public array $form = [];

    public function updating($f): void
    {
        if (in_array($f, ['tab', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function open(int $id): void
    {
        $r = ImportRequest::findOrFail($id);
        $this->viewId = $r->id;
        $this->form = [
            'status'           => $r->status,
            'quote_amount'     => $r->quote_amount !== null ? (string) (float) $r->quote_amount : '',
            'deposit_paid'     => $r->deposit_paid !== null ? (string) (float) $r->deposit_paid : '',
            'eta'              => $r->eta?->toDateString() ?? '',
            'customer_message' => (string) $r->customer_message,
            'staff_note'       => (string) $r->staff_note,
        ];
        $this->resetValidation();
        $this->showView = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $data = $this->validate([
            'form.status'           => ['required', Rule::in(array_keys(ImportRequest::STATUSES))],
            'form.quote_amount'     => 'nullable|numeric|min:0',
            'form.deposit_paid'     => 'nullable|numeric|min:0',
            'form.eta'              => 'nullable|date',
            'form.customer_message' => 'nullable|string|max:500',
            'form.staff_note'       => 'nullable|string|max:1000',
        ])['form'];
        if (in_array($data['status'], ['quoted', 'confirmed', 'ordered', 'shipping', 'arrived', 'completed'], true) && $data['quote_amount'] === '') {
            $this->addError('form.quote_amount', 'Enter the price you quoted before moving past “New”.');

            return;
        }
        if ($data['deposit_paid'] !== '' && $data['quote_amount'] !== '' && (float) $data['deposit_paid'] > (float) $data['quote_amount']) {
            $this->addError('form.deposit_paid', 'The deposit cannot be more than the quoted price.');

            return;
        }
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $r = ImportRequest::findOrFail($this->viewId);
        $was = $r->status;
        $r->update($data + ['handled_by' => auth()->id()]);
        Audit::log('Imports', 'IMPORT_UPDATE', $r->ref.' '.$was.' → '.$r->status);
        $this->toast($r->ref.' saved.');
        $this->showView = false;
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasRole('admin', 'manager'), 403);
        $r = ImportRequest::findOrFail($id);
        $r->delete();
        Audit::log('Imports', 'IMPORT_DELETE', $r->ref);
        $this->showView = false;
        $this->toast('Request deleted.');
    }

    public function render()
    {
        $tabs = ['open' => 'Open', 'new' => 'New', 'quoted' => 'Quote sent', 'progress' => 'Paid & on the way', 'arrived' => 'Arrived', 'done' => 'Finished'];
        $q = ImportRequest::query()
            ->when($this->tab === 'open', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->when($this->tab === 'progress', fn ($q) => $q->whereIn('status', ['confirmed', 'ordered', 'shipping']))
            ->when($this->tab === 'done', fn ($q) => $q->whereIn('status', ['completed', 'cancelled']))
            ->when(in_array($this->tab, ['new', 'quoted', 'arrived'], true), fn ($q) => $q->where('status', $this->tab))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('ref', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")->orWhere('make', 'like', "%{$this->search}%")
                ->orWhere('model', 'like', "%{$this->search}%")->orWhere('engine_code', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%")))
            ->latest();
        $view = $this->viewId ? ImportRequest::with('handler:id,name', 'customer:id,name')->find($this->viewId) : null;

        return view('livewire.imports.index', [
            'requests' => $q->paginate(20),
            'tabs'     => $tabs,
            'newCount' => ImportRequest::where('status', 'new')->count(),
            'view'     => $view,
            'waLink'   => $view && $view->phone ? 'https://wa.me/'.ltrim(Phone::international($view->phone), '+').'?text='.rawurlencode(
                'Hello '.($view->name ?: '').', this is '.setting('business_name').' about your import request '.$view->ref.' ('.$view->item_type.', '.$view->car().').'
                .($view->quote_amount ? ' Our price delivered to Dar es Salaam is '.money($view->quote_amount).'.' : '')
            ) : null,
        ]);
    }
}
