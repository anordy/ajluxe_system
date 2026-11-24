<?php

namespace App\Http\Controllers;

use App\Services\Importer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Receives the CSV for the import screen as an ordinary form upload
 * (works on any PHP setup — no Livewire temporary-upload route, no fileinfo extension needed).
 */
class ImportUploadController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        $type = isset(Importer::TYPES[$request->input('type')]) ? $request->input('type') : 'customers';
        $back = redirect()->route('import.index', ['type' => $type]);

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            $max = ini_get('upload_max_filesize');

            return $back->with('import_error', "The file did not arrive. If it is bigger than {$max}, raise upload_max_filesize and post_max_size in php.ini.");
        }
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt'], true)) {
            return $back->with('import_error', 'Upload a .csv file (in Google Sheets: File → Download → Comma-separated values).');
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $back->with('import_error', 'The file is larger than 10 MB. Split it into smaller files.');
        }

        $path = $file->storeAs('imports', Str::random(32).'.csv', 'local');

        return $back->with('import_upload', ['path' => $path, 'name' => $file->getClientOriginalName()]);
    }
}
