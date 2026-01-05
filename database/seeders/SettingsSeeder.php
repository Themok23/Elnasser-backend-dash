<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\CentralLogics\Helpers;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Seeding business settings...');
        $this->seedBusinessSettings();
        
        $this->command->info('Seeding data settings...');
        $this->seedDataSettings();
        
        $this->command->info('Seeding tier settings...');
        $this->seedTierSettings();
        
        $this->command->info('Settings seeding completed!');
    }

    protected function seedBusinessSettings()
    {
        // Critical business settings
        $businessSettings = [
            ['key' => 'landing_page', 'value' => '1'],
            ['key' => 'wallet_status', 'value' => '0'],
            ['key' => 'loyalty_point_status', 'value' => '0'],
            ['key' => 'ref_earning_status', 'value' => '0'],
            ['key' => 'wallet_add_refund', 'value' => '0'],
            ['key' => 'loyalty_point_exchange_rate', 'value' => '0'],
            ['key' => 'ref_earning_exchange_rate', 'value' => '0'],
            ['key' => 'loyalty_point_item_purchase_point', 'value' => '0'],
            ['key' => 'loyalty_point_minimum_point', 'value' => '0'],
            ['key' => 'dm_tips_status', 'value' => '0'],
            ['key' => 'refund_active_status', 'value' => '1'],
            ['key' => 'social_login', 'value' => '[{"login_medium":"google","client_id":"","client_secret":"","status":"0"},{"login_medium":"facebook","client_id":"","client_secret":"","status":""}]'],
            ['key' => 'system_language', 'value' => '[{"id":1,"direction":"ltr","code":"en","status":1,"default":true}]'],
            ['key' => 'language', 'value' => '["en"]'],
            ['key' => 'home_delivery_status', 'value' => '1'],
            ['key' => 'takeaway_status', 'value' => '1'],
            ['key' => 'country_picker_status', 'value' => '1'],
            ['key' => 'manual_login_status', 'value' => '1'],
            ['key' => 'business_name', 'value' => 'ALNASSER'],
        ];

        foreach ($businessSettings as $setting) {
            DB::table('business_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }

    protected function seedDataSettings()
    {
        // Critical login URLs - MUST be seeded
        $loginUrls = [
            ['key' => 'admin_login_url', 'value' => 'master', 'type' => 'login_admin'],
            ['key' => 'admin_employee_login_url', 'value' => 'admin-employee', 'type' => 'login_admin_employee'],
            ['key' => 'store_login_url', 'value' => 'store', 'type' => 'login_store'],
            ['key' => 'store_employee_login_url', 'value' => 'store-employee', 'type' => 'login_store_employee'],
        ];

        foreach ($loginUrls as $url) {
            DB::table('data_settings')->updateOrInsert(
                ['key' => $url['key'], 'type' => $url['type']],
                [
                    'value' => $url['value'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }

        // Landing page settings
        $landingPageSettings = [
            ['key' => 'fixed_header_title', 'value' => 'Manage Your Daily Life in one platform', 'type' => 'admin_landing_page'],
            ['key' => 'fixed_header_sub_title', 'value' => 'More than just a reliable eCommerce platform', 'type' => 'admin_landing_page'],
            ['key' => 'fixed_module_title', 'value' => 'Your eCommerce venture starts here !', 'type' => 'admin_landing_page'],
            ['key' => 'fixed_module_sub_title', 'value' => 'Enjoy all services in one platform', 'type' => 'admin_landing_page'],
        ];

        foreach ($landingPageSettings as $setting) {
            DB::table('data_settings')->updateOrInsert(
                ['key' => $setting['key'], 'type' => $setting['type']],
                [
                    'value' => $setting['value'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }

    protected function seedTierSettings()
    {
        // Tier threshold settings
        $tierSettings = [
            // Tier display names (default: Silver / Gold / Platinum)
            ['key' => 'tier_bronze_name', 'value' => 'Silver'],
            ['key' => 'tier_silver_name', 'value' => 'Gold'],
            ['key' => 'tier_gold_name', 'value' => 'Platinum'],
            ['key' => 'tier_bronze_max_points', 'value' => '100'],
            ['key' => 'tier_silver_min_points', 'value' => '101'],
            ['key' => 'tier_silver_max_points', 'value' => '500'],
            ['key' => 'tier_gold_min_points', 'value' => '501'],
            // Tier point value multipliers
            ['key' => 'tier_bronze_multiplier', 'value' => '1.0'],
            ['key' => 'tier_silver_multiplier', 'value' => '1.2'],
            ['key' => 'tier_gold_multiplier', 'value' => '1.5'],
        ];

        foreach ($tierSettings as $setting) {
            DB::table('business_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
}

