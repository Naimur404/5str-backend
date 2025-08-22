<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AnalyticsService;
use App\Models\User;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

class GenerateTestAnalytics extends Command
{
    protected $signature = 'analytics:generate-test-data {--count=50 : Number of records to generate}';
    protected $description = 'Generate test analytics data for search logs, views, and trending data';

    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        parent::__construct();
        $this->analyticsService = $analyticsService;
    }

    public function handle()
    {
        $count = (int) $this->option('count');
        $this->info("Generating {$count} test analytics records...");

        $users = User::all();
        $businesses = Business::all();
        $categories = Category::all();

        if ($businesses->isEmpty()) {
            $this->error('No businesses found. Please run the seeders first.');
            return Command::FAILURE;
        }

        // Generate search logs
        $this->line('Generating search logs...');
        $searchTerms = [
            'restaurant', 'food', 'pizza', 'hotel', 'shopping', 'doctor', 
            'pharmacy', 'salon', 'gym', 'school', 'bank', 'coffee', 
            'electronics', 'clothing', 'grocery', 'hospital'
        ];

        for ($i = 0; $i < $count; $i++) {
            $user = $users->random();
            Auth::login($user);

            $searchTerm = $searchTerms[array_rand($searchTerms)];
            $category = $categories->random();
            $clickedBusiness = rand(0, 3) ? $businesses->random() : null; // 75% chance of click

            $this->analyticsService->logSearch(
                searchTerm: $searchTerm,
                categoryId: $category->id,
                userLatitude: 23.7465 + (rand(-100, 100) / 1000), // Around Dhaka
                userLongitude: 90.3754 + (rand(-100, 100) / 1000),
                filtersApplied: [
                    'price_range' => rand(1, 4),
                    'has_delivery' => rand(0, 1)
                ],
                resultsCount: rand(0, 50),
                clickedBusinessId: $clickedBusiness?->id
            );

            Auth::logout();
        }

        // Generate views
        $this->line('Generating view logs...');
        for ($i = 0; $i < $count * 2; $i++) {
            $user = $users->random();
            Auth::login($user);

            $business = $businesses->random();
            $this->analyticsService->logView($business);

            Auth::logout();
        }

        // Calculate trending data
        $this->line('Calculating trending data...');
        $this->analyticsService->calculateBusinessTrending('daily');
        $this->analyticsService->calculateCategoryTrending('daily');
        $this->analyticsService->calculateSearchTermTrending('daily');

        $this->info('Test analytics data generated successfully!');
        $this->line("Generated:");
        $this->line("- {$count} search logs");
        $this->line("- " . ($count * 2) . " view logs");
        $this->line("- Trending data calculations");

        return Command::SUCCESS;
    }
}
