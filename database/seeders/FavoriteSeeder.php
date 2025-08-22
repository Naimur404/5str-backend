<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Favorite;
use App\Models\User;
use App\Models\Business;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereHas('roles', function($query) {
            $query->whereIn('name', ['customer', 'business_owner']);
        })->get();
        $businesses = Business::all();

        foreach ($users as $user) {
            // Each user will favorite 2-5 random businesses
            $favoriteCount = rand(2, 5);
            $randomBusinesses = $businesses->random($favoriteCount);

            foreach ($randomBusinesses as $business) {
                Favorite::create([
                    'user_id' => $user->id,
                    'favoritable_type' => Business::class,
                    'favoritable_id' => $business->id,
                    'created_at' => now()->subDays(rand(1, 90)),
                    'updated_at' => now()->subDays(rand(1, 90))
                ]);
            }
        }
    }
}
