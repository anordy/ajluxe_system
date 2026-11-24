<?php

namespace App\Livewire\Import;

use App\Services\Importer;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Import Data')]
class Index extends Component
{
    public string $type = 'customers';

    /** stored copy of the uploaded CSV (local disk) */
    public ?string $path = null;
    public string $fileName = '';
    public array $headers = [];
    public int $rowCount = 0;
    /** field key => header index */
    public array $mapping = [];

    public bool $updateExisting = false;
    public bool $createCustomers = true;

    public ?array $result = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        if (isset(Importer::TYPES[request('type')])) {
            $this->type = request('type');
        }
        if ($up = session()->pull('import_upload')) {
            if (Storage::disk('local')->exists($up['path'])) {
                $this->loadUploaded($up['path'], $up['name']);
            }
        }
        if ($err = session()->pull('import_error')) {
            $this->addError('file', $err);
        }
    }

    public function updatedType(): void
    {
        $this->result = null;
        if ($this->path) {
            $this->mapping = app(Importer::class)->guessMapping($this->type, $this->headers);
        }
    }

    /** Load a CSV that ImportUploadController just stored (plain form upload, no Livewire upload route). */
    private function loadUploaded(string $path, string $name): void
    {
        $importer = app(Importer::class);
        $this->forgetFile();
        [$headers, $rows] = $importer->read(Storage::disk('local')->path($path));
        if (! $headers || ! $rows) {
            Storage::disk('local')->delete($path);
            $this->addError('file', 'The file has no data rows. The first row must be the column names.');

            return;
        }
        $this->path = $path;
        $this->fileName = $name;
        $this->headers = $headers;
        $this->rowCount = count($rows);
        $this->mapping = $importer->guessMapping($this->type, $headers);
        $this->result = null;
    }

    public function run(Importer $importer): void
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        if (! $this->path || ! Storage::disk('local')->exists($this->path)) {
            $this->addError('file', 'Upload the file again.');

            return;
        }
        foreach (Importer::TYPES[$this->type]['fields'] as $key => $f) {
            if (! empty($f['required']) && ($this->mapping[$key] ?? '') === '' ) {
                $this->addError('mapping', "Choose which column holds “{$f['label']}”.");

                return;
            }
        }

        @set_time_limit(300);
        [, $rows] = $importer->read(Storage::disk('local')->path($this->path));
        $this->result = $importer->import($this->type, $rows, $this->mapping, $this->updateExisting, $this->createCustomers);
        $this->result['errors'] = array_slice($this->result['errors'], 0, 100, true);
        $this->dispatch('toast', message: "Import finished: {$this->result['created']} added, {$this->result['updated']} updated, {$this->result['skipped']} skipped.", type: 'success');
    }

    public function startOver(): void
    {
        $this->forgetFile();
        $this->result = null;
    }

    private function forgetFile(): void
    {
        if ($this->path) {
            Storage::disk('local')->delete($this->path);
        }
        $this->reset(['path', 'fileName', 'headers', 'rowCount', 'mapping']);
    }

    public function render()
    {
        $importer = app(Importer::class);
        $preview = [];
        if ($this->path && Storage::disk('local')->exists($this->path)) {
            [, $rows] = $importer->read(Storage::disk('local')->path($this->path), 5);
            $preview = array_map(fn ($r) => $importer->mapRow($this->type, $r, $this->mapping), $rows);
        }

        return view('livewire.import.index', [
            'types'   => Importer::TYPES,
            'fields'  => Importer::TYPES[$this->type]['fields'],
            'preview' => $preview,
        ]);
    }
}
