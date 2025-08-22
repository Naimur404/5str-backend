<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SearchLog;
use App\Models\User;

class SearchLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        
        $searchTerms = [
            'pizza', 'restaurant', 'burger', 'biryani', 'chinese food',
            'beauty salon', 'hair cut', 'spa', 'massage', 'bridal makeup',
            'shopping mall', 'clothing', 'electronics', 'mobile phone', 'laptop',
            'movie theater', 'cinema', 'entertainment', 'gym', 'fitness center',
            'hospital', 'doctor', 'pharmacy', 'medical center', 'dentist',
            'hotel', 'travel agency', 'tour package', 'car rental', 'transport',
            'bank', 'atm', 'money exchange', 'insurance', 'loan',
            'school', 'college', 'university', 'tuition', 'training center',
            'repair service', 'plumber', 'electrician', 'ac service', 'cleaning'
        ];

        $areas = ['dhanmondi', 'gulshan', 'uttara', 'mirpur', 'banani', 'wari', 'old dhaka'];

        // Generate 200 search logs
        for ($i = 0; $i < 200; $i++) {
            $isGuest = rand(1, 10) <= 4; // 40% guest searches
            $searchTerm = $searchTerms[array_rand($searchTerms)];
            
            // Sometimes add area to search
            if (rand(1, 10) <= 3) {
                $searchTerm .= ' ' . $areas[array_rand($areas)];
            }

            SearchLog::create([
                'user_id' => $isGuest ? null : $users->random()->id,
                'search_term' => $searchTerm,
                'category_id' => rand(1, 10) <= 3 ? rand(1, 10) : null,
                'user_latitude' => rand(1, 10) <= 5 ? 23.7000 + (rand(-500, 500) / 10000) : null,
                'user_longitude' => rand(1, 10) <= 5 ? 90.3000 + (rand(-500, 500) / 10000) : null,
                'filters_applied' => rand(1, 10) <= 3 ? json_encode([
                    'price_range' => rand(1, 4),
                    'has_delivery' => true
                ]) : null,
                'results_count' => rand(0, 25),
                'clicked_business_id' => rand(1, 10) <= 6 ? rand(1, 5) : null,
                'created_at' => now()->subDays(rand(1, 90)),
                'updated_at' => now()->subDays(rand(1, 90))
            ]);
        }
    }
}
