<?php

namespace App\Livewire;

use App\Models\AuditLog;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Audit Log')]
class AuditLogIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $module = '';

    public function updating($field): void
    {
        if (in_array($field, ['search', 'module'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $logs = AuditLog::with('user:id,name,username')
            ->when($this->module, fn ($q) => $q->where('module', $this->module))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('record', 'like', "%{$this->search}%")
                ->orWhere('details', 'like', "%{$this->search}%")
                ->orWhere('action', 'like', "%{$this->search}%")))
            ->latest('id')
            ->paginate(25);

        return view('livewire.audit-log-index', [
            'logs'    => $logs,
            'modules' => AuditLog::distinct()->orderBy('module')->pluck('module'),
        ]);
    }
}
