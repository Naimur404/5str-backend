<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Calculate trending data automatically - EVERY MINUTE for real-time analytics
        $schedule->command('analytics:calculate-trending daily')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('analytics:calculate-trending weekly')
                 ->weeklyOn(1, '02:00') // Every Monday at 2 AM
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('analytics:calculate-trending monthly')
                 ->monthlyOn(1, '03:00') // First day of month at 3 AM
                 ->withoutOverlapping()
                 ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
