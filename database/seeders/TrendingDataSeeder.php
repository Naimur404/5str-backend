<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TrendingData;
use App\Models\Business;
use App\Models\Category;
use Carbon\Carbon;

class TrendingDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businesses = Business::all();
        $categories = Category::where('parent_id', null)->get(); // Main categories only
        
        // Generate trending data for the last 30 days
        $dates = [];
        for ($i = 29; $i >= 0; $i--) {
            $dates[] = Carbon::now()->subDays($i)->format('Y-m-d');
        }
        
        $areas = ['Dhanmondi', 'Gulshan', 'Uttara', 'Banani', 'Mirpur', 'Wari', 'Old Dhaka'];
        
        foreach ($dates as $date) {
            // Trending businesses
            foreach ($businesses->random(3) as $business) {
                foreach ($areas as $area) {
                    // Daily trends
                    TrendingData::create([
                        'item_type' => 'business',
                        'item_id' => $business->id,
                        'item_name' => $business->business_name,
                        'location_area' => $area,
                        'trend_score' => rand(10, 100), // 0-100 scale
                        'time_period' => 'daily',
                        'date_period' => $date,
                    ]);
                }
            }
            
            // Trending categories
            foreach ($categories->random(2) as $category) {
                foreach ($areas as $area) {
                    TrendingData::create([
                        'item_type' => 'category',
                        'item_id' => $category->id,
                        'item_name' => $category->name,
                        'location_area' => $area,
                        'trend_score' => rand(15, 90), // 0-100 scale
                        'time_period' => 'daily',
                        'date_period' => $date,
                    ]);
                }
            }
            
            // Trending search terms
            $searchTerms = [
                'pizza delivery',
                'best restaurant',
                'online shopping',
                'beauty salon',
                'car service',
                'mobile repair',
                'gym near me',
                'coffee shop',
                'biryani',
                'fast food'
            ];
            
            foreach (array_slice($searchTerms, 0, 3) as $term) {
                foreach ($areas as $area) {
                    TrendingData::create([
                        'item_type' => 'search_term',
                        'item_id' => null,
                        'item_name' => $term,
                        'location_area' => $area,
                        'trend_score' => rand(5, 70), // 0-100 scale
                        'time_period' => 'daily',
                        'date_period' => $date,
                    ]);
                }
            }
        }
        
        // Generate weekly trends for the last 8 weeks
        $weekDates = [];
        for ($i = 7; $i >= 0; $i--) {
            $weekDates[] = Carbon::now()->subWeeks($i)->startOfWeek()->format('Y-m-d');
        }
        
        foreach ($weekDates as $date) {
            // Weekly trending businesses
            foreach ($businesses->random(2) as $business) {
                foreach (array_slice($areas, 0, 3) as $area) {
                    TrendingData::create([
                        'item_type' => 'business',
                        'item_id' => $business->id,
                        'item_name' => $business->business_name,
                        'location_area' => $area,
                        'trend_score' => rand(25, 95), // 0-100 scale (weekly trending higher than daily)
                        'time_period' => 'weekly',
                        'date_period' => $date,
                    ]);
                }
            }
            
            // Weekly trending categories
            foreach ($categories->random(1) as $category) {
                foreach (array_slice($areas, 0, 3) as $area) {
                    TrendingData::create([
                        'item_type' => 'category',
                        'item_id' => $category->id,
                        'item_name' => $category->name,
                        'location_area' => $area,
                        'trend_score' => rand(30, 100), // 0-100 scale
                        'time_period' => 'weekly',
                        'date_period' => $date,
                    ]);
                }
            }
        }
        
        // Generate monthly trends for the last 6 months
        $monthDates = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDates[] = Carbon::now()->subMonths($i)->startOfMonth()->format('Y-m-d');
        }
        
        foreach ($monthDates as $date) {
            // Monthly trending businesses
            foreach ($businesses->random(1) as $business) {
                foreach (array_slice($areas, 0, 2) as $area) {
                    TrendingData::create([
                        'item_type' => 'business',
                        'item_id' => $business->id,
                        'item_name' => $business->business_name,
                        'location_area' => $area,
                        'trend_score' => rand(40, 100), // 0-100 scale (monthly trending highest)
                        'time_period' => 'monthly',
                        'date_period' => $date,
                    ]);
                }
            }
            
            // Monthly trending categories
            foreach ($categories->random(1) as $category) {
                foreach (array_slice($areas, 0, 2) as $area) {
                    TrendingData::create([
                        'item_type' => 'category',
                        'item_id' => $category->id,
                        'item_name' => $category->name,
                        'location_area' => $area,
                        'trend_score' => rand(50, 100), // 0-100 scale
                        'time_period' => 'monthly',
                        'date_period' => $date,
                    ]);
                }
            }
        }
        
        echo "Trending data seeded successfully!\n";
        echo "Daily trends: " . TrendingData::where('time_period', 'daily')->count() . "\n";
        echo "Weekly trends: " . TrendingData::where('time_period', 'weekly')->count() . "\n";
        echo "Monthly trends: " . TrendingData::where('time_period', 'monthly')->count() . "\n";
    }
}
