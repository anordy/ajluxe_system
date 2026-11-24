<?php

namespace App\Livewire\Shop;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.shop')]
class Account extends Component
{
    use WithPagination;

    public function render()
    {
        $me = auth('shop')->user();

        return view('livewire.shop.account', [
            'me'     => $me,
            'orders' => $me->orders()->withCount('items')->paginate(10),
            'imports' => \App\Models\ImportRequest::where('shop_customer_id', $me->id)
                ->orWhere(fn ($q) => $q->whereNull('shop_customer_id')->where('phone', $me->phone)->whereNotNull('phone'))
                ->latest()->limit(20)->get(),
        ])->title(__('My orders'));
    }
}
