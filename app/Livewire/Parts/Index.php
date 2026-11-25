<?php

namespace App\Livewire\Parts;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\CarEngine;
use App\Models\CarModel;
use App\Models\Part;
use App\Models\StockMovement;
use App\Support\Audit;
use App\Support\ImageData;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Parts & Stock')]
class Index extends Component
{
    use ManagesRecords, WithPagination;

    /** Add/edit parts and prices. Sales staff and mechanics can look parts up (no cost prices). */
    protected array $manageRoles = ['admin', 'manager'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $stock = '';           // '' | low | out

    #[Url(except: 'active')]
    public string $status = 'active';

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];
    /** new photo as a data URL (shrunk in the browser, sent with Save) */
    public ?string $photoData = null;
    public string $opening_qty = '';

    /** cars this part fits: [['m' => car_model_id, 'e' => car_engine_id|null], ...] */
    public array $fits = [];
    public string $fitMake = '';
    public string $fitModel = '';
    public string $fitEngine = '';

    public bool $showView = false;
    public ?int $viewId = null;

    public bool $showHistory = false;
    public ?int $historyId = null;

    public function updating($field): void
    {
        if (in_array($field, ['search', 'category', 'stock', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function canSeeCost(): bool
    {
        return (bool) auth()->user()?->hasRole('admin', 'manager');
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->editingId = null;
        $this->form = self::blank();
        $this->reset(['photoData', 'opening_qty', 'fits', 'fitMake', 'fitModel', 'fitEngine']);
        $this->resetValidation();
        $this->showForm = true;
        $this->dispatch('part-form-opened');
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $p = Part::findOrFail($id);
        $this->editingId = $p->id;
        $this->form = array_merge(self::blank(), array_map(fn ($v) => $v ?? '', $p->only(array_keys(self::blank()))));
        $this->form['is_active'] = (bool) $p->is_active;
        $this->form['show_online'] = (bool) $p->show_online;
        $this->form['sourced'] = (bool) $p->sourced;
        $this->form['source_supplier_id'] = (string) ($p->source_supplier_id ?? '');
        $this->form['specs_text'] = $p->specsText();
        $this->form['subcategory'] = (string) $p->subcategory;
        $this->form['variant_of'] = (string) ($p->variant_of ?? '');
        foreach (['purchase_price', 'selling_price', 'min_selling_price', 'discount_percent', 'tax_rate', 'reorder_level'] as $n) {
            $this->form[$n] = (float) $p->{$n} ? (string) ($p->{$n} + 0) : '';   // 45000.00 → 45000
        }
        $this->reset(['photoData', 'opening_qty', 'fitMake', 'fitModel', 'fitEngine']);
        $this->fits = $p->fitments()->get()->map(fn ($f) => ['m' => $f->car_model_id, 'e' => $f->car_engine_id])->all();
        $this->resetValidation();
        $this->showForm = true;
        $this->dispatch('part-form-opened');
    }

    // ---------------------------------------------------------------- cars the part fits

    public function updatedFitMake(): void
    {
        $this->fitModel = $this->fitEngine = '';
    }

    public function updatedFitModel(): void
    {
        $this->fitEngine = '';
    }

    public function addFit(): void
    {
        $model = CarModel::find((int) $this->fitModel);
        if (! $model) {
            $this->addError('fitModel', 'Choose the make and model.');

            return;
        }
        $engine = $this->fitEngine !== '' ? CarEngine::where('car_model_id', $model->id)->find((int) $this->fitEngine) : null;
        $new = ['m' => $model->id, 'e' => $engine?->id];
        // "all engines" replaces single engines of the same model, and a single engine is not added when all engines are already in
        if ($engine === null) {
            $this->fits = array_values(array_filter($this->fits, fn ($f) => $f['m'] !== $model->id));
        } elseif (collect($this->fits)->contains(fn ($f) => $f['m'] === $model->id && $f['e'] === null)) {
            $this->addError('fitModel', 'All engines of this model are already in the list.');

            return;
        }
        if (! in_array($new, $this->fits, true)) {
            $this->fits[] = $new;
        }
        $this->fitEngine = '';
        $this->resetErrorBag('fitModel');
    }

    public function removeFit(int $i): void
    {
        unset($this->fits[$i]);
        $this->fits = array_values($this->fits);
    }

    public function view(int $id): void
    {
        $this->viewId = $id;
        $this->showView = true;
    }

    public function editFromView(int $id): void
    {
        $this->showView = false;
        $this->edit($id);
    }

    public function save(): void
    {
        $this->authorizeManage();
        $this->form['sku'] = strtoupper(trim((string) $this->form['sku']));

        $this->validate([
            'form.sku'            => ['nullable', 'string', 'max:40', Rule::unique('parts', 'sku')->ignore($this->editingId)],
            'form.name'           => 'required|string|max:150',
            'form.oem_no'         => 'nullable|string|max:60',
            'form.brand'          => 'nullable|string|max:60',
            'form.category'       => 'nullable|string|max:60',
            'form.subcategory'    => 'nullable|string|max:80',
            'form.specs_text'     => 'nullable|string|max:2000',
            'form.unit'           => 'required|string|max:20',
            'form.vehicle_make'   => 'nullable|string|max:60',
            'form.vehicle_model'  => 'nullable|string|max:120',
            'form.rack_location'  => 'nullable|string|max:40',
            'form.purchase_price' => 'nullable|numeric|min:0',
            'form.selling_price'  => 'nullable|numeric|min:0',
            'form.min_selling_price' => 'nullable|numeric|min:0|lte:form.selling_price',
            'form.tax_rate'       => 'nullable|numeric|min:0|max:100',
            'form.reorder_level'  => 'nullable|numeric|min:0',
            'form.is_active'      => 'boolean',
            'form.show_online'    => 'boolean',
            'form.sourced'        => 'boolean',
            'form.source_supplier_id' => ['nullable', 'required_if_accepted:form.sourced', 'integer', 'exists:suppliers,id'],
            'form.discount_percent' => 'nullable|numeric|min:0|max:90',
            'form.web_description'  => 'nullable|string|max:2000',
            'form.variant_of'       => ['nullable', 'integer', 'exists:parts,id', Rule::notIn(array_filter([$this->editingId]))],
            'opening_qty'         => 'nullable|numeric|min:0',
        ], ['form.sku.unique' => 'Another part already uses this SKU / part code.', 'form.min_selling_price.lte' => 'The minimum price cannot be above the selling price.', 'form.source_supplier_id.required_if_accepted' => 'Pick the supplier you buy this part from.']);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        foreach (['purchase_price', 'selling_price', 'min_selling_price', 'discount_percent', 'tax_rate', 'reorder_level'] as $n) {
            $data[$n] = (float) ($data[$n] ?? 0);
        }
        $data['unit'] ??= 'Pcs';
        $data['sourced'] = (bool) ($data['sourced'] ?? false);
        if (! $data['sourced']) {
            $data['source_supplier_id'] = null;
        } elseif ((float) $data['purchase_price'] <= 0) {
            $this->addError('form.purchase_price', 'Enter what the supplier charges you, so profit is right.');

            return;
        }
        // another size of a product always points at the main part (never a chain), and a main part cannot become a size
        if ($data['variant_of'] ?? null) {
            $main = Part::find($data['variant_of']);
            $data['variant_of'] = $main->variant_of ?: $main->id;
            if ($this->editingId && ($data['variant_of'] == $this->editingId || Part::where('variant_of', $this->editingId)->exists())) {
                $this->addError('form.variant_of', 'This part already has other sizes linked to it. Link those sizes to it instead.');

                return;
            }
        }
        $data['specs'] = Part::parseSpecs($data['specs_text'] ?? null);
        unset($data['specs_text']);

        $photo = null;
        if ($this->photoData) {
            try {
                $photo = ImageData::store($this->photoData, 'parts');
            } catch (\InvalidArgumentException $e) {
                $this->addError('photoData', $e->getMessage());

                return;
            }
        }

        DB::transaction(function () use ($data, $photo) {
            if ($this->editingId) {
                $part = Part::findOrFail($this->editingId);
                $data['sku'] ??= $part->sku;
                $part->update($data);
                Audit::log('Parts', 'UPDATE', $part->sku, $part->name);
            } else {
                $data['sku'] ??= Numbering::code('part', 'PRT', 5);
                $part = Part::create($data);
                // supplier parts are bought when sold: never add stock for them here
                $qty = $part->sourced ? 0 : (float) ($this->opening_qty ?: 0);
                if ($qty > 0) {
                    StockMovement::record($part, 'opening', in: $qty, unitCost: (float) $part->purchase_price, note: 'Opening stock');
                }
                Audit::log('Parts', 'CREATE', $part->sku, $part->name.($qty ? " (opening stock {$qty})" : ''));
            }

            // cars it fits
            $part->fitments()->delete();
            foreach (collect($this->fits)->unique(fn ($f) => $f['m'].'-'.($f['e'] ?? 0)) as $f) {
                if (CarModel::whereKey($f['m'])->exists()) {
                    $part->fitments()->create(['car_model_id' => $f['m'], 'car_engine_id' => $f['e'] ?: null]);
                }
            }

            if ($photo) {
                $old = $part->image_path;
                $part->update(['image_path' => $photo]);
                if ($old) {
                    Storage::disk('public')->delete($old);
                }
            }
        });

        $this->showForm = false;
        $this->reset(['photoData', 'opening_qty']);
        $this->toast($this->editingId ? 'Part updated.' : 'Part added.');
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();
        $p = Part::findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
        Audit::log('Parts', $p->is_active ? 'ACTIVATE' : 'DEACTIVATE', $p->sku);
        $this->toast($p->name.' is now '.($p->is_active ? 'active' : 'inactive').'.');
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $p = Part::findOrFail($id);
        if ($reason = $p->deleteBlocker()) {
            $this->toast($reason, 'error');

            return;
        }
        $p->movements()->delete();
        $p->delete();
        Audit::log('Parts', 'DELETE', $p->sku, $p->name);
        $this->toast('Part deleted.');
    }

    public function history(int $id): void
    {
        $this->historyId = $id;
        $this->showHistory = true;
    }

    public static function blank(): array
    {
        return [
            'sku' => '', 'name' => '', 'oem_no' => '', 'brand' => '', 'category' => '', 'subcategory' => '', 'specs_text' => '', 'unit' => 'Pcs',
            'vehicle_make' => '', 'vehicle_model' => '', 'rack_location' => '', 'purchase_price' => '',
            'selling_price' => '', 'min_selling_price' => '', 'tax_rate' => '', 'reorder_level' => '', 'is_active' => true,
            'show_online' => true, 'sourced' => false, 'source_supplier_id' => '', 'discount_percent' => '', 'web_description' => '', 'variant_of' => '',
        ];
    }

    public function render()
    {
        $parts = Part::query()
            ->search($this->search)
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($this->stock === 'low', fn ($q) => $q->lowStock())
            ->when($this->stock === 'out', fn ($q) => $q->where('stock_qty', '<=', 0)->where('sourced', false))
            ->when($this->stock === 'sourced', fn ($q) => $q->where('sourced', true))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->with('fitments.carModel', 'sourceSupplier:id,name')
            ->orderBy('name')
            ->paginate(25);

        $totals = Part::active()->selectRaw('count(*) as items, sum(stock_qty * purchase_price) as value')->first();

        $fitModels = $this->fitMake ? CarModel::where('make', $this->fitMake)->orderBy('model')->get(['id', 'model']) : collect();
        $fitEngines = $this->fitModel ? CarEngine::where('car_model_id', (int) $this->fitModel)->orderBy('litres')->orderBy('fuel')->get() : collect();
        $fitCars = CarModel::with('engines')->whereIn('id', array_column($this->fits, 'm'))->get()->keyBy('id');

        return view('livewire.parts.index', [
            'fitMakes'   => CarModel::distinct()->orderBy('make')->pluck('make'),
            'fitModels'  => $fitModels,
            'fitEngines' => $fitEngines,
            'fitList'    => collect($this->fits)->map(fn ($f) => ($c = $fitCars->get($f['m']))
                ? $c->name().' – '.($f['e'] ? ($c->engines->firstWhere('id', $f['e'])?->label() ?? 'engine') : 'all engines') : null)->all(),
            'viewPart'   => $this->viewId ? Part::with('fitments.carModel', 'fitments.engine', 'variants', 'main')->find($this->viewId) : null,
            'parts'      => $parts,
            'categories' => list_options('part_category'),
            'brands'     => list_options('part_brand'),
            'units'      => list_options('unit'),
            'suppliers'  => \App\Models\Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'makes'      => list_options('vehicle_make'),
            'lowCount'   => Part::active()->lowStock()->count(),
            'totals'     => $totals,
            'historyPart' => $this->showHistory && $this->historyId ? Part::withTrashed()->find($this->historyId) : null,
            'movements'  => $this->showHistory && $this->historyId
                ? StockMovement::with('user:id,name')->where('part_id', $this->historyId)->latest('id')->limit(30)->get()
                : collect(),
        ]);
    }
}
