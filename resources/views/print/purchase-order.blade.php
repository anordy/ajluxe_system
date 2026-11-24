@extends('print.layout', ['docTitle' => 'Purchase Order '.$po->number])

@section('meta')
    <tr><td>PO No</td><td><b>{{ $po->number }}</b></td></tr>
    <tr><td>Date</td><td>{{ $po->date->format('d M Y') }}</td></tr>
    @if ($po->expected_date)<tr><td>Delivery by</td><td>{{ $po->expected_date->format('d M Y') }}</td></tr>@endif
@endsection

@section('content')
    @if ($po->status === 'cancelled') <div class="stamp">CANCELLED</div> @endif
    <div class="info">
        <div class="box">
            <h4>Supplier</h4>
            <div class="big">{{ $po->supplier->name }}</div>
            <table>
                @if ($po->supplier->contact_person)<tr><td>Contact</td><td>{{ $po->supplier->contact_person }}</td></tr>@endif
                @if ($po->supplier->phone)<tr><td>Phone</td><td>{{ $po->supplier->phone }}</td></tr>@endif
                @if ($po->supplier->address)<tr><td>Address</td><td>{{ $po->supplier->address }}</td></tr>@endif
                @if ($po->supplier->tin)<tr><td>TIN</td><td>{{ $po->supplier->tin }}</td></tr>@endif
            </table>
        </div>
        <div class="box">
            <h4>Deliver To</h4>
            <div class="big">{{ setting('business_name') }}</div>
            <table>
                @if (setting('address'))<tr><td>Address</td><td>{{ setting('address') }}</td></tr>@endif
                @if (setting('phone'))<tr><td>Phone</td><td>{{ setting('phone') }}</td></tr>@endif
            </table>
        </div>
    </div>

    <table class="items">
        <thead><tr><th style="width:34px">#</th><th>Part</th><th style="width:120px">Part no</th><th class="r" style="width:80px">Qty</th><th class="r" style="width:110px">Unit price</th><th class="r" style="width:120px">Amount</th></tr></thead>
        <tbody>
        @foreach ($po->items as $n => $i)
            <tr><td>{{ $n + 1 }}</td><td>{{ $i->part->name }}{{ $i->part->brand ? ' ('.$i->part->brand.')' : '' }}</td><td>{{ $i->part->oem_no ?: $i->part->sku }}</td>
                <td class="r">{{ rtrim(rtrim(number_format($i->qty, 2), '0'), '.') }} {{ $i->part->unit }}</td><td class="r">{{ amount($i->rate) }}</td><td class="r">{{ amount($i->amount) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <div class="bottom">
        <div>
            <div class="words"><b>Amount in words:</b> {{ \App\Support\NumberWords::shillings($po->grand_total) }}</div>
            @if ($po->notes) <div class="note"><b>Notes:</b> {{ $po->notes }}</div> @endif
        </div>
        <table class="totals">
            <tr><td>Subtotal</td><td>{{ money($po->subtotal) }}</td></tr>
            @if ((float) $po->tax_amount)<tr><td>VAT</td><td>{{ money($po->tax_amount) }}</td></tr>@endif
            @if ((float) $po->other_charges)<tr><td>Transport / other</td><td>{{ money($po->other_charges) }}</td></tr>@endif
            <tr class="grand"><td>TOTAL</td><td>{{ money($po->grand_total) }}</td></tr>
        </table>
    </div>

    <div class="sign">
        <div>Prepared by</div>
        <div>Authorised signature &amp; stamp — {{ setting('business_name') }}</div>
    </div>
@endsection
