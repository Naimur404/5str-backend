<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EndpointAnalytics;
use App\Models\User;
use Carbon\Carbon;

class EndpointAnalyticsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::limit(10)->get();
        $endpoints = [
            'home_index',
            'popular_nearby',
            'top_rated',
            'trending',
            'business_index',
            'business_show',
            'search_businesses',
            'search_offerings',
            'offering_index',
            'offering_show',
            'featured_sections',
            'statistics',
        ];

        $areas = [
            'Dhanmondi Ward 2',
            'Gulshan 1',
            'Banani',
            'Uttara Sector 3',
            'Mirpur 1',
            'Wari',
            'Old Dhaka',
            'Tejgaon',
            'Mohammadpur',
            'Bashundhara R/A',
            'Baridhara',
            'Lalmatia',
            'Elephant Road',
            'New Market',
            'Farmgate',
        ];

        $coordinates = [
            ['lat' => 23.7465, 'lng' => 90.3776], // Dhanmondi
            ['lat' => 23.7903, 'lng' => 90.4125], // Gulshan
            ['lat' => 23.7937, 'lng' => 90.4066], // Banani
            ['lat' => 23.8759, 'lng' => 90.3795], // Uttara
            ['lat' => 23.8103, 'lng' => 90.3654], // Mirpur
            ['lat' => 23.7104, 'lng' => 90.4074], // Wari
            ['lat' => 23.7296, 'lng' => 90.3955], // Old Dhaka
            ['lat' => 23.7644, 'lng' => 90.3887], // Tejgaon
            ['lat' => 23.7692, 'lng' => 90.3563], // Mohammadpur
            ['lat' => 23.8041, 'lng' => 90.4152], // Bashundhara
            ['lat' => 23.7956, 'lng' => 90.4168], // Baridhara
            ['lat' => 23.7558, 'lng' => 90.3647], // Lalmatia
            ['lat' => 23.7380, 'lng' => 90.3872], // Elephant Road
            ['lat' => 23.7340, 'lng' => 90.3853], // New Market
            ['lat' => 23.7515, 'lng' => 90.3897], // Farmgate
        ];

        // Create analytics data for the last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            // Generate 50-200 requests per day with realistic patterns
            $dailyRequests = rand(50, 200);
            
            // Weekend and evening patterns
            if ($date->isWeekend()) {
                $dailyRequests = (int) ($dailyRequests * 0.7); // Less traffic on weekends
            }

            for ($j = 0; $j < $dailyRequests; $j++) {
                $user = $users->random();
                $endpoint = $endpoints[array_rand($endpoints)];
                $areaIndex = array_rand($areas);
                $area = $areas[$areaIndex];
                $coords = $coordinates[$areaIndex];
                
                // Add some randomness to coordinates
                $lat = $coords['lat'] + (rand(-50, 50) / 10000);
                $lng = $coords['lng'] + (rand(-50, 50) / 10000);

                // Create random timestamp within the day
                $timestamp = $date->copy()
                    ->addHours(rand(6, 23))
                    ->addMinutes(rand(0, 59))
                    ->addSeconds(rand(0, 59));

                // Weight certain endpoints to be more popular
                $popularEndpoints = ['home_index', 'popular_nearby', 'search_businesses'];
                if (in_array($endpoint, $popularEndpoints) && rand(1, 100) <= 30) {
                    // Create additional entries for popular endpoints
                    $this->createAnalyticsEntry($user, $endpoint, $area, $lat, $lng, $timestamp);
                }

                $this->createAnalyticsEntry($user, $endpoint, $area, $lat, $lng, $timestamp);
            }
        }

        $this->command->info('Created endpoint analytics sample data for the last 30 days');
    }

    private function createAnalyticsEntry($user, $endpoint, $area, $lat, $lng, $timestamp)
    {
        EndpointAnalytics::create([
            'endpoint' => $endpoint,
            'user_id' => rand(1, 100) <= 70 ? $user->id : null, // 70% authenticated, 30% guest
            'user_area' => $area,
            'latitude' => $lat,
            'longitude' => $lng,
            'ip_address' => $this->generateRandomIP(),
            'user_agent' => $this->generateRandomUserAgent(),
            'additional_data' => [
                'status_code' => 200,
                'method' => 'GET',
                'response_time' => round(rand(50, 500) / 1000, 3), // 0.05 to 0.5 seconds
                'has_auth' => $user !== null,
            ],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function generateRandomIP(): string
    {
        return rand(103, 119) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255);
    }

    private function generateRandomUserAgent(): string
    {
        $userAgents = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Android 11; Mobile; rv:92.0) Gecko/92.0 Firefox/92.0',
            'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36 Chrome/91.0.4472.120',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/91.0.4472.124',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/91.0.4472.124',
            'Mozilla/5.0 (iPad; CPU OS 15_0 like Mac OS X) AppleWebKit/605.1.15',
        ];

        return $userAgents[array_rand($userAgents)];
    }
}
