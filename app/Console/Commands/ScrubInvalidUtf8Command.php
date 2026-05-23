<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scrub invalid UTF-8 byte sequences from text columns of a model
 * table. Defaults to the `businesses` table, with the column list we
 * know from production to occasionally hold mixed-encoding bytes.
 *
 * The runtime middleware already substitutes bad bytes with U+FFFD on
 * every response, so the API never returns a 500 anymore, but the
 * stored data is still wrong. Run this once after a known-bad import
 * to clean the rows in place.
 *
 *     php artisan scrub:invalid-utf8                # businesses table
 *     php artisan scrub:invalid-utf8 attractions    # one table
 *     php artisan scrub:invalid-utf8 --columns=description,landmark
 *
 * The fix is "convert from UTF-8 to UTF-8 substituting invalid bytes"
 * — `mb_convert_encoding` with `'UTF-8', 'UTF-8'` uses the substitute
 * character configured by `mb_substitute_character`. We set that to
 * U+FFFD so users see the same replacement glyph the JSON middleware
 * produces.
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
     * @var array<string, list<string>>
     */
    private const DEFAULT_COLUMNS = [
        'businesses' => [
            'business_name',
            'description',
            'short_description',
            'full_address',
            'landmark',
            'area',
            'city',
        ],
        'attractions' => [
            'name',
            'description',
            'short_description',
            'full_address',
            'landmark',
            'area',
            'city',
        ],
        'reviews' => [
            'title',
            'body',
            'pros',
            'cons',
        ],
    ];

    public function handle(): int
    {
        $table = (string) $this->argument('table');
        $chunk = max(50, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $columns = $this->resolveColumns($table);
        if (empty($columns)) {
            $this->error("No columns to scrub for table '{$table}'. Pass --columns=col1,col2.");

            return self::FAILURE;
        }

        if (! \Schema::hasTable($table)) {
            $this->error("Table '{$table}' does not exist.");

            return self::FAILURE;
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
     * @return list<string>
     */
    private function resolveColumns(string $table): array
    {
        $option = $this->option('columns');
        if (is_string($option) && $option !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $option))));
        }

        return self::DEFAULT_COLUMNS[$table] ?? [];
    }
}
