<?php
/**
 * Laravel Queue Worker for Shared Hosting
 * 
 * Add this to your cPanel cron jobs to run every minute:
 * * * * * * /usr/bin/php /path/to/your/project/cron-queue.php >> /dev/null 2>&1
 */

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Process queue jobs (stop when empty to avoid hanging)
$kernel->call('queue:work', [
    '--stop-when-empty' => true,
    '--max-time' => 60, // Run for maximum 60 seconds
    '--memory' => 128,  // Memory limit
    '--timeout' => 60   // Job timeout
]);

echo "Queue processed at " . date('Y-m-d H:i:s') . PHP_EOL;
