<?php
/**
 * Laravel Scheduler Runner for Shared Hosting
 * 
 * Add this to your cPanel cron jobs to run every minute:
 * * * * * * /usr/bin/php /path/to/your/project/cron-scheduler.php >> /dev/null 2>&1
 */

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->call('schedule:run');

echo "Scheduler executed at " . date('Y-m-d H:i:s') . PHP_EOL;
