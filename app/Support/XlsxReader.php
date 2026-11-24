<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Small .xlsx reader (no extra packages): every sheet → list of rows keyed by the header row (lower case, no spaces).
 * Also reads a folder of CSV files, one per sheet ("AJ_AUTO_PARTS_DB - Parts.csv" → sheet "Parts").
 * Columns whose name says date / time / timestamp / delivery / expiry, or ends in "at" / "on", are turned from Excel day numbers into "Y-m-d H:i:s".
 */
class XlsxReader
{
    /** @return array<string, array<int, array<string, mixed>>> sheet name => rows */
    public static function read(string $path): array
    {
        if (is_dir($path)) {
            return self::readCsvFolder($path);
        }
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv') {
            return [self::sheetName(basename($path, '.csv')) => self::csvRows($path)];
        }
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP zip extension is off. Turn on "extension=zip" in php.ini, or export each sheet as CSV into one folder and give the folder instead.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Cannot open {$path} as an Excel file.");
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = simplexml_load_string($xml);
            foreach ($sx->si as $si) {
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }
        $wb = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $targets = [];
        foreach ($rels->Relationship as $r) {
            $targets[(string) $r['Id']] = ltrim(preg_replace('#^/?xl/#', '', (string) $r['Target']), '/');
        }

        $out = [];
        foreach ($wb->sheets->sheet as $sheet) {
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $xml = $zip->getFromName('xl/'.($targets[$rid] ?? ''));
            if ($xml === false) {
                continue;
            }
            $sx = simplexml_load_string($xml);
            $grid = [];
            foreach ($sx->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $c) {
                    $col = self::colIndex(preg_replace('/\d+/', '', (string) $c['r']));
                    $t = (string) $c['t'];
                    $v = match ($t) {
                        's'         => $shared[(int) $c->v] ?? '',
                        'inlineStr' => (string) $c->is->t,
                        'b'         => (string) $c->v === '1',
                        default     => isset($c->v) ? (string) $c->v : '',
                    };
                    $cells[$col] = $v;
                }
                $grid[] = $cells;
            }
            $out[(string) $sheet['name']] = self::keyed($grid);
        }
        $zip->close();

        return $out;
    }

    private static function keyed(array $grid): array
    {
        if (! $grid) {
            return [];
        }
        $head = [];
        foreach (array_shift($grid) as $i => $h) {
            $head[$i] = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $h));
        }
        $rows = [];
        foreach ($grid as $cells) {
            $r = [];
            foreach ($head as $i => $k) {
                if ($k === '') {
                    continue;
                }
                $v = $cells[$i] ?? '';
                if ($v !== '' && is_numeric($v) && (preg_match('/date|time|stamp|delivery|expiry/', $k) || preg_match('/(at|on)$/', $k)) && (float) $v > 20000 && (float) $v < 80000) {
                    $v = gmdate('Y-m-d H:i:s', (int) round(((float) $v - 25569) * 86400));
                }
                $r[$k] = is_string($v) ? trim($v) : $v;
            }
            if (array_filter($r, fn ($v) => $v !== '' && $v !== null)) {
                $rows[] = $r;
            }
        }

        return $rows;
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    private static function readCsvFolder(string $dir): array
    {
        $out = [];
        foreach (glob(rtrim($dir, '/\\').'/*.csv') as $f) {
            $out[self::sheetName(basename($f, '.csv'))] = self::csvRows($f);
        }

        return $out;
    }

    /** "AJ_AUTO_PARTS_DB - Purchase_Items" → "Purchase_Items" */
    private static function sheetName(string $file): string
    {
        return trim(str_contains($file, ' - ') ? substr($file, strrpos($file, ' - ') + 3) : $file);
    }

    private static function csvRows(string $file): array
    {
        $h = fopen($file, 'r');
        $grid = [];
        while (($r = fgetcsv($h)) !== false) {
            $grid[] = $r;
        }
        fclose($h);
        if ($grid) {
            $grid[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $grid[0][0]);
        }
        // CSV dates are already text ("2026-11-14 1:34:53")
        return self::keyed($grid);
    }
}
