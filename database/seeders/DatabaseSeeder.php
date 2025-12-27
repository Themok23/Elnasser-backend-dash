<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Seed in order of dependency
        $this->call([
            SettingsSeeder::class,      // Settings first (login URLs, tier thresholds, etc.)
            AdminSeeder::class,         // Admin user
            UserSeeder::class,          // Basic users (existing)
            CustomerSeeder::class,      // Complete customers with wallet/points
            OrderSeeder::class,         // Orders with order details
            PointsSeeder::class,       // Loyalty points, wallet, and order transactions
        ]);
    }
}
