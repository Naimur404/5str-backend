<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class R2Storage
{
    public const DISK = 'r2';

    protected static function disk(): Cloud
    {
        /** @var Cloud $disk */
        $disk = Storage::disk(self::DISK);

        return $disk;
    }

    public static function storeUrl(UploadedFile $file, string $directory, ?string $filename = null): string
    {
        $path = $filename
            ? $file->storeAs($directory, $filename, self::DISK)
            : $file->store($directory, self::DISK);

        return self::disk()->url($path);
    }

    public static function putUrl(string $path, string $contents): string
    {
        if (! self::disk()->put($path, $contents)) {
            throw new \RuntimeException("Failed to upload to R2: {$path}");
        }

        return self::disk()->url($path);
    }

    public static function pathFromUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (! str_starts_with($value, 'http')) {
            return $value;
        }

        $path = parse_url($value, PHP_URL_PATH);

        return $path ? ltrim($path, '/') : null;
    }

    public static function urlFromValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'http')) {
            return $value;
        }

        return self::disk()->url($value);
    }

    public static function delete(?string $value): void
    {
        $path = self::pathFromUrl($value);

        if ($path && self::disk()->exists($path)) {
            self::disk()->delete($path);
        }
    }
}
