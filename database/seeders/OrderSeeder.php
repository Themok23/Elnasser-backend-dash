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
        $stores = Store::pluck('id')->toArray();
        $zones = Zone::pluck('id')->toArray();
        $modules = Module::pluck('id')->toArray();
        $items = Item::pluck('id')->toArray();

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

        // Get users with their order_count
        $usersWithCounts = User::whereNotNull('password')
            ->where('order_count', '>', 0)
            ->get(['id', 'order_count', 'zone_id']);

        $this->command->info('Found ' . $usersWithCounts->count() . ' users with order_count > 0');

        if ($usersWithCounts->isEmpty()) {
            $this->command->warn('No users with order_count found. Skipping order creation.');
            return;
        }

        $totalOrdersCreated = 0;

        // Create orders for each user based on their order_count
        foreach ($usersWithCounts as $user) {
            $userOrderCount = (int) $user->order_count;
            $this->command->info("Creating {$userOrderCount} orders for user {$user->id}...");

            // Reset user's order_count to 0, we'll recreate them
            $user->order_count = 0;
            $user->save();

            $userZoneId = $user->zone_id ?? ($zones[array_rand($zones)] ?? null);
            $storeId = $stores[array_rand($stores)];
            $zoneId = $userZoneId ?? $zones[array_rand($zones)];
            $moduleId = $modules[array_rand($modules)];

            for ($i = 0; $i < $userOrderCount; $i++) {
                // Calculate order amounts
                $itemSubtotal = rand(50, 500);
                $storeDiscount = rand(0, 50);
                $couponDiscount = rand(0, 30);
                $deliveryCharge = rand(10, 50);
                $taxAmount = round($itemSubtotal * 0.14, 2);
                $orderAmount = $itemSubtotal - $storeDiscount - $couponDiscount + $deliveryCharge + $taxAmount;

                $orderStatus = $orderStatuses[array_rand($orderStatuses)];
                $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
                $paymentMethod = $paymentMethods[array_rand($paymentMethods)];

                // Create order using new Order() instead of Order::create() to avoid mass assignment issues
                $order = new Order();
                $order->user_id = $user->id;
                $order->store_id = $storeId;
                $order->zone_id = $zoneId;
                $order->module_id = $moduleId;
                $order->order_amount = round($orderAmount, 2);
                $order->store_discount_amount = $storeDiscount;
                $order->coupon_discount_amount = $couponDiscount > 0 ? $couponDiscount : 0;
                $order->coupon_code = $couponDiscount > 0 ? 'TEST' . rand(1000, 9999) : null;
                $order->total_tax_amount = $taxAmount;
                $order->delivery_charge = $deliveryCharge;
                $order->original_delivery_charge = $deliveryCharge;
                $order->order_status = $orderStatus;
                $order->payment_status = $paymentStatus;
                $order->payment_method = $paymentMethod;
                $order->order_type = rand(0, 1) ? 'delivery' : 'take_away';
                $order->delivery_address_id = null;
                $order->delivery_man_id = null;
                $order->scheduled = rand(0, 1);
                $order->distance = round(rand(1, 20) + (rand(0, 99) / 100), 2);
                $order->processing_time = rand(15, 60);
                $order->cutlery = rand(0, 1);
                $order->prescription_order = 0;
                $order->is_guest = 0;
                $order->additional_charge = 0;
                $order->dm_tips = rand(0, 1) ? round(rand(5, 20), 2) : 0;
                $order->ref_bonus_amount = 0;
                $order->bring_change_amount = $paymentMethod === 'cash_on_delivery' ? rand(0, 50) : 0;
                $order->created_at = Carbon::now()->subDays(rand(0, 30));
                $order->updated_at = Carbon::now()->subDays(rand(0, 30));
                $order->save();

                // Create order details
                if (!empty($items)) {
                    $detailsCount = rand(1, min(5, count($items)));
                    $itemsForOrder = [];

                    if ($detailsCount == 1) {
                        $itemsForOrder = [array_rand($items)];
                    } else {
                        $itemsForOrder = array_rand($items, $detailsCount);
                    }

                    if (!is_array($itemsForOrder)) {
                        $itemsForOrder = [$itemsForOrder];
                    }

                    foreach ($itemsForOrder as $itemKey) {
                        $itemId = $items[$itemKey];
                        $item = Item::find($itemId);

                        if ($item) {
                            $quantity = rand(1, 5);
                            $price = $item->price ?? rand(10, 100);
                            $discountOnItem = rand(0, 10);
                            $taxAmount = round(($price * $quantity - $discountOnItem) * 0.14, 2);

                            $orderDetail = new OrderDetail();
                            $orderDetail->order_id = $order->id;
                            $orderDetail->item_id = $itemId;
                            $orderDetail->item_campaign_id = null;
                            $orderDetail->quantity = $quantity;
                            $orderDetail->price = $price;
                            $orderDetail->discount_on_item = $discountOnItem;
                            $orderDetail->total_add_on_price = 0;
                            $orderDetail->tax_amount = $taxAmount;
                            $orderDetail->created_at = $order->created_at;
                            $orderDetail->updated_at = $order->updated_at;
                            $orderDetail->save();
                        }
                    }
                }

                $totalOrdersCreated++;
            }

            // Update user order count to match created orders
            $actualOrderCount = Order::where('user_id', $user->id)->count();
            $user->order_count = $actualOrderCount;
            $user->save();

            $this->command->info("Created {$actualOrderCount} orders for user {$user->id}");
        }

        $this->command->info("Successfully seeded {$totalOrdersCreated} orders with details for " . $usersWithCounts->count() . " users");
    }
}
