<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class LoyaltyCustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('========================================');
        $this->command->info('Seeding Loyalty Customers...');
        $this->command->info('========================================');
        $this->command->newLine();

        // Create customers with different statuses and data completeness
        $customers = [
            // Complete customers with all data
            [
                'f_name' => 'Ahmed',
                'l_name' => 'Mohamed',
                'phone' => '+201012345678',
                'email' => 'ahmed.mohamed@example.com',
                'password' => bcrypt('Password123'),
                'status' => 1,
                'is_phone_verified' => 1,
                'is_email_verified' => 1,
                'email_verified_at' => now(),
                'wallet_balance' => 1500.50,
                'loyalty_point' => 250,
                'order_count' => 15,
                'zone_id' => 1,
            ],
            [
                'f_name' => 'Fatima',
                'l_name' => 'Ali',
                'phone' => '+201012345679',
                'email' => 'fatima.ali@example.com',
                'password' => bcrypt('Password123'),
                'status' => 1,
                'is_phone_verified' => 1,
                'is_email_verified' => 1,
                'email_verified_at' => now(),
                'wallet_balance' => 800.25,
                'loyalty_point' => 120,
                'order_count' => 8,
                'zone_id' => 1,
            ],
            [
                'f_name' => 'Mohamed',
                'l_name' => 'Hassan',
                'phone' => '+201012345680',
                'email' => 'mohamed.hassan@example.com',
                'password' => bcrypt('Password123'),
                'status' => 1,
                'is_phone_verified' => 1,
                'is_email_verified' => 0,
                'wallet_balance' => 500.00,
                'loyalty_point' => 75,
                'order_count' => 5,
                'zone_id' => 1,
            ],
            // Customers with only phone (incomplete registration)
            [
                'f_name' => 'Sara',
                'l_name' => 'Ibrahim',
                'phone' => '+201012345681',
                'email' => null,
                'password' => null,
                'status' => 1,
                'is_phone_verified' => 1,
                'is_email_verified' => 0,
                'wallet_balance' => 0,
                'loyalty_point' => 0,
                'order_count' => 0,
                'zone_id' => null,
            ],
            [
                'f_name' => 'Omar',
                'l_name' => 'Khalil',
                'phone' => '+201012345682',
                'email' => null,
                'password' => null,
                'status' => 1,
                'is_phone_verified' => 0,
                'is_email_verified' => 0,
                'wallet_balance' => 0,
                'loyalty_point' => 0,
                'order_count' => 0,
                'zone_id' => null,
            ],
            // Customers with phone and email but no password
            [
                'f_name' => 'Layla',
                'l_name' => 'Youssef',
                'phone' => '+201012345683',
                'email' => 'layla.youssef@example.com',
                'password' => null,
                'status' => 1,
                'is_phone_verified' => 1,
                'is_email_verified' => 0,
                'wallet_balance' => 200.00,
                'loyalty_point' => 30,
                'order_count' => 2,
                'zone_id' => 1,
            ],
            // More complete customers
            [
                'f_name' => 'Youssef',
                'l_name' => 'Mahmoud',
                'phone' => '+201012345684',
                'email' => 'youssef.mahmoud@example.com',
                'password' => bcrypt('Password123'),
                'status' => 1,
                'is_phone_verified' => 1,
                'is_email_verified' => 1,
                'email_verified_at' => now(),
                'wallet_balance' => 2200.75,
                'loyalty_point' => 350,
                'order_count' => 22,
                'zone_id' => 1,
            ],
            [
                'f_name' => 'Nour',
                'l_name' => 'Said',
                'phone' => '+201012345685',
                'email' => 'nour.said@example.com',
                'password' => bcrypt('Password123'),
                'status' => 1,
                'is_phone_verified' => 1,
                'is_email_verified' => 1,
                'email_verified_at' => now(),
                'wallet_balance' => 950.00,
                'loyalty_point' => 150,
                'order_count' => 12,
                'zone_id' => 1,
            ],
            [
                'f_name' => 'Khaled',
                'l_name' => 'Fahmy',
                'phone' => '+201012345686',
                'email' => 'khaled.fahmy@example.com',
                'password' => bcrypt('Password123'),
                'status' => 1,
                'is_phone_verified' => 1,
                'is_email_verified' => 1,
                'email_verified_at' => now(),
                'wallet_balance' => 1750.50,
                'loyalty_point' => 280,
                'order_count' => 18,
                'zone_id' => 1,
            ],
            [
                'f_name' => 'Mariam',
                'l_name' => 'Tarek',
                'phone' => '+201012345687',
                'email' => 'mariam.tarek@example.com',
                'password' => bcrypt('Password123'),
                'status' => 1,
                'is_phone_verified' => 1,
                'is_email_verified' => 1,
                'email_verified_at' => now(),
                'wallet_balance' => 600.25,
                'loyalty_point' => 95,
                'order_count' => 7,
                'zone_id' => 1,
            ],
        ];

        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $totalCustomers = count($customers);

        $this->command->info("Processing {$totalCustomers} customers...");
        $this->command->newLine();

        $progressBar = $this->command->getOutput()->createProgressBar($totalCustomers);
        $progressBar->start();

        foreach ($customers as $index => $customerData) {
            $customerNumber = $index + 1;
            $customerName = trim(($customerData['f_name'] ?? '') . ' ' . ($customerData['l_name'] ?? ''));
            $phone = $customerData['phone'] ?? 'N/A';

            try {
                // Validate required fields
                if (empty($customerData['phone'])) {
                    throw new Exception("Phone number is required");
                }

                if (empty($customerData['f_name'])) {
                    throw new Exception("First name is required");
                }

                // Check if customer already exists
                $existingCustomer = User::where('phone', $customerData['phone'])->first();

                if ($existingCustomer) {
                    $skippedCount++;
                    $progressBar->advance();
                    continue;
                }

                // Create customer
                $customer = User::create(array_merge($customerData, [
                    'image' => 'def.png',
                    'login_medium' => 'manual',
                    'current_language_key' => 'en',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                // Generate and assign ref_code
                try {
                    $customer->ref_code = Helpers::generate_referer_code();
                    $customer->save();
                } catch (Exception $e) {
                    Log::warning("LoyaltyCustomerSeeder: Failed to generate ref_code for customer {$customer->id}", [
                        'phone' => $phone,
                        'error' => $e->getMessage()
                    ]);
                }

                // Update tier based on loyalty points
                if (method_exists($customer, 'updateTier')) {
                    try {
                        $customer->updateTier();
                    } catch (Exception $e) {
                        Log::warning("LoyaltyCustomerSeeder: Failed to update tier for customer {$customer->id}", [
                            'phone' => $phone,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Create storage record for image
                try {
                    DB::table('storages')->insertOrIgnore([
                        'data_type' => User::class,
                        'data_id' => $customer->id,
                        'key' => 'image',
                        'value' => 'public',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (Exception $e) {
                    Log::warning("LoyaltyCustomerSeeder: Failed to create storage record for customer {$customer->id}", [
                        'phone' => $phone,
                        'error' => $e->getMessage()
                    ]);
                }

                $createdCount++;
                $progressBar->advance();

            } catch (Exception $e) {
                $errorCount++;
                Log::error("LoyaltyCustomerSeeder: Failed to seed customer #{$customerNumber}", [
                    'customer' => $customerName,
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->command->newLine(2);

        // Summary
        $this->command->info('========================================');
        $this->command->info('Seeding Summary:');
        $this->command->info('========================================');
        $this->command->info("Total customers processed: {$totalCustomers}");
        $this->command->info("✅ Successfully created: {$createdCount}");
        $this->command->info("⚠️  Skipped (already exists): {$skippedCount}");
        $this->command->info("❌ Errors: {$errorCount}");
        $this->command->info('========================================');
        $this->command->newLine();

        if ($errorCount > 0) {
            $this->command->warn("⚠️  {$errorCount} errors occurred. Check logs for details:");
            $this->command->info("   storage/logs/laravel.log");
            $this->command->newLine();
        }

        if ($createdCount > 0) {
            $this->command->info('Customer credentials (for complete users):');
            $this->command->info('  Password: Password123');
            $this->command->newLine();
        }
    }
}

