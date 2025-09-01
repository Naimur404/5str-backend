<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use App\Models\BusinessSimilarity;
use App\Jobs\CalculateBusinessSimilarityJob;

class CalculateBusinessSimilaritiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'business:calculate-similarities {--force : Force recalculation of all similarities}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate business similarities for recommendation system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting business similarity calculation...');

        $businesses = Business::with(['category', 'offerings'])->get();
        $totalBusinesses = $businesses->count();

        if ($totalBusinesses < 2) {
            $this->warn('Need at least 2 businesses to calculate similarities.');
            return;
        }

        $this->info("Found {$totalBusinesses} businesses");

        // Clear existing similarities if force flag is used
        if ($this->option('force')) {
            $this->info('Clearing existing similarities...');
            BusinessSimilarity::truncate();
        }

        $processed = 0;
        $created = 0;

        $bar = $this->output->createProgressBar($totalBusinesses * ($totalBusinesses - 1) / 2);
        $bar->start();

        foreach ($businesses as $business1) {
            foreach ($businesses as $business2) {
                if ($business1->id >= $business2->id) {
                    continue; // Avoid duplicates and self-comparison
                }

                // Check if similarity already exists
                $existingSimilarity = BusinessSimilarity::where([
                    'business_a_id' => min($business1->id, $business2->id),
                    'business_b_id' => max($business1->id, $business2->id),
                ])->first();

                if (!$existingSimilarity || $this->option('force')) {
                    if ($existingSimilarity) {
                        $existingSimilarity->delete();
                    }

                    $similarity = $this->calculateSimilarity($business1, $business2);
                    
                    if ($similarity > 0) {
                        BusinessSimilarity::create([
                            'business_a_id' => min($business1->id, $business2->id),
                            'business_b_id' => max($business1->id, $business2->id),
                            'similarity_type' => 'category_and_location',
                            'similarity_score' => $similarity,
                        ]);

                        $created++;
                    }
                }

                $processed++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info("Processed {$processed} business pairs");
        $this->info("Created {$created} similarity records");
        $this->info('Business similarity calculation completed!');
    }

    /**
     * Calculate similarity between two businesses
     */
    private function calculateSimilarity(Business $business1, Business $business2): float
    {
        $similarity = 0;

        // Category similarity (40% weight)
        if ($business1->category_id === $business2->category_id) {
            $similarity += 0.4;
        }

        // Location similarity (30% weight) - same area/district
        if ($business1->area === $business2->area) {
            $similarity += 0.3;
        }

        // District similarity (20% weight)
        if ($business1->district === $business2->district) {
            $similarity += 0.2;
        }

        // Offering similarity (10% weight)
        $business1Offerings = $business1->offerings->pluck('id')->toArray();
        $business2Offerings = $business2->offerings->pluck('id')->toArray();
        
        if (!empty($business1Offerings) && !empty($business2Offerings)) {
            $intersection = count(array_intersect($business1Offerings, $business2Offerings));
            $union = count(array_unique(array_merge($business1Offerings, $business2Offerings)));
            
            if ($union > 0) {
                $offeringSimilarity = $intersection / $union;
                $similarity += $offeringSimilarity * 0.1;
            }
        }

        return min(1.0, $similarity); // Cap at 1.0
    }
}
