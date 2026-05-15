<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class R2RewriteImageUrls extends Command
{
    protected $signature = 'r2:rewrite-image-urls
                            {--dry-run : Show what would change without writing}
                            {--only= : Comma-separated list of tables to limit the run to}';

    protected $description = 'One-shot: rewrite legacy paths / /storage URLs in image columns to full R2 URLs. Skips external URLs and rows already pointing at R2.';

    /**
     * Each entry: table => [
     *   ['column' => string, 'type' => 'string'|'json', 'key' => string (primary key, default id)]
     * ]
     */
    protected array $columns = [
        'users' => [
            ['column' => 'profile_image', 'type' => 'string'],
            // 'avatar' intentionally excluded — typically Google OAuth URLs
        ],
        'categories' => [
            ['column' => 'icon_image', 'type' => 'string'],
            ['column' => 'banner_image', 'type' => 'string'],
        ],
        'business_images' => [
            ['column' => 'image_url', 'type' => 'string'],
        ],
        'business_offerings' => [
            ['column' => 'image_url', 'type' => 'string'],
        ],
        'review_images' => [
            ['column' => 'image_url', 'type' => 'string'],
        ],
        'offers' => [
            ['column' => 'banner_image', 'type' => 'string'],
        ],
        'banners' => [
            ['column' => 'image_url', 'type' => 'string'],
        ],
        'user_collections' => [
            ['column' => 'cover_image', 'type' => 'string'],
        ],
        'attraction_galleries' => [
            ['column' => 'image_path', 'type' => 'string'],
            ['column' => 'image_url', 'type' => 'string'],
        ],
        'attraction_review_images' => [
            ['column' => 'image_path', 'type' => 'string'],
            ['column' => 'image_url', 'type' => 'string'],
        ],
        'business_submissions' => [
            ['column' => 'images', 'type' => 'json'],
        ],
        'attraction_submissions' => [
            ['column' => 'images', 'type' => 'json'],
        ],
        'offering_submissions' => [
            ['column' => 'images', 'type' => 'json'],
        ],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->option('only')
            ? array_map('trim', explode(',', (string) $this->option('only')))
            : null;

        $r2Url = rtrim((string) config('filesystems.disks.r2.url'), '/');
        if (! $r2Url) {
            $this->error('filesystems.disks.r2.url is empty. Set R2_URL in your env first.');
            return self::FAILURE;
        }

        $this->info('R2 base URL: ' . $r2Url);
        $this->info('Mode:        ' . ($dryRun ? 'DRY-RUN' : 'WRITE'));
        $this->newLine();

        $grandUpdated = 0;
        $grandUnchanged = 0;
        $grandSkippedExternal = 0;

        foreach ($this->columns as $table => $cols) {
            if ($only && ! in_array($table, $only, true)) {
                continue;
            }

            if (! Schema::hasTable($table)) {
                $this->warn("Skip: table {$table} not found");
                continue;
            }

            $this->line("<fg=cyan>== {$table} ==</>");

            foreach ($cols as $col) {
                $column = $col['column'];
                $type = $col['type'];

                if (! Schema::hasColumn($table, $column)) {
                    $this->warn("  Skip: column {$table}.{$column} not found");
                    continue;
                }

                $updated = 0;
                $unchanged = 0;
                $external = 0;

                DB::table($table)
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->orderBy('id')
                    ->chunkById(500, function ($rows) use ($table, $column, $type, $r2Url, $dryRun, &$updated, &$unchanged, &$external) {
                        foreach ($rows as $row) {
                            $current = $row->{$column};

                            if ($type === 'json') {
                                $decoded = json_decode((string) $current, true);
                                if (! is_array($decoded)) {
                                    $unchanged++;
                                    continue;
                                }

                                $changedAny = false;
                                $newArr = array_map(function ($item) use ($r2Url, &$changedAny, &$external) {
                                    if (! is_string($item)) {
                                        return $item;
                                    }
                                    $rewritten = $this->normalize($item, $r2Url);
                                    if ($rewritten === null) {
                                        $external++;
                                        return $item;
                                    }
                                    if ($rewritten !== $item) {
                                        $changedAny = true;
                                    }
                                    return $rewritten;
                                }, $decoded);

                                if (! $changedAny) {
                                    $unchanged++;
                                    continue;
                                }

                                $newValue = json_encode($newArr, JSON_UNESCAPED_SLASHES);
                                if (! $dryRun) {
                                    DB::table($table)->where('id', $row->id)->update([$column => $newValue]);
                                }
                                $updated++;
                                continue;
                            }

                            $rewritten = $this->normalize((string) $current, $r2Url);
                            if ($rewritten === null) {
                                $external++;
                                continue;
                            }
                            if ($rewritten === $current) {
                                $unchanged++;
                                continue;
                            }
                            if (! $dryRun) {
                                DB::table($table)->where('id', $row->id)->update([$column => $rewritten]);
                            }
                            $updated++;
                        }
                    });

                $this->line(sprintf('  %s.%s — updated: %d, unchanged: %d, external skipped: %d', $table, $column, $updated, $unchanged, $external));
                $grandUpdated += $updated;
                $grandUnchanged += $unchanged;
                $grandSkippedExternal += $external;
            }
        }

        $this->newLine();
        $this->info("Total updated:           {$grandUpdated}");
        $this->info("Total already-correct:   {$grandUnchanged}");
        $this->info("Total external (kept):   {$grandSkippedExternal}");

        if ($dryRun) {
            $this->newLine();
            $this->comment('Dry-run: no rows were written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Convert any legacy form (raw path / /storage URL / bare path) into a full R2 URL.
     * Returns null if the value is an external URL we should leave alone.
     * Returns the original value if it's already correct.
     */
    protected function normalize(string $value, string $r2Url): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        // Anything containing /storage/ — strip up to and including the marker (even on R2 host)
        if (($pos = strpos($value, '/storage/')) !== false) {
            $path = ltrim(substr($value, $pos + strlen('/storage/')), '/');
            return $path === '' ? $value : $r2Url . '/' . $path;
        }

        // Already pointing at R2 (and no /storage/ segment) — leave it
        if (str_starts_with($value, $r2Url . '/') || $value === $r2Url) {
            return $value;
        }

        if (str_starts_with($value, 'storage/')) {
            $path = ltrim(substr($value, strlen('storage/')), '/');
            return $path === '' ? $value : $r2Url . '/' . $path;
        }

        // Other http(s) URLs — external, do not touch
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return null;
        }

        // Relative path
        $path = ltrim($value, '/');
        return $r2Url . '/' . $path;
    }
}
