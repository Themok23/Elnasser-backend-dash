<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SafeOrderSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['orders', 'users', 'stores', 'modules'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->command?->warn("Missing table: {$table}");
                return;
            }
        }

        $moduleId = DB::table('modules')->value('id');
        if (!$moduleId) {
            $this->command?->warn('No module found in modules table.');
            return;
        }

        $userIds = DB::table('users')->pluck('id')->toArray();
        $stores = DB::table('stores')->select('id', 'zone_id')->get()->toArray();

        if (empty($userIds)) {
            $this->command?->warn('No users found. Run UserSeeder first.');
            return;
        }
        if (empty($stores)) {
            $this->command?->warn('No stores found. Seed stores first.');
            return;
        }

        $count = (int) env('SEED_ORDERS_COUNT', 200);
        $statuses = ['pending', 'confirmed', 'processing', 'handover', 'picked_up', 'delivered', 'canceled', 'failed'];
        $now = now();

        $hasOrderDetails = Schema::hasTable('order_details') && Schema::hasTable('items');
        $itemIds = $hasOrderDetails ? DB::table('items')->pluck('id')->toArray() : [];

        for ($i = 0; $i < $count; $i++) {
            $userId = $userIds[array_rand($userIds)];
            $store = $stores[array_rand($stores)];

            $orderId = DB::table('orders')->insertGetId([
                'user_id' => $userId,
                'store_id' => $store->id,
                'zone_id' => $store->zone_id ?? 1,
                'module_id' => $moduleId, // fixes orders_module_id_foreign
                'order_amount' => rand(100, 1500),
                'order_status' => $statuses[array_rand($statuses)],
                'store_discount_amount' => rand(0, 50),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Optional: add order details so /customer/order/details shows items
            if ($hasOrderDetails && !empty($itemIds)) {
                $detailsCount = rand(1, min(3, count($itemIds)));
                for ($d = 0; $d < $detailsCount; $d++) {
                    $itemId = $itemIds[array_rand($itemIds)];
                    DB::table('order_details')->insert([
                        'order_id' => $orderId,
                        'item_id' => $itemId,
                        'quantity' => rand(1, 5),
                        'price' => rand(20, 300),
                        'discount_on_item' => 0,
                        'total_add_on_price' => 0,
                        'tax_amount' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        $this->command?->info("Seeded {$count} orders successfully (module_id={$moduleId}).");
    }
}


