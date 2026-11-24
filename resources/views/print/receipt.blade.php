@extends('print.layout', ['docTitle' => 'Receipt '.$p->number])

@section('meta')
    <tr><td>Receipt No</td><td><b>{{ $p->number }}</b></td></tr>
    <tr><td>Date</td><td>{{ $p->date->format('d M Y') }}</td></tr>
    @if ($p->invoice)<tr><td>Invoice</td><td><b>{{ $p->invoice->number }}</b></td></tr>@endif
    @if ($p->jobCard)<tr><td>Job Card</td><td><b>{{ $p->jobCard->number }}</b></td></tr>@endif
@endsection

@section('content')
    <div class="info">
        <div class="box">
            <h4>Received From</h4>
            <div class="big">{{ $p->customer?->name ?? $p->invoice?->customerName() ?? '—' }}</div>
            <table>
                @if ($p->customer?->phone)<tr><td>Phone</td><td>{{ $p->customer->phone }}</td></tr>@endif
                @if ($p->jobCard?->vehicle)<tr><td>Vehicle</td><td><span class="plate">{{ $p->jobCard->vehicle->reg_no }}</span></td></tr>@endif
            </table>
        </div>
        <div class="box">
            <h4>Payment</h4>
            <table>
                <tr><td>Paid by</td><td><b>{{ $p->mode }}</b></td></tr>
                @if ($p->reference)<tr><td>Reference</td><td style="font-family:Consolas,monospace">{{ $p->reference }}</td></tr>@endif
                <tr><td>For</td><td>{{ $p->invoice ? 'Invoice '.$p->invoice->number : 'Advance on job card '.$p->jobCard?->number }}</td></tr>
                @if ($p->note && $p->note !== 'Advance')<tr><td>Note</td><td>{{ $p->note }}</td></tr>@endif
            </table>
        </div>
    </div>

    <div class="bottom">
        <div>
            <div class="words" style="margin-top:0;font-size:12px"><b>Amount in words:</b> {{ \App\Support\NumberWords::shillings($p->amount) }}</div>
        </div>
        <table class="totals">
            <tr class="grand"><td>AMOUNT RECEIVED</td><td>{{ money($p->amount) }}</td></tr>
            @if ($p->invoice)
                <tr><td>Invoice total</td><td>{{ money($p->invoice->grand_total) }}</td></tr>
                <tr><td>Total paid to date</td><td>{{ money($p->invoice->paid) }}</td></tr>
                <tr class="{{ (float) $p->invoice->balance > 0 ? 'due' : 'settled' }}"><td>Balance</td><td>{{ money($p->invoice->balance) }}</td></tr>
            @endif
        </table>
    </div>

    <div class="sign">
        <div>Customer's signature</div>
        <div>Received by — {{ $p->creator?->name ?? setting('business_name') }}</div>
    </div>
@endsection
