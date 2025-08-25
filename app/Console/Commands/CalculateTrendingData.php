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
            // Calculate all trending data at once
            $this->line('Calculating all trending data...');
            $this->analyticsService->calculateAllTrending($period, $date);

            $this->info('All trending data calculation completed successfully!');
            $this->line('Calculated:');
            $this->line('- Business trending data');
            $this->line('- Category trending data');
            $this->line('- Offering trending data');
            $this->line('- Search term trending data');
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error calculating trending data: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
