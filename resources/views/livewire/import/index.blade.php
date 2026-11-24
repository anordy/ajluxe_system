<div class="mx-auto max-w-5xl space-y-6">
    <div class="card p-5 text-sm text-ink-600">
        <p class="font-semibold text-ink-900">Bring your data from the old Google Sheets systems</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Open the sheet (e.g. Customers) in Google Sheets → <b>File → Download → Comma-separated values (.csv)</b>.</li>
            <li>Choose what it contains below and upload the file. Columns are matched automatically — check them.</li>
            <li>Import in this order: <b>Customers → Vehicles → Suppliers → Services → Parts</b>. Existing records are skipped (or updated if you tick the box).</li>
        </ol>
    </div>

    {{-- 1. type --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title"><span class="mr-2 text-brand-500">1</span> What are you importing?</h2></div>
        <div class="card-body grid gap-2 sm:grid-cols-5">
            @foreach ($types as $k => $t)
                <button type="button" wire:click="$set('type', @js($k))"
                        class="rounded-xl border-2 px-3 py-3 text-sm font-semibold transition {{ $type === $k ? 'border-ink-900 bg-ink-50 text-ink-900' : 'border-ink-100 text-ink-500 hover:border-ink-300' }}">{{ $t['label'] }}</button>
            @endforeach
        </div>
        <div class="border-t border-ink-100 px-5 py-3 text-xs text-ink-500">
            Columns for {{ strtolower($types[$type]['label']) }}: {{ collect($fields)->map(fn ($f) => $f['label'].(! empty($f['required']) ? ' *' : ''))->implode(', ') }}.
            <a href="{{ route('import.template', $type) }}" class="ml-1 font-semibold text-brand-600 hover:underline">Download a blank template</a>
        </div>
    </div>

    {{-- 2. file --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title"><span class="mr-2 text-brand-500">2</span> Upload the CSV file</h2></div>
        <div class="card-body">
            @if ($path)
                <div class="flex items-center justify-between rounded-lg bg-ink-50 px-4 py-3 text-sm">
                    <span><b>{{ $fileName }}</b> — {{ number_format($rowCount) }} rows, {{ count($headers) }} columns</span>
                    <button type="button" class="text-xs font-semibold text-brand-600" wire:click="startOver">Choose another file</button>
                </div>
            @else
                {{-- ordinary form upload (works on every PHP setup) --}}
                <form method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data" x-data="{ sending: false }" @submit="sending = true">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-ink-200 px-6 py-10 text-center hover:border-ink-400">
                        <x-icon name="upload" class="h-6 w-6 text-ink-400" />
                        <span class="mt-2 text-sm font-semibold text-ink-700" x-text="sending ? 'Reading file…' : 'Click to choose a .csv file'">Click to choose a .csv file</span>
                        <span class="text-xs text-ink-400">Up to 10 MB</span>
                        <input type="file" name="file" accept=".csv,text/csv" class="hidden" @change="if ($event.target.files.length) { sending = true; $el.form.submit() }">
                    </label>
                </form>
            @endif
            @error('file') <p class="error">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- 3. mapping --}}
    @if ($path && ! $result)
        <div class="card">
            <div class="card-header"><h2 class="card-title"><span class="mr-2 text-brand-500">3</span> Match the columns</h2></div>
            <div class="card-body grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($fields as $key => $f)
                    <div>
                        <label class="label">{{ $f['label'] }} @if (! empty($f['required'])) <span class="text-brand-600">*</span> @endif</label>
                        <select wire:model.live="mapping.{{ $key }}" class="input {{ ($mapping[$key] ?? null) === null || ($mapping[$key] ?? '') === '' ? 'text-ink-400' : 'font-semibold' }}">
                            <option value="">— not in file —</option>
                            @foreach ($headers as $i => $h) <option value="{{ $i }}">{{ $h !== '' ? $h : 'Column '.($i + 1) }}</option> @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
            @error('mapping') <p class="error px-5 pb-3">{{ $message }}</p> @enderror

            <div class="border-t border-ink-100">
                <p class="px-5 pt-4 text-xs font-semibold uppercase tracking-wide text-ink-400">Preview — first {{ count($preview) }} rows as they will be saved</p>
                <div class="overflow-x-auto p-5 pt-2">
                    <table class="table">
                        <thead><tr>@foreach ($fields as $key => $f) @if (($mapping[$key] ?? '') !== '' && ($mapping[$key] ?? null) !== null) <th>{{ $f['label'] }}</th> @endif @endforeach</tr></thead>
                        <tbody>
                        @foreach ($preview as $row)
                            <tr>@foreach ($fields as $key => $f) @if (($mapping[$key] ?? '') !== '' && ($mapping[$key] ?? null) !== null)
                                <td class="whitespace-nowrap {{ ! empty($f['number']) ? 'text-right' : '' }}">{{ ! empty($f['number']) && $row[$key] !== null ? rtrim(rtrim(number_format($row[$key], 2), '0'), '.') : $row[$key] }}</td>
                            @endif @endforeach</tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-5 border-t border-ink-100 bg-ink-50 px-5 py-4">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="updateExisting" class="rounded border-ink-300"> Update records that already exist</label>
                @if ($type === 'vehicles')
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="createCustomers" class="rounded border-ink-300"> Create the owner if not found</label>
                @endif
                @if ($type === 'parts') <span class="text-xs text-ink-500">Stock qty becomes opening stock for new parts only.</span> @endif
                <button type="button" class="btn-primary ml-auto" wire:click="run" wire:loading.attr="disabled" wire:target="run"
                        wire:confirm="Import {{ number_format($rowCount) }} rows into {{ $types[$type]['label'] }}?">
                    <span wire:loading.remove wire:target="run">Import {{ number_format($rowCount) }} rows</span><span wire:loading wire:target="run">Importing… please wait</span>
                </button>
            </div>
        </div>
    @endif

    {{-- 4. result --}}
    @if ($result)
        <div class="card">
            <div class="card-header"><h2 class="card-title">Import finished — {{ $types[$type]['label'] }}</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-4">
                @foreach (['created' => 'Added', 'updated' => 'Updated', 'skipped' => 'Skipped (already there)'] as $k => $label)
                    <div class="rounded-lg bg-ink-50 p-4"><p class="text-xs font-semibold uppercase text-ink-400">{{ $label }}</p><p class="text-2xl font-extrabold">{{ number_format($result[$k]) }}</p></div>
                @endforeach
                <div class="rounded-lg {{ $result['errors'] ? 'bg-brand-50' : 'bg-ink-50' }} p-4"><p class="text-xs font-semibold uppercase {{ $result['errors'] ? 'text-brand-600' : 'text-ink-400' }}">Problems</p><p class="text-2xl font-extrabold">{{ count($result['errors']) }}</p></div>
            </div>
            @if ($result['errors'])
                <div class="max-h-72 overflow-y-auto border-t border-ink-100">
                    <table class="table">
                        <thead><tr><th class="w-24">Row</th><th>Problem</th></tr></thead>
                        <tbody>@foreach ($result['errors'] as $line => $msg) <tr><td class="font-mono">{{ $line }}</td><td>{{ $msg }}</td></tr> @endforeach</tbody>
                    </table>
                </div>
                <p class="hint px-5 py-3">Fix those rows in the sheet and import the file again — rows already imported will be skipped.</p>
            @endif
            <div class="flex justify-end gap-2 border-t border-ink-100 bg-ink-50 px-5 py-3">
                <button class="btn-light" wire:click="$set('result', null)">Back to columns</button>
                <button class="btn-primary" wire:click="startOver">Import another file</button>
            </div>
        </div>
    @endif
</div>
