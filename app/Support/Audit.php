<?php

namespace App\Support;

use App\Models\AuditLog;

/**
 * Writes one line to the audit log.  Audit::log('Users', 'CREATE', 'manager1', 'Role: manager');
 */
class Audit
{
    public static function log(string $module, string $action, ?string $record = null, ?string $details = null): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'module'  => $module,
                'action'  => $action,
                'record'  => $record,
                'details' => $details,
                'ip'      => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e); // never let logging break the main action
        }
    }
}
