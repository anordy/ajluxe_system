<?php

namespace App\Http\Controllers;

use App\Services\Importer;
use App\Services\Reports;
use App\Support\Audit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    /** CSV download of a report (opens in Excel / Google Sheets). */
    public function __invoke(Request $request, string $report, Reports $reports): StreamedResponse
    {
        abort_unless(isset(Reports::REPORTS[$report]), 404);
        $from = $request->date('from')?->toDateString() ?? today()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? today()->toDateString();

        $rows = $reports->csv($report, $from, $to);
        $name = 'ajluxe-'.$report.(in_array($report, Reports::DATED, true) ? "-{$from}-to-{$to}" : '-'.today()->toDateString()).'.csv';
        Audit::log('Reports', 'EXPORT', $report, "{$from} to {$to}");

        return $this->stream($rows, $name);
    }

    /** Blank CSV with the expected columns, for the importer. */
    public function template(string $type): StreamedResponse
    {
        abort_unless(isset(Importer::TYPES[$type]), 404);
        $fields = Importer::TYPES[$type]['fields'];

        return $this->stream([array_values(array_map(fn ($f) => $f['label'], $fields)), array_values(array_map(fn ($f) => $f['example'] ?? '', $fields))],
            "ajluxe-import-{$type}.csv");
    }

    private function stream(array $rows, string $name): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows TZS names correctly
            foreach ($rows as $row) {
                // neutralise spreadsheet formulas in text cells
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
