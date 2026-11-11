<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Storage;
use Modules\Dashboard\Models\Media;
use Throwable;

/**
 * A small copy of a picture for Jocko Control: the original never leaves this
 * machine. Returns null for anything that cannot be turned into one (the file
 * is gone, is not a picture, or is too big to decode with the memory available);
 * such a picture simply has no preview there.
 */
#[Singleton]
class MediaPreview
{
    private const SOURCES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * @return array{bytes: string, mime: string, sha256: string}|null
     */
    public function make(Media $media): ?array
    {
        try {
            $source = Storage::disk($media->disk)->get($media->path);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($source) || $source === '' || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $info = @getimagesizefromstring($source);

        if ($info === false || ! in_array($info['mime'], self::SOURCES, true) || ! $this->fitsInMemory($info[0], $info[1])) {
            return null;
        }

        $image = @imagecreatefromstring($source);

        if ($image === false) {
            return null;
        }

        try {
            $image = $this->upright($image, $info['mime'], $source);
            $image = $this->shrink($image);

            $transparent = $info['mime'] !== 'image/jpeg';
            $encoded = $this->encode($image, $transparent);
        } finally {
            unset($image);
        }

        if ($encoded === null || strlen($encoded) > (int) config('control.files.max_bytes')) {
            return null;
        }

        return ['bytes' => $encoded, 'mime' => $transparent ? 'image/png' : 'image/jpeg', 'sha256' => hash('sha256', $encoded)];
    }

    /**
     * The picture itself, for a Jocko Control that keeps originals. Null when it
     * cannot be sent as it is (gone, not a type Jocko Control accepts, too big),
     * so the caller falls back to a preview.
     *
     * @return array{bytes: string, mime: string, sha256: string}|null
     */
    public function original(Media $media): ?array
    {
        try {
            $bytes = Storage::disk($media->disk)->get($media->path);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > (int) config('control.files.max_original_bytes')) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        return ['bytes' => $bytes, 'mime' => $info['mime'], 'sha256' => hash('sha256', $bytes)];
    }

    /**
     * Decoding takes about four bytes per pixel, plus a copy while scaling.
     */
    private function fitsInMemory(int $width, int $height): bool
    {
        $limit = $this->memoryLimit();

        return $limit === null || $width * $height * 9 + memory_get_usage() < $limit;
    }

    private function memoryLimit(): ?int
    {
        $value = trim((string) ini_get('memory_limit'));

        if ($value === '' || $value === '-1') {
            return null;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Phones store a photo sideways and say so in EXIF; browsers obey it for the
     * original, but a re-encoded copy has no EXIF, so apply it now.
     */
    private function upright(\GdImage $image, string $mime, string $source): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $stream = fopen('php://memory', 'r+');

        if ($stream === false) {
            return $image;
        }

        fwrite($stream, $source);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }

    private function shrink(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);
        $max = (int) config('control.files.max_dimension');

        if ($longest <= $max) {
            return $image;
        }

        $scaled = imagescale($image, (int) max(1, round($width * $max / $longest)), (int) max(1, round($height * $max / $longest)), IMG_BICUBIC);

        return $scaled === false ? $image : $scaled;
    }

    private function encode(\GdImage $image, bool $transparent): ?string
    {
        ob_start();

        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $done = imagepng($image, null, 7);
        } else {
            $done = imagejpeg($image, null, (int) config('control.files.quality'));
        }

        $bytes = ob_get_clean();

        return $done && is_string($bytes) && $bytes !== '' ? $bytes : null;
    }
}
