@extends('print.layout', ['docTitle' => $title.' '.$r->number])

@section('meta')
    <tr><td>Note No</td><td><b>{{ $r->number }}</b></td></tr>
    <tr><td>Date</td><td>{{ $r->date->format('d M Y') }}</td></tr>
    <tr><td>{{ $r->isCustomer() ? 'Invoice' : 'PO' }}</td><td><b>{{ $r->invoice?->number ?? $r->purchaseOrder?->number }}</b></td></tr>
@endsection

@section('content')
    <div class="info">
        <div class="box">
            <h4>{{ $r->isCustomer() ? 'Customer' : 'Supplier' }}</h4>
            <div class="big">{{ $r->partyName() }}</div>
            @php $party = $r->isCustomer() ? $r->customer : $r->supplier; @endphp
            <table>@if ($party?->phone)<tr><td>Phone</td><td>{{ $party->phone }}</td></tr>@endif</table>
        </div>
        <div class="box">
            <h4>Reason</h4>
            <div>{{ $r->reason }}</div>
        </div>
    </div>

    <table class="items">
        <thead><tr><th style="width:34px">#</th><th>Part returned</th><th class="r" style="width:90px">Qty</th><th class="r" style="width:120px">Price</th><th class="r" style="width:120px">Amount</th></tr></thead>
        <tbody>
        @foreach ($r->items as $n => $i)
            <tr><td>{{ $n + 1 }}</td><td>{{ $i->part->name }}</td><td class="r">{{ rtrim(rtrim(number_format($i->qty, 2), '0'), '.') }} {{ $i->part->unit }}</td><td class="r">{{ amount($i->rate) }}</td><td class="r">{{ amount($i->amount) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <div class="bottom">
        <div>
            <div class="words"><b>Amount in words:</b> {{ \App\Support\NumberWords::shillings($r->total) }}</div>
            <div class="note">
                @if ($r->isCustomer()) This amount has been credited to the customer's invoice.
                @else This amount has been deducted from the amount owed to the supplier. @endif
            </div>
        </div>
        <table class="totals">
            <tr class="grand"><td>{{ $r->isCustomer() ? 'TOTAL CREDIT' : 'TOTAL DEBIT' }}</td><td>{{ money($r->total) }}</td></tr>
            @if ((float) $r->refund_amount)
                <tr class="settled"><td>{{ $r->isCustomer() ? 'Refunded' : 'Refund due from supplier' }} ({{ $r->refund_mode }})</td><td>{{ money($r->refund_amount) }}</td></tr>
            @endif
        </table>
    </div>

    <div class="sign">
        <div>{{ $r->isCustomer() ? "Customer's signature" : "Supplier's signature" }}</div>
        <div>Authorised — {{ setting('business_name') }} ({{ $r->creator?->name }})</div>
    </div>
@endsection
