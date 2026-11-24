<?php

namespace App\Http\Controllers;

use App\Support\ImageData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Receives a picture the browser has already shrunk, as the raw request body (not a form upload,
 * so PHP's upload_max_filesize and temp-folder settings do not matter). It is kept in a private
 * holding folder; the form only carries the short "upload:…" reference until Save moves it.
 */
class PictureUploadController extends Controller
{
    public function __invoke(Request $request)
    {
        $bytes = $request->getContent();
        if ($bytes === '' || strlen($bytes) > ImageData::MAX_BYTES) {
            return response()->json(['message' => 'The picture is too large.'], 422);
        }
        $info = @getimagesizefromstring($bytes);
        $ext = ImageData::TYPES[$info['mime'] ?? ''] ?? null;
        if (! $ext) {
            return response()->json(['message' => 'That file is not a JPG, PNG or WebP picture.'], 422);
        }

        $disk = Storage::disk('local');
        // tidy pictures that were chosen but never saved
        foreach ($disk->files(ImageData::HOLDING) as $old) {
            if ($disk->lastModified($old) < now()->subDay()->getTimestamp()) {
                $disk->delete($old);
            }
        }
        $name = Str::random(40).'.'.$ext;
        $disk->put(ImageData::HOLDING.'/'.$name, $bytes);

        return response()->json(['token' => 'upload:'.$name]);
    }
}
