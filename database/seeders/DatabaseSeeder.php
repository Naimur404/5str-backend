<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run seeders in order
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            FeaturedSectionSeeder::class,
            BusinessSeeder::class,
            BusinessImageSeeder::class,
            BusinessOfferingSeeder::class,
            ReviewSeeder::class,
            OfferSeeder::class,
            BannerSeeder::class,
            FavoriteSeeder::class,
            ViewSeeder::class,
            SearchLogSeeder::class,
            TrendingDataSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
