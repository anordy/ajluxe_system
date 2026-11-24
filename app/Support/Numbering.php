<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Issues document numbers safely, even when two users save at the same moment.
 *   Numbering::next('job_card')  ->  "JC-2026-0001"
 * The prefix comes from Settings (prefix_job_card, prefix_invoice, ...).
 * Numbers restart at 0001 every year.
 */
class Numbering
{
    public static function next(string $key, int $pad = 4): string
    {
        $year = (int) now()->format('Y');
        $prefix = Settings::get('prefix_'.$key, strtoupper($key));

        $number = DB::transaction(function () use ($key, $year) {
            DB::table('sequences')->insertOrIgnore(['key' => $key, 'year' => $year, 'last_number' => 0]);

            $row = DB::table('sequences')->where(['key' => $key, 'year' => $year])->lockForUpdate()->first();
            $next = $row->last_number + 1;
            DB::table('sequences')->where(['key' => $key, 'year' => $year])->update(['last_number' => $next]);

            return $next;
        });

        return $prefix.'-'.$year.'-'.str_pad((string) $number, $pad, '0', STR_PAD_LEFT);
    }

    /** Simple running codes for masters: CUS-0001, SUP-0001 (no year). */
    public static function code(string $key, string $prefix, int $pad = 4): string
    {
        $number = DB::transaction(function () use ($key) {
            DB::table('sequences')->insertOrIgnore(['key' => $key, 'year' => 0, 'last_number' => 0]);
            $row = DB::table('sequences')->where(['key' => $key, 'year' => 0])->lockForUpdate()->first();
            $next = $row->last_number + 1;
            DB::table('sequences')->where(['key' => $key, 'year' => 0])->update(['last_number' => $next]);

            return $next;
        });

        return $prefix.'-'.str_pad((string) $number, $pad, '0', STR_PAD_LEFT);
    }
}
