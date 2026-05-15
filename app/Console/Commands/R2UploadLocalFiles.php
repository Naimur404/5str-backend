<?php

namespace App\Console\Commands;

use App\Support\R2Storage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;

class R2UploadLocalFiles extends Command
{
    protected $signature = 'r2:upload-local-files
                            {--source=storage/app/public : Local directory to upload from, relative to base path}
                            {--prefix= : Optional R2 path prefix to prepend (e.g. "legacy/")}
                            {--overwrite : Re-upload files that already exist on R2}
                            {--dry-run : Show what would be uploaded without writing to R2}';

    protected $description = 'One-shot: upload everything under storage/app/public to Cloudflare R2, preserving the relative directory structure.';

    public function handle(): int
    {
        $source = base_path((string) $this->option('source'));
        $prefix = trim((string) $this->option('prefix'), '/');
        $overwrite = (bool) $this->option('overwrite');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_dir($source)) {
            $this->error("Source directory does not exist: {$source}");
            return self::FAILURE;
        }

        $this->info("Source:   {$source}");
        $this->info('Target:   r2://' . R2Storage::DISK . ($prefix ? "/{$prefix}" : ''));
        $this->info('Mode:     ' . ($dryRun ? 'DRY-RUN' : 'WRITE') . ($overwrite ? ' (overwrite)' : ' (skip existing)'));
        $this->newLine();

        $finder = (new Finder())->files()->in($source)->ignoreDotFiles(true);
        $total = $finder->count();

        if ($total === 0) {
            $this->warn('No files found.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $uploaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($finder as $file) {
            $relative = ltrim(str_replace('\\', '/', $file->getRelativePathname()), '/');
            $target = $prefix ? "{$prefix}/{$relative}" : $relative;

            try {
                if (! $overwrite && Storage::disk(R2Storage::DISK)->exists($target)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                if ($dryRun) {
                    $this->line("\n  would upload  {$relative}  →  r2://{$target}");
                    $uploaded++;
                    $bar->advance();
                    continue;
                }

                $stream = fopen($file->getRealPath(), 'rb');
                Storage::disk(R2Storage::DISK)->put($target, $stream, 'public');
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $uploaded++;
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("FAIL  {$relative}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Uploaded: {$uploaded}");
        $this->info("Skipped (already on R2): {$skipped}");
        if ($failed > 0) {
            $this->warn("Failed: {$failed}");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
