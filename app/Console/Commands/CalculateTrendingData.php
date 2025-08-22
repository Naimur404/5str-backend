<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AnalyticsService;

class CalculateTrendingData extends Command
{
    protected $signature = 'analytics:calculate-trending 
                           {period=daily : Time period (daily, weekly, monthly)}
                           {--date= : Specific date (YYYY-MM-DD)}';
    
    protected $description = 'Calculate trending data for businesses, categories, and search terms';

    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        parent::__construct();
        $this->analyticsService = $analyticsService;
    }

    public function handle()
    {
        $period = $this->argument('period');
        $date = $this->option('date') ?: now()->format('Y-m-d');

        if (!in_array($period, ['daily', 'weekly', 'monthly'])) {
            $this->error('Period must be daily, weekly, or monthly');
            return Command::FAILURE;
        }

        $this->info("Calculating {$period} trending data for {$date}...");

        try {
            // Calculate business trending
            $this->line('Calculating business trending data...');
            $this->analyticsService->calculateBusinessTrending($period, $date);

            // Calculate category trending
            $this->line('Calculating category trending data...');
            $this->analyticsService->calculateCategoryTrending($period, $date);

            // Calculate search term trending
            $this->line('Calculating search term trending data...');
            $this->analyticsService->calculateSearchTermTrending($period, $date);

            $this->info('Trending data calculation completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error calculating trending data: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
