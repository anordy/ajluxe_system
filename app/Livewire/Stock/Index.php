<?php

namespace App\Livewire\Stock;

use App\Models\Part;
use App\Models\StockMovement;
use App\Support\Audit;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Stock Register')]
class Index extends Component
{
    use WithPagination;

    /** levels | movements */
    #[Url(except: 'levels')]
    public string $view = 'levels';

    #[Url(except: '')]
    public string $search = '';

    /** levels: '' | low | out ; movements: movement type */
    #[Url(except: '')]
    public string $filter = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    // adjustment
    public bool $showAdjust = false;
    public ?int $partId = null;
    public string $partSearch = '';
    public string $adjustMode = 'count';   // count | add | remove
    public string $adjustQty = '';
    public string $adjustReason = '';

    public function updating($field): void
    {
        if (in_array($field, ['view', 'search', 'filter', 'from', 'to'], true)) {
            $this->resetPage();
        }
        if ($field === 'view') {
            $this->filter = '';
        }
    }

    public function canAdjust(): bool
    {
        return auth()->user()->hasRole('admin', 'manager');
    }

    public function openAdjust(?int $partId = null): void
    {
        abort_unless($this->canAdjust(), 403);
        $this->resetErrorBag();
        $this->partId = $partId;
        $this->partSearch = '';
        $this->adjustMode = 'count';
        $this->adjustQty = '';
        $this->adjustReason = '';
        $this->showAdjust = true;
    }

    public function pickPart(int $id): void
    {
        $this->partId = $id;
        $this->partSearch = '';
    }

    public function saveAdjust(): void
    {
        abort_unless($this->canAdjust(), 403);
        $this->validate([
            'partId'       => 'required|exists:parts,id',
            'adjustMode'   => 'required|in:count,add,remove',
            'adjustQty'    => 'required|numeric|min:0',
            'adjustReason' => 'required|string|min:3|max:200',
        ], ['partId.required' => 'Pick the part.'], ['adjustQty' => 'quantity', 'adjustReason' => 'reason']);

        $part = Part::findOrFail($this->partId);
        $current = (float) $part->stock_qty;
        $qty = round((float) $this->adjustQty, 2);
        $delta = match ($this->adjustMode) {
            'count'  => round($qty - $current, 2),
            'add'    => $qty,
            'remove' => -$qty,
        };
        if ($delta == 0.0) {
            $this->addError('adjustQty', 'This would not change the stock.');

            return;
        }
        if ($current + $delta < 0) {
            $this->addError('adjustQty', 'Stock cannot go below 0 (currently '.rtrim(rtrim(number_format($current, 2), '0'), '.').').');

            return;
        }

        StockMovement::record($part, 'adjustment', in: max(0, $delta), out: max(0, -$delta),
            unitCost: (float) $part->purchase_price, note: trim($this->adjustReason));
        Audit::log('Stock', 'ADJUST', $part->sku, ($delta > 0 ? '+' : '').$delta.' — '.$this->adjustReason);

        $this->showAdjust = false;
        $this->dispatch('toast', message: "Stock of {$part->name} is now ".rtrim(rtrim(number_format($current + $delta, 2), '0'), '.').'.', type: 'success');
    }

    public function render()
    {
        $canCost = $this->canAdjust();

        if ($this->view === 'movements') {
            $rows = StockMovement::with(['part:id,sku,name,unit', 'user:id,name'])
                ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('reference_no', 'like', '%'.trim($this->search).'%')
                    ->orWhereHas('part', fn ($p) => $p->search($this->search))))
                ->when($this->filter, fn ($q) => $q->where('type', $this->filter))
                ->when($this->from, fn ($q) => $q->whereDate('date', '>=', $this->from))
                ->when($this->to, fn ($q) => $q->whereDate('date', '<=', $this->to))
                ->latest('id')->paginate(30);
        } else {
            $rows = Part::query()->search($this->search)
                ->when($this->filter === 'low', fn ($q) => $q->lowStock())
                ->when($this->filter === 'out', fn ($q) => $q->where('stock_qty', '<=', 0))
                ->orderBy('name')->paginate(30);
        }

        return view('livewire.stock.index', [
            'rows'       => $rows,
            'canCost'    => $canCost,
            'stockValue' => $canCost ? (float) Part::where('is_active', true)->where('stock_qty', '>', 0)->selectRaw('sum(stock_qty * purchase_price) as v')->value('v') : 0,
            'retailValue'=> (float) Part::where('is_active', true)->where('stock_qty', '>', 0)->selectRaw('sum(stock_qty * selling_price) as v')->value('v'),
            'lowCount'   => Part::where('is_active', true)->lowStock()->count(),
            'outCount'   => Part::where('is_active', true)->where('stock_qty', '<=', 0)->count(),
            'types'      => StockMovement::TYPES,
            'adjPart'    => $this->showAdjust && $this->partId ? Part::find($this->partId) : null,
            'partMatches'=> $this->showAdjust && strlen(trim($this->partSearch)) >= 2 ? Part::active()->search($this->partSearch)->limit(8)->get() : collect(),
        ]);
    }
}
