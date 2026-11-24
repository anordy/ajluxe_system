<?php

namespace App\Livewire\Lists;

use App\Models\ListOption;
use App\Support\Audit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Lists')]
class Index extends Component
{
    #[Url]
    public string $list = 'vehicle_make';

    public string $newValue = '';

    public function mount(): void
    {
        if (! array_key_exists($this->list, ListOption::LISTS)) {
            $this->list = 'vehicle_make';
        }
    }

    public function select(string $list): void
    {
        if (array_key_exists($list, ListOption::LISTS)) {
            $this->list = $list;
            $this->reset('newValue');
            $this->resetValidation();
        }
    }

    public function add(): void
    {
        $this->newValue = trim($this->newValue);
        $this->validate([
            'newValue' => ['required', 'string', 'max:120',
                Rule::unique('list_options', 'value')->where('list', $this->list)],
        ], ['newValue.unique' => 'This value is already in the list.'], ['newValue' => 'value']);

        ListOption::create([
            'list'       => $this->list,
            'value'      => $this->newValue,
            'sort_order' => (int) ListOption::where('list', $this->list)->max('sort_order') + 1,
        ]);
        Audit::log('Lists', 'ADD', ListOption::LISTS[$this->list], $this->newValue);
        $this->reset('newValue');
    }

    public function remove(int $id): void
    {
        $o = ListOption::where('list', $this->list)->findOrFail($id);
        $o->delete();
        Audit::log('Lists', 'REMOVE', ListOption::LISTS[$this->list], $o->value);
        $this->dispatch('toast', message: '"'.$o->value.'" removed.', type: 'success');
    }

    public function move(int $id, int $direction): void
    {
        $items = ListOption::where('list', $this->list)->orderBy('sort_order')->orderBy('value')->get()->values();
        $i = $items->search(fn ($o) => $o->id === $id);
        $j = $i + $direction;
        if ($i === false || $j < 0 || $j >= $items->count()) {
            return;
        }
        // renumber everything, swapping the two rows
        $order = $items->pluck('id')->all();
        [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        foreach ($order as $pos => $oid) {
            ListOption::whereKey($oid)->update(['sort_order' => $pos + 1]);
        }
        \Illuminate\Support\Facades\Cache::forget('list.'.$this->list);
    }

    public function render()
    {
        return view('livewire.lists.index', [
            'lists'  => ListOption::LISTS,
            'counts' => ListOption::selectRaw('list, count(*) as c')->groupBy('list')->pluck('c', 'list'),
            'items'  => ListOption::where('list', $this->list)->orderBy('sort_order')->orderBy('value')->get(),
        ]);
    }
}
