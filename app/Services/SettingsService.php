<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\R2Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SettingsService
{
    /**
     * Registry of known settings with their type and default value.
     * Types: string, boolean, integer, file_url
     */
    protected array $registry = [
        'admin_logo' => ['type' => 'file_url', 'default' => null],
        'admin_title' => ['type' => 'string', 'default' => '5SRT Admin'],
        'ai_enabled' => ['type' => 'boolean', 'default' => false],
        'ai_api_token' => ['type' => 'string', 'default' => null, 'sensitive' => true],
    ];

    /** Keys that are encrypted at rest. */
    protected array $sensitiveKeys = ['ai_api_token'];

    /** Cache TTL in seconds (60 minutes). */
    protected int $cacheTtl = 3600;

    /**
     * Get a single setting value, cast to its declared type.
     */
    public function get(string $key): mixed
    {
        if (!isset($this->registry[$key])) {
            return null;
        }

        $cacheKey = $this->cacheKey($key);

        $raw = Cache::remember($cacheKey, $this->cacheTtl, function () use ($key) {
            $record = Setting::where('key', $key)->first();
            // Use a sentinel to distinguish "no row" from "null value"
            return $record ? $record->value : '__NULL_SENTINEL__';
        });

        if ($raw === '__NULL_SENTINEL__') {
            return $this->registry[$key]['default'];
        }

        // Decrypt sensitive values
        if (in_array($key, $this->sensitiveKeys) && $raw !== null) {
            try {
                $raw = Crypt::decryptString($raw);
            } catch (\Exception $e) {
                // If decryption fails (e.g. key rotation), return null
                return null;
            }
        }

        return $this->cast($key, $raw);
    }

    /**
     * Get all settings as an associative array.
     */
    public function all(): array
    {
        $result = [];
        foreach (array_keys($this->registry) as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    /**
     * Get only public (non-sensitive) settings.
     */
    public function publicSettings(): array
    {
        return [
            'admin_logo' => $this->get('admin_logo'),
            'admin_title' => $this->get('admin_title'),
        ];
    }

    /**
     * Get full settings for authenticated admin, including derived fields.
     */
    public function authenticatedSettings(): array
    {
        $all = $this->all();
        $all['ai_configured'] = $all['ai_enabled'] && !empty($all['ai_api_token']);
        return $all;
    }

    /**
     * Set one or more settings. Accepts an associative array.
     */
    public function set(array $data): void
    {
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                if (!isset($this->registry[$key])) {
                    continue;
                }

                $storeValue = $value;

                // Encrypt sensitive values
                if (in_array($key, $this->sensitiveKeys) && $storeValue !== null && $storeValue !== '') {
                    $storeValue = Crypt::encryptString($storeValue);
                }

                // Empty string for sensitive keys means clear
                if (in_array($key, $this->sensitiveKeys) && $value === '') {
                    $storeValue = null;
                }

                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $storeValue]
                );

                Cache::forget($this->cacheKey($key));
            }
        });
    }

    /**
     * Upload and store the admin logo on R2.
     */
    public function uploadLogo(UploadedFile $file): string
    {
        // Delete old logo if exists
        $oldLogo = $this->get('admin_logo');
        if ($oldLogo) {
            R2Storage::delete($oldLogo);
        }

        $url = R2Storage::storeUrl($file, 'admin/branding');

        $this->set(['admin_logo' => $url]);

        return $url;
    }

    /**
     * Get the list of registered keys.
     */
    public function registeredKeys(): array
    {
        return array_keys($this->registry);
    }

    /**
     * Check if a key is sensitive.
     */
    public function isSensitive(string $key): bool
    {
        return in_array($key, $this->sensitiveKeys);
    }

    /**
     * Cast a raw value to the declared type.
     */
    protected function cast(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return $this->registry[$key]['default'];
        }

        return match ($this->registry[$key]['type']) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'file_url' => $value ? R2Storage::urlFromValue($value) : null,
            default => (string) $value,
        };
    }

    protected function cacheKey(string $key): string
    {
        return "settings:{$key}";
    }
}
