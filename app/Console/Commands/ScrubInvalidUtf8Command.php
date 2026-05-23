<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scrub invalid UTF-8 byte sequences from text columns of any table.
 *
 * Usage:
 *
 *     php artisan scrub:invalid-utf8                # businesses
 *     php artisan scrub:invalid-utf8 attractions    # specific table
 *     php artisan scrub:invalid-utf8 reviews --columns=title,body
 *     php artisan scrub:invalid-utf8 --dry-run      # preview only
 *
 * The runtime middleware already substitutes bad bytes with U+FFFD on
 * every response so the API never returns a 500 anymore. This command
 * cleans the underlying rows so the substitution is no longer needed.
 *
 * Resilience:
 *   - Looks up the actual columns of the target table via Schema, so
 *     specifying a column that doesn't exist (or running against an
 *     older deployment that lacks a column we expected) just skips
 *     instead of crashing with "Unknown column".
 *   - Only scrubs text-shaped types (`varchar`, `text`, etc.); numeric
 *     and boolean columns are left alone.
 *   - Defaults are a "best guess" set of likely-text columns per
 *     table; missing columns from that list are silently dropped.
 *   - Streams in chunks; safe on multi-million-row tables.
 */
class ScrubInvalidUtf8Command extends Command
{
    protected $signature = 'scrub:invalid-utf8
                            {table=businesses : Table to scrub}
                            {--columns= : Comma-separated columns; defaults to a sane set per table}
                            {--chunk=200 : Rows per pass}
                            {--dry-run : Report rows that would change without writing}';

    protected $description = 'Replace invalid UTF-8 byte sequences in text columns with U+FFFD.';

    /**
     * Likely-text columns for the tables we know. Anything in this
     * list that doesn't exist on the actual table is dropped before
     * we run the scan.
     *
     * @var array<string, list<string>>
     */
    private const DEFAULT_COLUMNS = [
        'businesses' => [
            'business_name',
            'description',
            'short_description',
            'about',
            'tagline',
            'full_address',
            'address',
            'landmark',
            'area',
            'city',
            'business_email',
            'website',
        ],
        'attractions' => [
            'name',
            'description',
            'short_description',
            'history',
            'best_time_to_visit',
            'full_address',
            'address',
            'landmark',
            'area',
            'city',
        ],
        'reviews' => [
            'title',
            'body',
            'review_text',
            'pros',
            'cons',
        ],
        'business_offerings' => [
            'name',
            'description',
            'short_description',
        ],
        'attraction_reviews' => [
            'title',
            'body',
            'review_text',
        ],
    ];

    /**
     * Doctrine-flavoured "stringy" types that contain text we may want
     * to scrub. Other types (json, decimal, datetime, etc.) are left
     * alone — UTF-8 only matters for actual text columns.
     */
    private const STRINGY_TYPES = ['string', 'text', 'mediumText', 'longText', 'tinyText'];

    public function handle(): int
    {
        $table = (string) $this->argument('table');
        $chunk = max(50, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        if (! Schema::hasTable($table)) {
            $this->error("Table '{$table}' does not exist.");

            return self::FAILURE;
        }

        $existingColumns = $this->existingColumnsFor($table);
        $requested = $this->resolveColumns($table);
        $columns = array_values(array_intersect($requested, array_keys($existingColumns)));

        if (empty($columns)) {
            $hint = $this->bestGuessColumnsForTable($existingColumns);
            $this->error("No applicable text columns to scrub on '{$table}'.");
            if (! empty($hint)) {
                $this->line('Available text columns: '.implode(', ', $hint));
            }

            return self::FAILURE;
        }

        $missing = array_diff($requested, $columns);
        if (! empty($missing)) {
            $this->warn('Skipping columns not present on this table: '.implode(', ', $missing));
        }

        mb_substitute_character(0xFFFD);

        $this->info("Scrubbing {$table} columns: ".implode(', ', $columns));
        if ($dryRun) {
            $this->warn('Dry run — no rows will be written.');
        }

        $cleaned = 0;
        $touched = 0;

        DB::table($table)
            ->select(array_merge(['id'], $columns))
            ->orderBy('id')
            ->chunk($chunk, function ($rows) use (&$cleaned, &$touched, $columns, $table, $dryRun) {
                foreach ($rows as $row) {
                    $touched++;
                    $diff = [];

                    foreach ($columns as $col) {
                        $value = $row->{$col};
                        if (! is_string($value) || $value === '') {
                            continue;
                        }
                        if (mb_check_encoding($value, 'UTF-8')) {
                            continue;
                        }

                        $clean = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                        if ($clean !== $value) {
                            $diff[$col] = $clean;
                        }
                    }

                    if (empty($diff)) {
                        continue;
                    }

                    $cleaned++;

                    if (! $dryRun) {
                        DB::table($table)->where('id', $row->id)->update($diff);
                    }
                }
            });

        $this->info("Scanned {$touched} rows; cleaned {$cleaned}.");

        return self::SUCCESS;
    }

    /**
     * Map of column-name => Doctrine type for the target table.
     *
     * @return array<string, string>
     */
    private function existingColumnsFor(string $table): array
    {
        $columns = Schema::getColumnListing($table);
        $map = [];
        foreach ($columns as $name) {
            try {
                $map[$name] = Schema::getColumnType($table, $name);
            } catch (\Throwable) {
                // Driver reported a type Doctrine can't map (enum on
                // some versions, etc.). Treat as opaque: include the
                // column so callers can scrub it if they ask, but
                // don't auto-pick it from a default set.
                $map[$name] = 'unknown';
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function resolveColumns(string $table): array
    {
        $option = $this->option('columns');
        if (is_string($option) && $option !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $option))));
        }

        return self::DEFAULT_COLUMNS[$table] ?? $this->bestGuessColumnsForTable($this->existingColumnsFor($table));
    }

    /**
     * @param  array<string, string>  $columns
     * @return list<string>
     */
    private function bestGuessColumnsForTable(array $columns): array
    {
        $stringy = [];
        foreach ($columns as $name => $type) {
            if (in_array($type, self::STRINGY_TYPES, true)) {
                $stringy[] = $name;
            }
        }

        return $stringy;
    }
}
