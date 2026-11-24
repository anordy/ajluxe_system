<?php

namespace App\Livewire\Website;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\CarEngine;
use App\Models\CarModel;
use App\Models\Part;
use App\Support\Audit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Cars in the website's "Find parts for your car" list. */
#[Title('Car List')]
class Cars extends Component
{
    use ManagesRecords, WithPagination;

    protected array $manageRoles = ['admin', 'manager'];

    #[Url(except: '')]
    public string $search = '';

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];

    private static function blank(): array
    {
        return ['make' => '', 'model' => '', 'year_from' => '', 'year_to' => '', 'is_active' => true, 'engines' => ''];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
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
        $c = CarModel::findOrFail($id);
        $this->editingId = $c->id;
        $this->form = ['make' => $c->make, 'model' => $c->model, 'year_from' => (string) ($c->year_from ?? ''), 'year_to' => (string) ($c->year_to ?? ''), 'is_active' => $c->is_active,
            'engines' => $c->engines->map->line()->implode("\n")];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $max = now()->year + 1;
        $data = $this->validate([
            'form.make'      => 'required|string|max:60',
            'form.model'     => ['required', 'string', 'max:80', Rule::unique('car_models', 'model')->where('make', trim($this->form['make'] ?? ''))->ignore($this->editingId)],
            'form.year_from' => "nullable|integer|min:1960|max:$max",
            'form.year_to'   => "nullable|integer|min:1960|max:$max|gte:form.year_from",
            'form.is_active' => 'boolean',
            'form.engines'   => 'nullable|string|max:5000',
        ], ['form.model.unique' => 'This car is already in the list.', 'form.year_to.gte' => 'The last year must be after the first year.'])['form'];
        $data['make'] = trim($data['make']);
        $data['model'] = trim($data['model']);
        $data['year_from'] = $data['year_from'] ?: null;
        $data['year_to'] = $data['year_to'] ?: null;
        $engineLines = $data['engines'] ?? '';
        unset($data['engines']);

        $c = $this->editingId ? tap(CarModel::findOrFail($this->editingId))->update($data) : CarModel::create($data);
        $this->syncEngines($c, (string) $engineLines);
        Audit::log('Website', $this->editingId ? 'CAR_UPDATE' : 'CAR_CREATE', $c->make.' '.$c->model);
        $this->toast($this->editingId ? 'Car updated.' : 'Car added to the website list.');
        $this->showForm = false;
    }

    /** Keep engines that are still listed (their part links stay), add new ones, remove the rest. */
    private function syncEngines(CarModel $c, string $text): void
    {
        $keep = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if ($e = CarEngine::parseLine($line)) {
                $engine = CarEngine::updateOrCreate(['car_model_id' => $c->id, 'code' => $e['code'], 'litres' => $e['litres'], 'fuel' => $e['fuel']], $e);
                $keep[] = $engine->id;
            }
        }
        $c->engines()->whereNotIn('id', $keep)->delete();
    }

    public function toggle(int $id): void
    {
        $this->authorizeManage();
        $c = CarModel::findOrFail($id);
        $c->update(['is_active' => ! $c->is_active]);
        $this->toast($c->make.' '.$c->model.($c->is_active ? ' shows on the website.' : ' is hidden from the website.'));
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $c = CarModel::findOrFail($id);
        $c->delete();
        Audit::log('Website', 'CAR_DELETE', $c->make.' '.$c->model);
        $this->toast('Car removed from the list.');
    }

    /** Add every make/model already written on parts, so the list starts complete. */
    public function importFromParts(): void
    {
        $this->authorizeManage();
        $n = 0;
        foreach (Part::whereNotNull('vehicle_make')->where('vehicle_make', '!=', '')->get(['vehicle_make', 'vehicle_model']) as $p) {
            foreach (preg_split('/\s*[\/,]\s*/', (string) $p->vehicle_model) as $m) {
                $m = trim(preg_replace('/\s+\d{3,}.*$/', '', $m));
                if ($m === '' || preg_match('/^(all|most)\b/i', $m)) {
                    continue;
                }
                $car = CarModel::firstOrCreate(['make' => trim($p->vehicle_make), 'model' => $m], ['is_active' => true]);
                $n += $car->wasRecentlyCreated ? 1 : 0;
            }
        }
        $this->toast($n ? "$n cars added from the parts catalogue." : 'The list already has every car from the parts catalogue.');
    }

    public function render()
    {
        return view('livewire.website.cars', [
            'cars' => CarModel::query()
                ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('make', 'like', "%{$this->search}%")->orWhere('model', 'like', "%{$this->search}%")))
                ->withCount('engines')->orderBy('make')->orderBy('model')->paginate(30),
            'makes' => CarModel::distinct()->orderBy('make')->pluck('make'),
        ]);
    }
}
