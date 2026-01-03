<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\CustomerLogic;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dynamics365Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Brian2694\Toastr\Facades\Toastr;

class InStorePurchaseController extends Controller
{
    /**
     * Check purchase eligibility with Dynamics 365
     * Employee scans customer QR code (phone number) and enters purchase amount
     * Checks Dynamics 365 FIRST, then Laravel if needed
     * Dynamics 365 decides if points should be awarded
     */
    public function checkPurchaseWithDynamics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_phone' => 'required|string', // Phone number from QR code
            'purchase_amount' => 'required|numeric|min:0.01',
            'store_id' => 'nullable|string',
            'employee_id' => 'nullable|string',
            'reference' => 'nullable|string', // Optional: invoice/receipt number
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => Helpers::error_processor($validator)
            ], 403);
        }

        $dynamicsService = new Dynamics365Service();
        
        if (!$dynamicsService->isEnabled()) {
            return response()->json([
                'errors' => [[
                    'code' => 'dynamics365',
                    'message' => translate('messages.dynamics365_not_configured')
                ]]
            ], 403);
        }

        // STEP 1: Check Dynamics 365 FIRST (source of truth)
        $d365Customer = $dynamicsService->getCustomerFromD365($request->customer_phone);
        
        $customerData = null;
        $d365ContactId = null;

        if ($d365Customer) {
            // Customer exists in D365 - use D365 data
            $d365ContactId = $d365Customer['contactid'];
            $customerData = [
                'name' => trim(($d365Customer['firstname'] ?? '') . ' ' . ($d365Customer['lastname'] ?? '')),
                'points' => $d365Customer['new_loyaltypoints'] ?? 0,
                'tier' => $d365Customer['new_tier'] ?? 'bronze',
                'd365_contact_id' => $d365ContactId,
            ];
            
            // Sync customer data FROM D365 TO Laravel (ensure Laravel has latest data)
            $dynamicsService->syncCustomerFromD365($d365ContactId);
            
        } else {
            // Customer NOT in D365 - check Laravel
            $laravelCustomer = User::where('phone', $request->customer_phone)->first();
            
            if (!$laravelCustomer) {
                return response()->json([
                    'errors' => [[
                        'code' => 'customer',
                        'message' => translate('messages.customer_not_found')
                    ]]
                ], 404);
            }
            
            // Customer exists in Laravel but not D365 - sync TO D365
            $syncResult = $dynamicsService->syncCustomer($laravelCustomer);
            
            if (!$syncResult) {
                return response()->json([
                    'errors' => [[
                        'code' => 'sync_error',
                        'message' => translate('messages.failed_to_sync_to_dynamics365')
                    ]]
                ], 500);
            }
            
            // Get customer from D365 again (after creation)
            $d365Customer = $dynamicsService->getCustomerFromD365($request->customer_phone);
            
            if (!$d365Customer) {
                return response()->json([
                    'errors' => [[
                        'code' => 'sync_error',
                        'message' => translate('messages.failed_to_retrieve_customer_from_dynamics365')
                    ]]
                ], 500);
            }
            
            $d365ContactId = $d365Customer['contactid'];
            $customerData = [
                'name' => $laravelCustomer->f_name . ' ' . $laravelCustomer->l_name,
                'points' => $laravelCustomer->loyalty_point ?? 0,
                'tier' => $laravelCustomer->tier_level ?? 'bronze',
                'd365_contact_id' => $d365ContactId,
            ];
        }

        // STEP 2: Check purchase eligibility with Dynamics 365
        $result = $dynamicsService->checkPurchaseEligibility(
            d365ContactId: $d365ContactId,
            phone: $request->customer_phone,
            purchaseAmount: $request->purchase_amount,
            storeId: $request->store_id,
            employeeId: $request->employee_id ?? auth('admin')->id(),
            reference: $request->reference
        );

        // Return Dynamics 365 decision
        return response()->json([
            'approved' => $result['approved'] ?? false,
            'points_to_award' => $result['points_to_award'] ?? 0,
            'reason' => $result['reason'] ?? 'No reason provided',
            'tier_multiplier' => $result['tier_multiplier'] ?? 1.0,
            'final_points' => $result['final_points'] ?? 0,
            'dynamics365_transaction_id' => $result['dynamics365_transaction_id'] ?? null,
            'customer_info' => $result['customer_info'] ?? [
                'name' => $customerData['name'],
                'current_points' => $customerData['points'],
                'current_tier' => $customerData['tier'],
            ],
            'purchase_amount' => $request->purchase_amount,
        ], 200);
    }

    /**
     * Add points after Dynamics 365 approval
     * This endpoint is called after Dynamics 365 approves the purchase
     */
    public function addPointsAfterApproval(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_phone' => 'required|string', // Phone number from QR code
            'points_to_award' => 'required|integer|min:0',
            'purchase_amount' => 'required|numeric|min:0.01',
            'dynamics365_transaction_id' => 'nullable|string',
            'reference' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => Helpers::error_processor($validator)
            ], 403);
        }

        // Find customer by phone
        $customer = User::where('phone', $request->customer_phone)->first();

        if (!$customer) {
            return response()->json([
                'errors' => [[
                    'code' => 'customer',
                    'message' => translate('messages.customer_not_found')
                ]]
            ], 404);
        }

        // Check if points should be awarded
        if ($request->points_to_award <= 0) {
            return response()->json([
                'success' => true,
                'message' => translate('messages.no_points_awarded'),
                'points_awarded' => 0,
                'customer' => [
                    'name' => $customer->f_name . ' ' . $customer->l_name,
                    'total_points' => $customer->loyalty_point ?? 0,
                    'tier' => $customer->tier_level ?? 'bronze',
                ]
            ], 200);
        }

        try {
            // Add points transaction
            $reference = $request->reference ?? 'IN-STORE-' . now()->format('Y-m-d-H-i-s');
            
            // Use purchase_amount as reference amount, but points_to_award as the actual points
            // We'll pass points_to_award as the amount parameter
            $transaction = CustomerLogic::create_loyalty_point_transaction(
                user_id: $customer->id,
                referance: $reference,
                amount: $request->points_to_award, // Pass points as amount for in_store_purchase type
                transaction_type: 'in_store_purchase'
            );

            if ($transaction === false) {
                return response()->json([
                    'errors' => [[
                        'code' => 'transaction',
                        'message' => translate('messages.failed_to_add_points')
                    ]]
                ], 500);
            }

            // Refresh customer to get updated points and tier
            $customer->refresh();

            // Sync updated points FROM Dynamics 365 TO Laravel (D365 is source of truth)
            $dynamicsService = new Dynamics365Service();
            if ($dynamicsService->isEnabled()) {
                // Get latest data from D365
                $d365Customer = $dynamicsService->getCustomerFromD365($request->customer_phone);
                if ($d365Customer) {
                    // Sync latest points and tier from D365
                    $dynamicsService->syncCustomerFromD365($d365Customer['contactid']);
                    $customer->refresh();
                }
                
                // Confirm to Dynamics 365 that points were added
                if (!empty($request->dynamics365_transaction_id)) {
                    $dynamicsService->confirmPointsAdded(
                        dynamics365TransactionId: $request->dynamics365_transaction_id,
                        pointsAwarded: $request->points_to_award,
                        customerId: $customer->id
                    );
                }
            }

            // Get previous tier from request if provided
            $previousTier = $request->previous_tier ?? 'bronze';
            
            return response()->json([
                'success' => true,
                'message' => translate('messages.points_added_successfully'),
                'points_awarded' => $request->points_to_award,
                'customer' => [
                    'name' => trim(($customer->f_name ?? '') . ' ' . ($customer->l_name ?? '')),
                    'total_points' => $customer->loyalty_point ?? 0,
                    'tier' => $customer->tier_level ?? 'bronze',
                    'previous_tier' => $previousTier,
                ],
                'tier_upgraded' => $previousTier !== ($customer->tier_level ?? 'bronze'),
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to add points after Dynamics 365 approval', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id,
                'points' => $request->points_to_award
            ]);

            return response()->json([
                'errors' => [[
                    'code' => 'exception',
                    'message' => translate('messages.failed_to_add_points')
                ]]
            ], 500);
        }
    }

    /**
     * Get customer info by phone number
     * Used by employee to verify customer before scanning
     * Checks Dynamics 365 first, then Laravel
     */
    public function getCustomerByPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => Helpers::error_processor($validator)
            ], 403);
        }

        $dynamicsService = new Dynamics365Service();
        $customerData = null;

        // Check Dynamics 365 first
        if ($dynamicsService->isEnabled()) {
            $d365Customer = $dynamicsService->getCustomerFromD365($request->customer_phone);
            
            if ($d365Customer) {
                // Sync from D365 to Laravel
                $dynamicsService->syncCustomerFromD365($d365Customer['contactid']);
                $customerData = [
                    'source' => 'dynamics365',
                    'd365_contact_id' => $d365Customer['contactid'],
                ];
            }
        }

        // Get customer from Laravel (may have been synced from D365)
        $customer = User::where('phone', $request->customer_phone)->first();

        if (!$customer) {
            return response()->json([
                'errors' => [[
                    'code' => 'customer',
                    'message' => translate('messages.customer_not_found')
                ]]
            ], 404);
        }

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => trim(($customer->f_name ?? '') . ' ' . ($customer->l_name ?? '')),
                'phone' => $customer->phone,
                'email' => $customer->email,
                'ref_code' => $customer->ref_code,
                'current_points' => $customer->loyalty_point ?? 0,
                'current_tier' => $customer->tier_level ?? 'bronze',
                'tier_name' => \App\Models\User::tierDisplayName($customer->tier_level ?? 'bronze'),
                'd365_contact_id' => $customerData['d365_contact_id'] ?? null,
            ]
        ], 200);
    }
}

