<?php

namespace App\Livewire\Services;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\Service;
use App\Support\Audit;
use App\Support\Numbering;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Services')]
class Index extends Component
{
    use ManagesRecords;

    protected string $managePermission = 'services';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];

    public function mount(): void
    {
        $this->form = self::blank(); // the dialog's pricing toggle needs these keys from the start
    }

    private static function blank(): array
    {
        return ['name' => '', 'category' => '', 'pricing' => 'fixed', 'rate' => '', 'est_hours' => '', 'is_active' => true];
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->editingId = null;
        $this->form = self::blank();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $s = Service::findOrFail($id);
        $this->editingId = $s->id;
        $this->form = array_merge(self::blank(), array_map(fn ($v) => $v ?? '', $s->only(array_keys(self::blank()))));
        $this->form['is_active'] = (bool) $s->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $data = $this->validate([
            'form.name'      => 'required|string|max:150',
            'form.category'  => 'nullable|string|max:60',
            'form.pricing'   => ['required', Rule::in(array_keys(Service::PRICING))],
            'form.rate'      => 'nullable|numeric|min:0',
            'form.est_hours' => 'nullable|numeric|min:0|max:500',
            'form.is_active' => 'boolean',
        ])['form'];
        $data['rate'] = (float) ($data['rate'] ?: 0);
        $data['est_hours'] = (float) ($data['est_hours'] ?: 0);

        if ($this->editingId) {
            $s = Service::findOrFail($this->editingId);
            $s->update($data);
            Audit::log('Services', 'UPDATE', $s->code, $s->name.' — '.$s->priceLabel());
            $this->toast('Service updated.');
        } else {
            $s = Service::create($data + ['code' => Numbering::code('service', 'SRV', 3)]);
            Audit::log('Services', 'CREATE', $s->code, $s->name);
            $this->toast("Service {$s->code} added.");
        }
        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();
        $s = Service::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
        Audit::log('Services', $s->is_active ? 'ACTIVATE' : 'DEACTIVATE', $s->code);
        $this->toast($s->name.' is now '.($s->is_active ? 'active' : 'inactive').'.');
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $s = Service::findOrFail($id);
        if ($reason = $s->deleteBlocker()) {
            $this->toast($reason, 'error');

            return;
        }
        $s->delete();
        Audit::log('Services', 'DELETE', $s->code, $s->name);
        $this->toast('Service deleted.');
    }

    public function render()
    {
        return view('livewire.services.index', [
            'services'   => Service::search($this->search)
                ->when($this->category, fn ($q) => $q->where('category', $this->category))
                ->orderByDesc('is_active')->orderBy('category')->orderBy('name')->get(),
            'categories' => list_options('service_category'),
            'unpriced'   => Service::active()->where('rate', 0)->count(),
        ]);
    }
}
