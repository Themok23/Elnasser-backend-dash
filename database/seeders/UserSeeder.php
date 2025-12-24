<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Users with only phone and name (no password)
        $usersWithoutPassword = [
            [
                'f_name' => 'Ahmed',
                'l_name' => 'Ali',
                'phone' => '+201234567890',
            ],
            [
                'f_name' => 'Sara',
                'l_name' => 'Mohamed',
                'phone' => '+201234567891',
            ],
            [
                'f_name' => 'Mohamed',
                'l_name' => 'Hassan',
                'phone' => '+201234567892',
            ],
            [
                'f_name' => 'Fatima',
                'l_name' => 'Ibrahim',
                'phone' => '+201234567893',
            ],
            [
                'f_name' => 'Omar',
                'l_name' => 'Khalil',
                'phone' => '+201234567894',
            ],
            [
                'f_name' => 'Layla',
                'l_name' => 'Youssef',
                'phone' => '+201234567895',
            ],
            [
                'f_name' => 'Youssef',
                'l_name' => 'Mahmoud',
                'phone' => '+201234567896',
            ],
            [
                'f_name' => 'Nour',
                'l_name' => 'Said',
                'phone' => '+201234567897',
            ],
            [
                'f_name' => 'Khaled',
                'l_name' => 'Fahmy',
                'phone' => '+201234567898',
            ],
            [
                'f_name' => 'Mariam',
                'l_name' => 'Tarek',
                'phone' => '+201234567899',
            ],
        ];

        foreach ($usersWithoutPassword as $userData) {
            $user = User::create([
                'f_name' => $userData['f_name'],
                'l_name' => $userData['l_name'],
                'phone' => $userData['phone'],
                'password' => null, // No password
                'status' => 1,
                'is_phone_verified' => 0,
                'is_email_verified' => 0,
                'wallet_balance' => 0.000,
                'loyalty_point' => 0.000,
                'order_count' => 0,
                'current_language_key' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Generate and assign ref_code
            $user->ref_code = Helpers::generate_referer_code();
            $user->save();
        }

        // Complete user with all data
        $completeUser = User::create([
            'f_name' => 'John',
            'l_name' => 'Doe',
            'phone' => '+201156683330',
            'email' => 'john.doe@example.com',
            'password' => bcrypt('Password123'),
            'image' => 'def.png',
            'status' => 1,
            'is_phone_verified' => 1,
            'is_email_verified' => 1,
            'email_verified_at' => now(),
            'wallet_balance' => 500.000,
            'loyalty_point' => 100.000,
            'order_count' => 5,
            'login_medium' => 'manual',
            'current_language_key' => 'en',
            'zone_id' => 1, // Assuming zone_id 1 exists
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Generate and assign ref_code for complete user
        $completeUser->ref_code = Helpers::generate_referer_code();
        $completeUser->save();

        // Create storage record for complete user's image
        DB::table('storages')->insertOrIgnore([
            'data_type' => User::class,
            'data_id' => $completeUser->id,
            'key' => 'image',
            'value' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Seeded ' . count($usersWithoutPassword) . ' users without passwords');
        $this->command->info('Seeded 1 complete user with all data');
        $this->command->info('Complete user credentials:');
        $this->command->info('  Phone: +201156683330');
        $this->command->info('  Email: john.doe@example.com');
        $this->command->info('  Password: Password123');
    }
}
