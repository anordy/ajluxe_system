@extends('print.layout', ['docTitle' => 'Job Card '.$job->number])

@section('meta')
    <tr><td>Job Card No</td><td><b>{{ $job->number }}</b></td></tr>
    <tr><td>Received</td><td>{{ $job->received_at->format('d M Y, H:i') }}</td></tr>
    @if ($job->promised_at)<tr><td>Promised</td><td>{{ $job->promised_at->format('d M Y, H:i') }}</td></tr>@endif
    <tr><td>Status</td><td>{{ $job->status }}</td></tr>
@endsection

@section('content')
    <div class="info three">
        <div class="box">
            <h4>Customer</h4>
            <div class="big">{{ $job->customer->name }}</div>
            <table><tr><td>Phone</td><td>{{ $job->customer->phone ?: '—' }}</td></tr></table>
        </div>
        <div class="box">
            <h4>Vehicle</h4>
            <div class="big"><span class="plate">{{ $job->vehicle->reg_no }}</span></div>
            <table>
                <tr><td>Make / model</td><td>{{ trim($job->vehicle->make.' '.$job->vehicle->model) ?: '—' }}</td></tr>
                @if ($job->vehicle->vin)<tr><td>VIN</td><td>{{ $job->vehicle->vin }}</td></tr>@endif
            </table>
        </div>
        <div class="box">
            <h4>Check-in</h4>
            <table>
                <tr><td>Odometer</td><td>{{ $job->odometer_in ? number_format($job->odometer_in).' km' : '—' }}</td></tr>
                <tr><td>Fuel</td><td>{{ $job->fuel_level ?: '—' }}</td></tr>
                <tr><td>Mechanic</td><td>{{ $job->mechanic?->name ?: '—' }}</td></tr>
                <tr><td>Priority</td><td>{{ $job->priority }}</td></tr>
            </table>
        </div>
    </div>

    <div class="info">
        <div class="box"><h4>Customer complaint / work requested</h4><div style="white-space:pre-line">{{ $job->complaints ?: '—' }}</div></div>
        <div class="box"><h4>Inspection notes</h4><div style="white-space:pre-line">{{ $job->inspection_notes ?: '—' }}</div></div>
    </div>

    <table class="items">
        <thead><tr><th style="width:34px">#</th><th style="width:90px">Type</th><th>Work / parts</th><th class="r" style="width:90px">Qty</th><th class="c" style="width:70px">Done</th></tr></thead>
        <tbody>
        @forelse ($job->items as $i => $item)
            <tr><td>{{ $i + 1 }}</td><td class="muted">{{ \App\Models\JobCardItem::TYPES[$item->type] }}</td><td>{{ $item->description }}</td><td class="r">{{ $item->qtyLabel() }}</td><td class="c">☐</td></tr>
        @empty
            @for ($i = 1; $i <= 6; $i++) <tr><td>{{ $i }}</td><td></td><td>&nbsp;</td><td></td><td class="c">☐</td></tr> @endfor
        @endforelse
        </tbody>
    </table>

    <div class="box" style="min-height:90px"><h4>Mechanic's work notes</h4><div style="white-space:pre-line">{{ $job->work_notes }}</div></div>

    <div class="sign" style="grid-template-columns:1fr 1fr 1fr; gap:28px">
        <div>Received by (customer)</div>
        <div>Mechanic</div>
        <div>Quality check / supervisor</div>
    </div>
@endsection
