<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\CustomerLogic;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class D365IntegrationController extends Controller
{
    /**
     * Verify API token from Authorization header
     */
    private function verifyApiToken(Request $request)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return [
                'valid' => false,
                'message' => 'Missing API token'
            ];
        }

        // Get API token from business settings
        $apiToken = BusinessSetting::where('key', 'dynamics365_api_token')->first()?->value;
        
        if (!$apiToken || $token !== $apiToken) {
            return [
                'valid' => false,
                'message' => 'Invalid API token'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get customer by phone number
     * Endpoint: GET /api/v1/dynamics365/customer-by-phone
     */
    public function getCustomerByPhone(Request $request)
    {
        // Verify API token
        $tokenCheck = $this->verifyApiToken($request);
        if (!$tokenCheck['valid']) {
            return response()->json([
                'success' => false,
                'message' => $tokenCheck['message']
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => Helpers::error_processor($validator)
            ], 400);
        }

        $customer = User::where('phone', $request->phone)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
                'customer' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'customer' => [
                'customer_id' => $customer->id,
                'name' => trim(($customer->f_name ?? '') . ' ' . ($customer->l_name ?? '')),
                'phone' => $customer->phone,
                'email' => $customer->email,
                'current_points' => (int)($customer->loyalty_point ?? 0),
                'current_tier' => $customer->effective_tier ?? 'bronze',
                'tier_name' => ucfirst($customer->effective_tier ?? 'bronze'),
            ]
        ], 200);
    }

    /**
     * Add points after D365 approval (with optional points redemption)
     * Endpoint: POST /api/v1/dynamics365/add-points
     */
    public function addPoints(Request $request)
    {
        // Verify API token
        $tokenCheck = $this->verifyApiToken($request);
        if (!$tokenCheck['valid']) {
            return response()->json([
                'success' => false,
                'message' => $tokenCheck['message']
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'customer_phone' => 'required|string',
            'points_to_award' => 'required|integer|min:0',
            'purchase_amount' => 'required|numeric|min:0.01',
            'final_purchase_amount' => 'nullable|numeric|min:0',
            'points_used' => 'nullable|integer|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string',
            'd365_transaction_id' => 'nullable|string',
            'tier_multiplier' => 'nullable|numeric',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => Helpers::error_processor($validator)
            ], 400);
        }

        // Find customer
        $customer = User::where('phone', $request->customer_phone)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
                'errors' => [[
                    'code' => 'customer',
                    'message' => 'Customer not found'
                ]]
            ], 404);
        }

        // Get values with defaults
        $pointsToAward = (int)$request->points_to_award;
        $pointsUsed = (int)($request->points_used ?? 0);
        $finalPurchaseAmount = $request->final_purchase_amount ?? $request->purchase_amount;
        $reference = $request->reference ?? 'D365-' . now()->format('Y-m-d-H-i-s');
        $previousPoints = (int)($customer->loyalty_point ?? 0);
        $previousTier = $customer->effective_tier ?? 'bronze';

        // Validate customer has enough points if using points
        if ($pointsUsed > 0 && $previousPoints < $pointsUsed) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient points',
                'errors' => [[
                    'code' => 'points',
                    'message' => 'Customer does not have enough points. Available: ' . $previousPoints . ', Required: ' . $pointsUsed
                ]]
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Step 1: Deduct points if customer used points for discount
            if ($pointsUsed > 0) {
                $deductTransaction = CustomerLogic::create_loyalty_point_transaction(
                    user_id: $customer->id,
                    referance: $reference . '-REDEEM',
                    amount: $pointsUsed,
                    transaction_type: 'in_store_points_redeemed' // Deduct points without adding to wallet
                );

                if ($deductTransaction === false) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to deduct points',
                        'errors' => [[
                            'code' => 'transaction',
                            'message' => 'Failed to process points deduction'
                        ]]
                    ], 500);
                }

                // Refresh customer to get updated balance
                $customer->refresh();
            }

            // Step 2: Add points awarded
            if ($pointsToAward > 0) {
                $awardTransaction = CustomerLogic::create_loyalty_point_transaction(
                    user_id: $customer->id,
                    referance: $reference,
                    amount: $pointsToAward,
                    transaction_type: 'in_store_purchase'
                );

                if ($awardTransaction === false) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to add points',
                        'errors' => [[
                            'code' => 'transaction',
                            'message' => 'Failed to process points award'
                        ]]
                    ], 500);
                }

                // Refresh customer to get updated balance and tier
                $customer->refresh();
            }

            DB::commit();

            // Calculate net points change
            $netPointsChange = $pointsToAward - $pointsUsed;
            $tierUpgraded = $previousTier !== ($customer->effective_tier ?? 'bronze');

            return response()->json([
                'success' => true,
                'message' => 'Points added successfully',
                'points_awarded' => $pointsToAward,
                'points_used' => $pointsUsed,
                'net_points_change' => $netPointsChange,
                'customer' => [
                    'customer_id' => $customer->id,
                    'name' => trim(($customer->f_name ?? '') . ' ' . ($customer->l_name ?? '')),
                    'total_points' => (int)($customer->loyalty_point ?? 0),
                    'tier' => $customer->effective_tier ?? 'bronze',
                    'tier_name' => ucfirst($customer->effective_tier ?? 'bronze'),
                    'tier_upgraded' => $tierUpgraded,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add points via D365 API', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id ?? null,
                'points_to_award' => $pointsToAward,
                'points_used' => $pointsUsed,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process points transaction',
                'errors' => [[
                    'code' => 'exception',
                    'message' => 'An error occurred while processing the transaction'
                ]]
            ], 500);
        }
    }

    /**
     * Bulk sync customers from D365 to Laravel
     * Endpoint: POST /api/v1/dynamics365/bulk-sync-customers
     */
    public function bulkSyncCustomers(Request $request)
    {
        // Verify API token
        $tokenCheck = $this->verifyApiToken($request);
        if (!$tokenCheck['valid']) {
            return response()->json([
                'success' => false,
                'message' => $tokenCheck['message']
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'customers' => 'required|array|min:1|max:500',
            'customers.*.phone' => 'required|string',
            'customers.*.firstname' => 'nullable|string|max:100',
            'customers.*.lastname' => 'nullable|string|max:100',
            'customers.*.email' => 'nullable|email|max:100',
            'customers.*.loyalty_points' => 'nullable|integer|min:0',
            'customers.*.tier' => 'nullable|string|in:bronze,silver,gold',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => Helpers::error_processor($validator)
            ], 400);
        }

        $results = [];
        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($request->customers as $customerData) {
            try {
                $phone = $customerData['phone'];
                $user = User::where('phone', $phone)->first();

                if ($user) {
                    // Update existing customer
                    $user->f_name = $customerData['firstname'] ?? $user->f_name;
                    $user->l_name = $customerData['lastname'] ?? $user->l_name;
                    $user->email = $customerData['email'] ?? $user->email;
                    $user->loyalty_point = $customerData['loyalty_points'] ?? $user->loyalty_point;
                    
                    if (isset($customerData['tier'])) {
                        $user->tier = $customerData['tier'];
                    }
                    
                    $user->save();
                    $user->updateTier();
                    
                    $results[] = [
                        'phone' => $phone,
                        'status' => 'updated',
                        'customer_id' => $user->id
                    ];
                    $updated++;
                } else {
                    // Create new customer
                    $user = new User();
                    $user->phone = $phone;
                    $user->f_name = $customerData['firstname'] ?? '';
                    $user->l_name = $customerData['lastname'] ?? '';
                    $user->email = $customerData['email'] ?? null;
                    $user->loyalty_point = $customerData['loyalty_points'] ?? 0;
                    $user->tier = $customerData['tier'] ?? 'bronze';
                    $user->status = 1;
                    $user->is_phone_verified = 1;
                    $user->save();
                    $user->updateTier();
                    
                    $results[] = [
                        'phone' => $phone,
                        'status' => 'created',
                        'customer_id' => $user->id
                    ];
                    $created++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync customer in bulk sync', [
                    'phone' => $customerData['phone'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                
                $results[] = [
                    'phone' => $customerData['phone'] ?? 'unknown',
                    'status' => 'failed',
                    'error' => 'Failed to sync customer: ' . $e->getMessage()
                ];
                $failed++;
            }
        }

        $message = $failed > 0 
            ? 'Bulk sync completed with some failures'
            : 'Bulk sync completed';

        return response()->json([
            'success' => true,
            'message' => $message,
            'total' => count($request->customers),
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'results' => $results
        ], 200);
    }
}

