<?php
/**
 * Business Similarity Calculator for Shared Hosting
 * 
 * Run this manually or add to cron to run every 6 hours:
 * 0 0,6,12,18 * * * /usr/bin/php /path/to/your/project/cron-similarities.php >> /dev/null 2>&1
 */

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "Starting business similarity calculation at " . date('Y-m-d H:i:s') . PHP_EOL;

$kernel->call('business:calculate-similarities');

echo "Similarity calculation completed at " . date('Y-m-d H:i:s') . PHP_EOL;
