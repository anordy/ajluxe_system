<?php

namespace App\Livewire\Shop;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\OrderManager;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.shop')]
class OrderShow extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        // customers only ever see their own orders
        abort_unless($order->shop_customer_id === auth('shop')->id(), 404);
        $this->order = $order;
    }

    public function cancel(OrderManager $orders): void
    {
        try {
            $orders->cancel($this->order, 'Cancelled by the customer on the website', byCustomer: true);
            $this->dispatch('toast', message: __('Your order was cancelled.'), type: 'success');
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
        $this->order->refresh();
    }

    public function render()
    {
        $this->order->load('items.part');

        return view('livewire.shop.order', ['o' => $this->order, 'placed' => session('placed')])->title('Order '.$this->order->number);
    }
}
