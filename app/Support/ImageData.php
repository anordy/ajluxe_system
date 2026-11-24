<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Saves a picture chosen in a form. The browser shrinks it first, then either
 *  - uploads it straight away (PictureUploadController) and the form carries "upload:<name>", or
 *  - (fallback) the form carries the picture itself as a data URL.
 */
class ImageData
{
    public const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public const MAX_BYTES = 5 * 1024 * 1024;

    /** private folder (local disk) for pictures chosen but not saved yet */
    public const HOLDING = 'picture-uploads';

    /** @return string the stored path on the "public" disk */
    public static function store(string $value, string $folder, int $maxBytes = self::MAX_BYTES): string
    {
        if (str_starts_with($value, 'upload:')) {
            $name = substr($value, 7);
            $disk = Storage::disk('local');
            if (! preg_match('/^[A-Za-z0-9]{40}\.(jpg|png|webp)$/', $name) || ! $disk->exists(self::HOLDING.'/'.$name)) {
                throw new InvalidArgumentException('The picture has expired. Choose it again.');
            }
            $bytes = $disk->get(self::HOLDING.'/'.$name);
            $disk->delete(self::HOLDING.'/'.$name);
        } else {
            if (! preg_match('#^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=\r\n]+)$#', $value, $m)) {
                throw new InvalidArgumentException('Choose a JPG, PNG or WebP picture.');
            }
            $bytes = base64_decode($m[2], true);
            if ($bytes === false || $bytes === '') {
                throw new InvalidArgumentException('The picture could not be read. Try another one.');
            }
        }
        if (strlen($bytes) > $maxBytes) {
            throw new InvalidArgumentException('The picture is too large.');
        }
        // check the real content, not just the label
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! isset(self::TYPES[$info['mime'] ?? ''])) {
            throw new InvalidArgumentException('That file is not a JPG, PNG or WebP picture.');
        }

        $path = trim($folder, '/').'/'.Str::random(40).'.'.self::TYPES[$info['mime']];
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /**
     * A small copy (longest side 480 px) for lists and product cards, made the first time it is asked for.
     * Falls back to the full picture when GD is missing or the copy cannot be made.
     */
    public static function thumbUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        $disk = Storage::disk('public');
        $thumb = 'thumbs/'.preg_replace('/\.\w+$/', '.jpg', $path);
        if ($disk->exists($thumb)) {
            return $disk->url($thumb);
        }
        if (function_exists('imagecreatefromstring') && $disk->exists($path)) {
            $src = @imagecreatefromstring($disk->get($path));
            if ($src) {
                [$w, $h] = [imagesx($src), imagesy($src)];
                $s = min(1, 480 / max($w, $h));
                $dst = imagecreatetruecolor(max(1, (int) round($w * $s)), max(1, (int) round($h * $s)));
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
                ob_start();
                imagejpeg($dst, null, 80);
                $disk->put($thumb, ob_get_clean());
                imagedestroy($src);
                imagedestroy($dst);

                return $disk->url($thumb);
            }
        }

        return $disk->url($path);
    }
}
