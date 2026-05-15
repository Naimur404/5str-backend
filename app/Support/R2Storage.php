<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class R2Storage
{
    /** R2 disk identifier. Used only by R2-specific migration commands. */
    public const DISK = 'r2';

    /** Currently-active default filesystem disk name (dynamic via FILESYSTEM_DISK). */
    public static function active(): string
    {
        return (string) config('filesystems.default') ?: self::DISK;
    }

    /** Storage instance for the currently-active default disk. */
    public static function storage(): Cloud
    {
        /** @var Cloud $disk */
        $disk = Storage::disk(self::active());

        return $disk;
    }

    public static function storeUrl(UploadedFile $file, string $directory, ?string $filename = null): string
    {
        $disk = self::active();
        $path = $filename
            ? $file->storeAs($directory, $filename, $disk)
            : $file->store($directory, $disk);

        return self::storage()->url($path);
    }

    public static function putUrl(string $path, string $contents): string
    {
        if (! self::storage()->put($path, $contents)) {
            throw new \RuntimeException("Failed to upload to disk [".self::active()."]: {$path}");
        }

        return self::storage()->url($path);
    }

    public static function pathFromUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (! str_starts_with($value, 'http')) {
            return $value;
        }

        // Strip the active disk's configured base URL (e.g. https://api.5str.xyz
        // for r2, or http://127.0.0.1:8000/storage for public).
        $base = rtrim((string) config('filesystems.disks.' . self::active() . '.url'), '/');
        if ($base !== '' && str_starts_with($value, $base . '/')) {
            return substr($value, strlen($base) + 1);
        }

        // Fallback for legacy URLs whose base no longer matches the active disk.
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

        return self::storage()->url($value);
    }

    public static function delete(?string $value): void
    {
        $path = self::pathFromUrl($value);

        if ($path && self::storage()->exists($path)) {
            self::storage()->delete($path);
        }
    }
}
