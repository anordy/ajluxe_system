@extends('print.layout', ['docTitle' => $title.' '.$number])

@section('meta')
    <tr><td>{{ $proforma ? 'Proforma No' : 'Invoice No' }}</td><td><b>{{ $number }}</b></td></tr>
    <tr><td>Date</td><td>{{ $date->format('d M Y') }}</td></tr>
    @if ($job)<tr><td>Job Card</td><td><b>{{ $job->number }}</b></td></tr>@endif
    @if (! $proforma && isset($invoice))<tr><td>Status</td><td>{{ \App\Models\Invoice::STATUS_LABELS[$invoice->status] }}</td></tr>@endif
@endsection

@section('content')
    @if (isset($invoice) && $invoice->status === 'cancelled') <div class="stamp">CANCELLED</div>
    @elseif (! $proforma && $paid > 0 && $total - ($credited ?? 0) - $paid <= 0.009) <div class="stamp">PAID</div> @endif

    <div class="info">
        <div class="box">
            <h4>Bill To</h4>
            <div class="big">{{ $customerName }}</div>
            <table>
                @if ($customer?->phone)<tr><td>Phone</td><td>{{ $customer->phone }}</td></tr>@endif
                @if ($customer?->address)<tr><td>Address</td><td>{{ $customer->address }}</td></tr>@endif
                @if ($customer?->tin)<tr><td>TIN</td><td>{{ $customer->tin }}</td></tr>@endif
            </table>
        </div>
        @if ($job && $job->vehicle)
            <div class="box">
                <h4>Vehicle</h4>
                <div class="big"><span class="plate">{{ $job->vehicle->reg_no }}</span> &nbsp;{{ trim($job->vehicle->make.' '.$job->vehicle->model) }}</div>
                <table>
                    @if ($job->vehicle->year)<tr><td>Year</td><td>{{ $job->vehicle->year }}</td></tr>@endif
                    @if ($job->vehicle->vin)<tr><td>Chassis / VIN</td><td>{{ $job->vehicle->vin }}</td></tr>@endif
                    @if ($job->odometer_in)<tr><td>Odometer</td><td>{{ number_format($job->odometer_in) }} km</td></tr>@endif
                </table>
            </div>
        @endif
    </div>

    @php
        $groups = ['service' => 'Labour', 'part' => 'Spare Parts', 'other' => 'Other Charges'];
        $grouped = $lines->groupBy(fn ($l) => $l->type ?? 'all');
        $showGroups = ! $grouped->has('all') && $grouped->count() > 1;
        $n = 0;
    @endphp
    <table class="items">
        <thead><tr><th style="width:34px">#</th><th>Description</th><th class="r" style="width:80px">Qty</th><th class="r" style="width:110px">Rate</th><th class="r" style="width:120px">Amount</th></tr></thead>
        <tbody>
        @foreach ($showGroups ? collect($groups)->keys()->filter(fn ($g) => $grouped->has($g)) : [$grouped->keys()->first()] as $g)
            @if ($showGroups) <tr class="group"><td colspan="5">{{ $groups[$g] }}</td></tr> @endif
            @foreach ($showGroups ? $grouped[$g] : $lines as $line)
                <tr>
                    <td>{{ ++$n }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="r">{{ $line->qtyLabel() }}</td>
                    <td class="r">{{ amount($line->rate) }}</td>
                    <td class="r">{{ amount($line->amount) }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>

    <div class="bottom">
        <div>
            <div class="pay">
                <h4>Payment Details</h4>
                @foreach (preg_split('/\r\n|\r|\n/', (string) setting('payment_details')) as $row)
                    @if (trim($row) !== '') <div>{{ $row }}</div> @endif
                @endforeach
            </div>
            <div class="words"><b>Amount in words:</b> {{ \App\Support\NumberWords::shillings($total) }}</div>
            @if ($job && $job->complaints)
                <div class="note"><b>Work requested:</b> {{ \Illuminate\Support\Str::limit($job->complaints, 300) }}</div>
            @endif
        </div>
        <table class="totals">
            <tr><td>Subtotal</td><td>{{ money($subtotal) }}</td></tr>
            @if ($discount > 0)<tr><td>Discount{{ $subtotal > 0 ? ' ('.rtrim(rtrim(number_format($discount / $subtotal * 100, 2), '0'), '.').'%)' : '' }}</td><td>− {{ money($discount) }}</td></tr>@endif
            @if ($tax > 0)<tr><td>VAT ({{ rtrim(rtrim(number_format($taxRate, 2), '0'), '.') }}%)</td><td>{{ money($tax) }}</td></tr>@endif
            <tr class="grand"><td>GRAND TOTAL</td><td>{{ money($total) }}</td></tr>
            @php $credited = $credited ?? 0; $due = $total - $credited - $paid; @endphp
            @if ($credited > 0)<tr><td>Returns (credit notes)</td><td>− {{ money($credited) }}</td></tr>@endif
            @if ($paid != 0)
                <tr><td>{{ $proforma ? 'Advance paid' : 'Amount paid' }}</td><td>{{ money($paid) }}</td></tr>
            @endif
            @if ($paid != 0 || ! $proforma)
                <tr class="{{ $due > 0.009 ? 'due' : 'settled' }}"><td>Balance Due</td><td>{{ money(max(0, $due)) }}</td></tr>
            @endif
        </table>
    </div>

    @unless ($proforma)
        <div class="sign">
            <div>Customer's signature &amp; date</div>
            <div>Authorised signature &amp; stamp — {{ setting('business_name') }}</div>
        </div>
    @endunless
@endsection
