<?php

namespace App\Livewire\Concerns;

/**
 * Shared behaviour for the master-data list screens:
 *  - who may add / edit / delete (everyone else on the page can only view)
 *  - a consistent "deleted / deactivated" toast
 * The using component sets:  protected string $managePermission = 'suppliers';   (see App\Support\Permissions)
 */
trait ManagesRecords
{
    public function canManage(): bool
    {
        return (bool) auth()->user()?->allows($this->managePermission);
    }

    protected function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403, 'You can view this list but not change it.');
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }
}
