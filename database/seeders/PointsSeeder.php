<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LoyaltyPointTransaction;
use App\Models\WalletTransaction;
use App\Models\OrderTransaction;
use App\Models\User;
use App\Models\Order;
use App\Models\DeliveryMan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PointsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Seeding points and transactions...');

        $users = User::whereNotNull('password')->get();
        $orders = Order::whereIn('order_status', ['delivered', 'confirmed'])->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please seed users first.');
            return;
        }

        // Seed Loyalty Point Transactions
        $this->seedLoyaltyPoints($users, $orders);

        // Seed Wallet Transactions
        $this->seedWalletTransactions($users, $orders);

        // Seed Order Transactions
        $this->seedOrderTransactions($orders);

        $this->command->info('Points and transactions seeding completed!');
    }

    protected function seedLoyaltyPoints($users, $orders)
    {
        $this->command->info('Seeding loyalty point transactions...');

        $loyaltyPointRate = 1; // 1 point per 1 currency unit (adjust based on your business logic)
        $transactionCount = 0;

        foreach ($users as $user) {
            $balance = $user->loyalty_point ?? 0;

            // Create transactions for orders
            $userOrders = $orders->where('user_id', $user->id)->take(rand(3, 10));

            foreach ($userOrders as $order) {
                if ($order->order_status === 'delivered') {
                    // Credit points for order
                    $pointsEarned = round($order->order_amount * $loyaltyPointRate);
                    $balance += $pointsEarned;

                    LoyaltyPointTransaction::create([
                        'user_id' => $user->id,
                        'transaction_id' => Str::uuid()->toString(),
                        'credit' => $pointsEarned,
                        'debit' => 0,
                        'balance' => $balance,
                        'transaction_type' => 'order_place',
                        'reference' => 'order_' . $order->id,
                        'created_at' => $order->created_at ?? now(),
                    ]);

                    $transactionCount++;
                }

                // Random point redemption
                if (rand(0, 1) && $balance > 50) {
                    $pointsRedeemed = rand(10, min(50, $balance));
                    $balance -= $pointsRedeemed;

                    LoyaltyPointTransaction::create([
                        'user_id' => $user->id,
                        'transaction_id' => Str::uuid()->toString(),
                        'credit' => 0,
                        'debit' => $pointsRedeemed,
                        'balance' => $balance,
                        'transaction_type' => 'point_to_wallet',
                        'reference' => 'redemption_' . $order->id,
                        'created_at' => $order->created_at ?? now(),
                    ]);

                    $transactionCount++;
                }
            }

            // Update user loyalty points and tier
            $user->loyalty_point = $balance;
            $user->save();
            $user->updateTier();
        }

        $this->command->info("Created {$transactionCount} loyalty point transactions");
    }

    protected function seedWalletTransactions($users, $orders)
    {
        $this->command->info('Seeding wallet transactions...');

        $transactionCount = 0;

        foreach ($users as $user) {
            $balance = $user->wallet_balance ?? 0;

            // Wallet top-ups
            $topUpCount = rand(2, 5);
            for ($i = 0; $i < $topUpCount; $i++) {
                $amount = rand(100, 1000);
                $balance += $amount;

                WalletTransaction::create([
                    'user_id' => $user->id,
                    'transaction_id' => Str::uuid()->toString(),
                    'reference' => 'top_up_' . ($i + 1),
                    'transaction_type' => 'add_fund',
                    'debit' => 0,
                    'credit' => $amount,
                    'admin_bonus' => 0,
                    'balance' => $balance,
                    'created_at' => Carbon::now()->subDays(rand(0, 30))->format('Y-m-d H:i:s'),
                ]);

                $transactionCount++;
            }

            // Wallet payments for orders
            $userOrders = $orders->where('user_id', $user->id)->where('payment_method', 'wallet_payment')->take(rand(2, 8));

            foreach ($userOrders as $order) {
                if ($balance >= $order->order_amount) {
                    $balance -= $order->order_amount;

                    WalletTransaction::create([
                        'user_id' => $user->id,
                        'transaction_id' => Str::uuid()->toString(),
                        'reference' => 'order_' . $order->id,
                        'transaction_type' => 'order_place',
                        'debit' => $order->order_amount,
                        'credit' => 0,
                        'admin_bonus' => 0,
                        'balance' => $balance,
                        'created_at' => $order->created_at ?? now()->format('Y-m-d H:i:s'),
                    ]);

                    $transactionCount++;
                }
            }

            // Refunds
            $refundCount = rand(0, 2);
            for ($i = 0; $i < $refundCount; $i++) {
                $refundAmount = rand(50, 200);
                $balance += $refundAmount;

                WalletTransaction::create([
                    'user_id' => $user->id,
                    'transaction_id' => Str::uuid()->toString(),
                    'reference' => 'refund_' . ($i + 1),
                    'transaction_type' => 'refund',
                    'debit' => 0,
                    'credit' => $refundAmount,
                    'admin_bonus' => 0,
                    'balance' => $balance,
                    'created_at' => Carbon::now()->subDays(rand(0, 20))->format('Y-m-d H:i:s'),
                ]);

                $transactionCount++;
            }

            // Update user wallet balance
            $user->wallet_balance = $balance;
            $user->save();
        }

        $this->command->info("Created {$transactionCount} wallet transactions");
    }

    protected function seedOrderTransactions($orders)
    {
        $this->command->info('Seeding order transactions...');

        $deliveryMen = DeliveryMan::pluck('id')->toArray();
        $transactionCount = 0;

        foreach ($orders as $order) {
            if (in_array($order->order_status, ['delivered', 'confirmed'])) {
                $deliveryManId = !empty($deliveryMen) ? $deliveryMen[array_rand($deliveryMen)] : null;

                // Calculate store amount (order amount - admin commission - delivery charge)
                $adminCommission = round($order->order_amount * 0.10, 2); // 10% commission
                $storeAmount = $order->order_amount - $adminCommission - ($order->delivery_charge ?? 0);
                $deliveryManFee = $order->delivery_charge ?? 0;

                OrderTransaction::create([
                    'vendor_id' => $order->store_id,
                    'delivery_man_id' => $deliveryManId,
                    'order_id' => $order->id,
                    'order_amount' => $order->order_amount,
                    'store_amount' => $storeAmount,
                    'admin_commission' => $adminCommission,
                    'delivery_charge' => $order->delivery_charge ?? 0,
                    'original_delivery_charge' => $order->original_delivery_charge ?? 0,
                    'tax' => $order->total_tax_amount ?? 0,
                    'dm_tips' => $order->dm_tips ?? 0,
                    'delivery_fee_comission' => 0,
                    'admin_expense' => 0,
                    'module_id' => $order->module_id,
                    'zone_id' => $order->zone_id,
                    'status' => 'disburse',
                    'created_at' => $order->created_at ?? now(),
                    'updated_at' => $order->updated_at ?? now(),
                ]);

                $transactionCount++;
            }
        }

        $this->command->info("Created {$transactionCount} order transactions");
    }
}

