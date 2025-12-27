<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use App\Models\Store;
use App\Models\Zone;
use App\Models\Module;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Seeding orders...');

        // Check dependencies
        $users = User::whereNotNull('password')->pluck('id')->toArray();
        $stores = Store::pluck('id')->toArray();
        $zones = Zone::pluck('id')->toArray();
        $modules = Module::pluck('id')->toArray();
        $items = Item::pluck('id')->toArray();

        if (empty($users)) {
            $this->command->warn('No users found. Please seed users first.');
            return;
        }

        if (empty($stores)) {
            $this->command->warn('No stores found. Orders require stores.');
            return;
        }

        if (empty($zones)) {
            $this->command->warn('No zones found. Orders require zones.');
            return;
        }

        if (empty($modules)) {
            $this->command->warn('No modules found. Orders require modules.');
            return;
        }

        // Order statuses
        $orderStatuses = ['pending', 'confirmed', 'processing', 'handover', 'picked_up', 'delivered', 'canceled', 'failed'];
        $paymentStatuses = ['unpaid', 'paid', 'partial'];
        $paymentMethods = ['cash_on_delivery', 'digital_payment', 'wallet_payment'];

        // Create orders
        $ordersToCreate = 50; // Adjust as needed
        $createdOrders = [];

        for ($i = 0; $i < $ordersToCreate; $i++) {
            $userId = $users[array_rand($users)];
            $storeId = $stores[array_rand($stores)];
            $zoneId = $zones[array_rand($zones)];
            $moduleId = $modules[array_rand($modules)];

            // Calculate order amounts
            $itemSubtotal = rand(50, 500);
            $storeDiscount = rand(0, 50);
            $couponDiscount = rand(0, 30);
            $deliveryCharge = rand(10, 50);
            $taxAmount = round($itemSubtotal * 0.14, 2); // 14% tax
            $orderAmount = $itemSubtotal - $storeDiscount - $couponDiscount + $deliveryCharge + $taxAmount;

            $orderStatus = $orderStatuses[array_rand($orderStatuses)];
            $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];

            // Create order
            $order = Order::create([
                'user_id' => $userId,
                'store_id' => $storeId,
                'zone_id' => $zoneId,
                'module_id' => $moduleId,
                'order_amount' => round($orderAmount, 2),
                'store_discount_amount' => $storeDiscount,
                'coupon_discount_amount' => $couponDiscount > 0 ? $couponDiscount : 0,
                'coupon_code' => $couponDiscount > 0 ? 'TEST' . rand(1000, 9999) : null,
                'total_tax_amount' => $taxAmount,
                'delivery_charge' => $deliveryCharge,
                'original_delivery_charge' => $deliveryCharge,
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'order_type' => rand(0, 1) ? 'delivery' : 'take_away',
                'delivery_address_id' => null,
                'delivery_man_id' => null,
                'scheduled' => rand(0, 1),
                'distance' => round(rand(1, 20) + (rand(0, 99) / 100), 2),
                'processing_time' => rand(15, 60),
                'cutlery' => rand(0, 1),
                'prescription_order' => 0,
                'is_guest' => 0,
                'additional_charge' => 0,
                'dm_tips' => rand(0, 1) ? round(rand(5, 20), 2) : 0,
                'ref_bonus_amount' => 0,
                'bring_change_amount' => $paymentMethod === 'cash_on_delivery' ? rand(0, 50) : 0,
                'created_at' => Carbon::now()->subDays(rand(0, 30)),
                'updated_at' => Carbon::now()->subDays(rand(0, 30)),
            ]);

            $createdOrders[] = $order;

            // Create order details
            $detailsCount = rand(1, 5);
            $itemsForOrder = !empty($items) ? array_rand($items, min($detailsCount, count($items))) : [];
            if (!is_array($itemsForOrder)) {
                $itemsForOrder = [$itemsForOrder];
            }

            foreach ($itemsForOrder as $itemIndex => $itemKey) {
                $itemId = $items[$itemKey];
                $item = Item::find($itemId);

                if ($item) {
                    $quantity = rand(1, 5);
                    $price = $item->price ?? rand(10, 100);
                    $discountOnItem = rand(0, 10);
                    $taxAmount = round(($price * $quantity - $discountOnItem) * 0.14, 2);

                    OrderDetail::create([
                        'order_id' => $order->id,
                        'item_id' => $itemId,
                        'item_campaign_id' => null,
                        'quantity' => $quantity,
                        'price' => $price,
                        'discount_on_item' => $discountOnItem,
                        'total_add_on_price' => 0,
                        'tax_amount' => $taxAmount,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ]);
                }
            }

            // Update user order count
            $user = User::find($userId);
            if ($user) {
                $user->increment('order_count');
            }
        }

        $this->command->info("Seeded " . count($createdOrders) . " orders with details");
    }
}
