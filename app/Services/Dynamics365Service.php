<?php

namespace App\Services;

use App\Models\ExternalConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Dynamics365Service
{
    private $baseUrl;
    private $clientId;
    private $clientSecret;
    private $tenantId;
    private $accessToken;
    private $enabled;

    public function __construct()
    {
        // Check .env first, then fall back to ExternalConfiguration
        $this->enabled = (int)(env('D365_ENABLED', ExternalConfiguration::where('key', 'dynamics365_enabled')->first()?->value ?? 0));
        $this->baseUrl = env('D365_BASE_URL', ExternalConfiguration::where('key', 'dynamics365_base_url')->first()?->value);
        $this->clientId = env('D365_CLIENT_ID', ExternalConfiguration::where('key', 'dynamics365_client_id')->first()?->value);
        $this->clientSecret = env('D365_CLIENT_SECRET', ExternalConfiguration::where('key', 'dynamics365_client_secret')->first()?->value);
        $this->tenantId = env('D365_TENANT_ID', ExternalConfiguration::where('key', 'dynamics365_tenant_id')->first()?->value);
    }

    /**
     * Check if Dynamics 365 integration is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled == 1 &&
               !empty($this->baseUrl) &&
               !empty($this->clientId) &&
               !empty($this->clientSecret) &&
               !empty($this->tenantId);
    }

    /**
     * Authenticate with Dynamics 365 using OAuth 2.0 Client Credentials flow
     */
    public function authenticate(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $response = Http::asForm()->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://org.api.crm.dynamics.com/.default',
                'grant_type' => 'client_credentials',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'] ?? null;
                return !empty($this->accessToken);
            }

            Log::error('Dynamics 365 authentication failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Dynamics 365 authentication exception', [
                'message' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get customer from Dynamics 365 by phone number
     *
     * @param string $phone Customer phone number
     * @return array|null Customer data from Dynamics 365 or null if not found
     */
    public function getCustomerFromD365(string $phone): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return null;
            }
        }

        try {
            $endpoint = rtrim($this->baseUrl, '/') . '/api/data/v9.2/contacts';

            // Query Dynamics 365 by phone number
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($endpoint, [
                    '$filter' => "mobilephone eq '{$phone}'",
                    '$select' => 'contactid,firstname,lastname,emailaddress1,mobilephone,new_loyaltypoints,new_tier,new_tierlevel'
                ]);

            if ($response->successful() && !empty($response->json()['value'])) {
                return $response->json()['value'][0];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get customer from Dynamics 365', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Sync customer data FROM Dynamics 365 TO Laravel
     * Updates Laravel user with latest D365 data (points, tier, etc.)
     *
     * @param string $d365ContactId Dynamics 365 contact ID
     * @return bool Success status
     */
    public function syncCustomerFromD365(string $d365ContactId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return false;
            }
        }

        try {
            // Get customer from D365
            $endpoint = rtrim($this->baseUrl, '/') . '/api/data/v9.2/contacts';

            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$endpoint}({$d365ContactId})", [
                    '$select' => 'contactid,firstname,lastname,emailaddress1,mobilephone,new_loyaltypoints,new_tier,new_tierlevel'
                ]);

            if (!$response->successful()) {
                return false;
            }

            $d365Customer = $response->json();

            // Find or create user in Laravel
            $user = User::where('phone', $d365Customer['mobilephone'])->first();

            if (!$user) {
                // Create new user from D365 data
                $user = User::create([
                    'f_name' => $d365Customer['firstname'] ?? '',
                    'l_name' => $d365Customer['lastname'] ?? '',
                    'email' => $d365Customer['emailaddress1'] ?? null,
                    'phone' => $d365Customer['mobilephone'],
                    'loyalty_point' => $d365Customer['new_loyaltypoints'] ?? 0,
                    'tier' => $d365Customer['new_tier'] ?? 'bronze',
                    'is_phone_verified' => 1, // From D365, assume verified
                ]);
            } else {
                // Update existing user with D365 data
                $user->f_name = $d365Customer['firstname'] ?? $user->f_name;
                $user->l_name = $d365Customer['lastname'] ?? $user->l_name;
                $user->email = $d365Customer['emailaddress1'] ?? $user->email;
                $user->loyalty_point = $d365Customer['new_loyaltypoints'] ?? 0;
                $user->tier = $d365Customer['new_tier'] ?? 'bronze';
                $user->save();
                $user->updateTier(); // Recalculate tier if needed
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to sync customer from Dynamics 365', [
                'contact_id' => $d365ContactId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check purchase eligibility with Dynamics 365
     * Dynamics 365 will decide if points should be awarded and how many
     *
     * @param string $d365ContactId Dynamics 365 contact ID
     * @param string $phone Customer phone number (for reference)
     * @param float $purchaseAmount Purchase amount
     * @param string|null $storeId Store ID
     * @param string|null $employeeId Employee ID
     * @param string|null $reference Optional reference/invoice number
     * @return array Response from Dynamics 365
     */
    public function checkPurchaseEligibility(
        string $d365ContactId,
        string $phone,
        float $purchaseAmount,
        ?string $storeId = null,
        ?string $employeeId = null,
        ?string $reference = null
    ): array {
        if (!$this->isEnabled()) {
            return [
                'approved' => false,
                'reason' => 'Dynamics 365 integration is not enabled'
            ];
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return [
                    'approved' => false,
                    'reason' => 'Failed to authenticate with Dynamics 365'
                ];
            }
        }

        try {
            // Prepare data to send to Dynamics 365
            $requestData = [
                'd365_contact_id' => $d365ContactId,  // Primary identifier
                'customer_phone' => $phone,           // For reference
                'purchase_amount' => $purchaseAmount,
                'store_id' => $storeId,
                'employee_id' => $employeeId,
                'reference' => $reference,
                'timestamp' => now()->toIso8601String(),
            ];

            // Send request to Dynamics 365
            // Note: The endpoint URL should be configured in Dynamics 365
            // This is a placeholder - you'll need to update it with your actual endpoint
            $endpoint = rtrim($this->baseUrl, '/') . '/api/data/v9.2/CheckPurchaseEligibility';

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($endpoint, $requestData);

            if ($response->successful()) {
                $responseData = $response->json();

                // Get customer data from Laravel for response (if exists)
                $customer = User::where('phone', $phone)->first();

                // Ensure response has required fields
                return [
                    'approved' => $responseData['approved'] ?? false,
                    'points_to_award' => $responseData['points_to_award'] ?? 0,
                    'reason' => $responseData['reason'] ?? 'No reason provided',
                    'tier_multiplier' => $responseData['tier_multiplier'] ?? 1.0,
                    'final_points' => $responseData['final_points'] ?? ($responseData['points_to_award'] ?? 0),
                    'dynamics365_transaction_id' => $responseData['dynamics365_transaction_id'] ?? null,
                    'customer_info' => [
                        'name' => $customer ? trim(($customer->f_name ?? '') . ' ' . ($customer->l_name ?? '')) : 'Customer',
                        'current_points' => $customer->loyalty_point ?? 0,
                        'current_tier' => $customer->tier_level ?? 'bronze',
                    ]
                ];
            }

            // Handle authentication errors - try to re-authenticate once
            if ($response->status() == 401) {
                if ($this->authenticate()) {
                    // Retry the request
                    $response = Http::withToken($this->accessToken)
                        ->withHeaders([
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json'
                        ])
                        ->timeout(30)
                        ->post($endpoint, $requestData);

                    if ($response->successful()) {
                        $responseData = $response->json();
                        $customer = User::where('phone', $phone)->first();

                        return [
                            'approved' => $responseData['approved'] ?? false,
                            'points_to_award' => $responseData['points_to_award'] ?? 0,
                            'reason' => $responseData['reason'] ?? 'No reason provided',
                            'tier_multiplier' => $responseData['tier_multiplier'] ?? 1.0,
                            'final_points' => $responseData['final_points'] ?? ($responseData['points_to_award'] ?? 0),
                            'dynamics365_transaction_id' => $responseData['dynamics365_transaction_id'] ?? null,
                            'customer_info' => [
                                'name' => $customer ? trim(($customer->f_name ?? '') . ' ' . ($customer->l_name ?? '')) : 'Customer',
                                'current_points' => $customer->loyalty_point ?? 0,
                                'current_tier' => $customer->tier_level ?? 'bronze',
                            ]
                        ];
                    }
                }
            }

            Log::error('Dynamics 365 purchase eligibility check failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'request_data' => $requestData
            ]);

            return [
                'approved' => false,
                'reason' => 'Dynamics 365 service unavailable',
                'error' => 'HTTP ' . $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Dynamics 365 purchase eligibility check exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'approved' => false,
                'reason' => 'Error communicating with Dynamics 365',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Confirm that points were successfully added to Laravel
     * This syncs back to Dynamics 365 to confirm the transaction was completed
     *
     * @param string|null $dynamics365TransactionId Transaction ID from Dynamics 365
     * @param int $pointsAwarded Points that were actually awarded
     * @param int $customerId Customer ID
     * @return bool Success status
     */
    public function confirmPointsAdded(?string $dynamics365TransactionId, int $pointsAwarded, int $customerId): bool
    {
        if (!$this->isEnabled() || empty($dynamics365TransactionId)) {
            return false;
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return false;
            }
        }

        try {
            $customer = User::find($customerId);
            if (!$customer) {
                return false;
            }

            $endpoint = rtrim($this->baseUrl, '/') . '/api/data/v9.2/ConfirmPointsAdded';

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($endpoint, [
                    'dynamics365_transaction_id' => $dynamics365TransactionId,
                    'points_awarded' => $pointsAwarded,
                    'customer_id' => $customerId,
                    'customer_phone' => $customer->phone, // Use phone instead of ref_code
                    'final_points_balance' => $customer->loyalty_point ?? 0,
                    'final_tier' => $customer->tier_level ?? 'bronze',
                    'confirmed_at' => now()->toIso8601String(),
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Dynamics 365 confirm points added exception', [
                'message' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Sync customer data to Dynamics 365
     * Called when customer registers or updates profile
     *
     * @param User $user Customer user model
     * @param array|null $fieldsToUpdate Optional: specific fields to update (for partial updates)
     * @param bool $queue If true, dispatches to queue instead of syncing immediately
     * @return bool Success status (or true if queued)
     */
    public function syncCustomer(User $user, ?array $fieldsToUpdate = null, bool $queue = true): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        // If queue is enabled, dispatch job instead of syncing immediately
        if ($queue && config('queue.default') !== 'sync') {
            \App\Jobs\SyncCustomerToD365Job::dispatch(
                $user,
                'update',
                $fieldsToUpdate,
                []
            )->onQueue('d365-sync');

            return true; // Job dispatched successfully
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return false;
            }
        }

        try {
            // Ensure ref_code exists
            if (empty($user->ref_code)) {
                $user->ref_code = \App\CentralLogics\Helpers::generate_referer_code();
                $user->save();
            }

            $endpoint = rtrim($this->baseUrl, '/') . '/api/data/v9.2/contacts';

            // First, try to find existing contact
            $findResponse = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($endpoint, [
                    '$filter' => "mobilephone eq '{$user->phone}'",
                    '$select' => 'contactid'
                ]);

            $contactId = null;
            if ($findResponse->successful() && !empty($findResponse->json()['value'])) {
                $contactId = $findResponse->json()['value'][0]['contactid'] ?? null;
            }

            // Build contact data - all fields for create, or specific fields for update
            $allContactData = [
                'firstname' => $user->f_name ?? '',
                'lastname' => $user->l_name ?? '',
                'emailaddress1' => $user->email,
                'mobilephone' => $user->phone,
                'customertypecode' => 1, // Customer
                'new_loyaltypoints' => $user->loyalty_point ?? 0,
                'new_tier' => $user->tier ?? 'bronze',
                'new_tierlevel' => $user->tier_level ?? 'bronze',
                'new_refcode' => $user->ref_code,
            ];

            if ($contactId) {
                // Update existing contact - PATCH supports partial updates
                if ($fieldsToUpdate !== null && !empty($fieldsToUpdate)) {
                    // Only send specified fields (partial update)
                    $contactData = array_intersect_key($allContactData, array_flip($fieldsToUpdate));
                } else {
                    // Send all fields (full update)
                    $contactData = $allContactData;
                }

                $response = Http::withToken($this->accessToken)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ])
                    ->patch("{$endpoint}({$contactId})", $contactData);
            } else {
                // Create new contact - POST requires all required fields
                $response = Http::withToken($this->accessToken)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ])
                    ->post($endpoint, $allContactData);
            }

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Dynamics 365 sync customer exception', [
                'message' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            return false;
        }
    }

    /**
     * Update only loyalty points and tier in Dynamics 365 (partial update)
     * More efficient than full sync when only points/tier changed
     *
     * @param User $user Customer user model
     * @param bool $queue If true, dispatches to queue instead of syncing immediately
     * @return bool Success status
     */
    public function updateLoyaltyPoints(User $user, bool $queue = true): bool
    {
        // If queue is enabled, dispatch specific loyalty points job
        if ($queue && config('queue.default') !== 'sync') {
            \App\Jobs\SyncLoyaltyPointsToD365Job::dispatch($user)
                ->onQueue('d365-sync');

            return true; // Job dispatched successfully
        }

        return $this->syncCustomer($user, ['new_loyaltypoints', 'new_tier', 'new_tierlevel'], false);
    }

    /**
     * Create customer in D365 using Retail Realm API (queued)
     *
     * @param User $user Customer user model
     * @param string|null $source Customer source
     * @param string|null $referenceId Optional reference ID
     * @param bool $queue If true, dispatches to queue
     * @return bool Success status
     */
    public function createCustomerInD365(User $user, ?string $source = null, ?string $referenceId = null, bool $queue = true): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        // If queue is enabled, dispatch job
        if ($queue && config('queue.default') !== 'sync') {
            \App\Jobs\SyncCustomerToD365Job::dispatch(
                $user,
                'create',
                null,
                [
                    'source' => $source ?? 'MobileApp',
                    'referenceId' => $referenceId
                ]
            )->onQueue('d365-sync');

            return true; // Job dispatched successfully
        }

        // Otherwise sync immediately
        $result = $this->createCustomerRetailRealm($user, $source, $referenceId);
        return $result !== null;
    }

    // ============================================
    // Retail Realm API Methods (New Format)
    // ============================================

    /**
     * Get ReferenceId for customer (phone number by default, but can be overridden)
     *
     * @param User $user Customer user model
     * @param string|null $overrideReferenceId Optional override
     * @return string ReferenceId
     */
    private function getReferenceId(User $user, ?string $overrideReferenceId = null): string
    {
        // Use override if provided, otherwise use phone number
        // TODO: Keep in mind - may need to change this logic based on D365 team feedback
        return $overrideReferenceId ?? $user->phone ?? (string)$user->id;
    }

    /**
     * Create Customer using Retail Realm API
     *
     * @param User $user Customer user model
     * @param string|null $source Customer source (e.g., "FaceBook", "MobileApp", "Website")
     * @param string|null $referenceId Optional reference ID (defaults to phone number)
     * @return array|null Response with AccountNumber and Status, or null on failure
     */
    public function createCustomerRetailRealm(User $user, ?string $source = null, ?string $referenceId = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return null;
            }
        }

        try {
            // Get endpoint from .env or use default
            $endpoint = env('D365_API_CREATE_CUSTOMER', rtrim($this->baseUrl, '/') . '/api/services/MasterData/Customer/CreateCustomer');

            $refId = $this->getReferenceId($user, $referenceId);

            $requestData = [
                'Customer' => [
                    [
                        'ReferenceId' => $refId,
                        'FirstName' => $user->f_name ?? '',
                        'LastName' => $user->l_name ?? '',
                        'Email' => $user->email ?? '',
                        'Source' => $source ?? 'MobileApp', // Default source
                    ]
                ]
            ];

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($endpoint, $requestData);

            if ($response->successful()) {
                $responseData = $response->json();

                // Response format: [{"ReferenceId": "...", "AccountNumber": "...", "Status": "..."}]
                if (is_array($responseData) && !empty($responseData[0])) {
                    $result = $responseData[0];

                    // Store AccountNumber in user model if we have a field for it
                    // TODO: May need to add account_number field to users table

                    return [
                        'ReferenceId' => $result['ReferenceId'] ?? $refId,
                        'AccountNumber' => $result['AccountNumber'] ?? null,
                        'Status' => $result['Status'] ?? 'Unknown',
                        'success' => true
                    ];
                }
            }

            Log::error('D365 Retail Realm CreateCustomer failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'user_id' => $user->id
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('D365 Retail Realm CreateCustomer exception', [
                'message' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            return null;
        }
    }

    /**
     * Update Customer using Retail Realm API
     *
     * @param User $user Customer user model
     * @param string|null $accountNumber D365 AccountNumber (if known)
     * @param string|null $referenceId ReferenceId (defaults to phone number)
     * @return array|null Response with Status, or null on failure
     */
    public function updateCustomerRetailRealm(User $user, ?string $accountNumber = null, ?string $referenceId = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return null;
            }
        }

        try {
            // Get endpoint from .env or use default
            $endpoint = env('D365_API_UPDATE_CUSTOMER', rtrim($this->baseUrl, '/') . '/api/services/MasterData/Customer/UpdateCustomer');

            $refId = $this->getReferenceId($user, $referenceId);

            $requestData = [
                'Customer' => [
                    [
                        'ReferenceId' => $refId,
                        'AccountNumber' => $accountNumber, // May be required for update
                        'FirstName' => $user->f_name ?? '',
                        'LastName' => $user->l_name ?? '',
                        'Email' => $user->email ?? '',
                    ]
                ]
            ];

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($endpoint, $requestData);

            if ($response->successful()) {
                $responseData = $response->json();

                if (is_array($responseData) && !empty($responseData[0])) {
                    return [
                        'ReferenceId' => $responseData[0]['ReferenceId'] ?? $refId,
                        'AccountNumber' => $responseData[0]['AccountNumber'] ?? $accountNumber,
                        'Status' => $responseData[0]['Status'] ?? 'Unknown',
                        'success' => true
                    ];
                }
            }

            Log::error('D365 Retail Realm UpdateCustomer failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'user_id' => $user->id
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('D365 Retail Realm UpdateCustomer exception', [
                'message' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            return null;
        }
    }

    /**
     * Get Customer using Retail Realm API
     *
     * @param string $referenceId ReferenceId (phone number or other identifier)
     * @param string|null $accountNumber Optional AccountNumber
     * @return array|null Customer data or null if not found
     */
    public function getCustomerRetailRealm(string $referenceId, ?string $accountNumber = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return null;
            }
        }

        try {
            // Get endpoint from .env or use default
            $endpoint = env('D365_API_GET_CUSTOMER', rtrim($this->baseUrl, '/') . '/api/services/MasterData/Customer/GetCustomer');

            $requestData = [
                'ReferenceId' => $referenceId,
            ];

            if ($accountNumber) {
                $requestData['AccountNumber'] = $accountNumber;
            }

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($endpoint, $requestData);

            if ($response->successful()) {
                $responseData = $response->json();
                return $responseData;
            }

            Log::error('D365 Retail Realm GetCustomer failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'referenceId' => $referenceId
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('D365 Retail Realm GetCustomer exception', [
                'message' => $e->getMessage(),
                'referenceId' => $referenceId
            ]);
            return null;
        }
    }

    /**
     * Add Points using Retail Realm API
     *
     * @param string $referenceId ReferenceId (phone number)
     * @param int $points Points to add
     * @param string|null $accountNumber Optional AccountNumber
     * @param string|null $reason Reason for adding points
     * @return array|null Response with Status, or null on failure
     */
    public function addPointsRetailRealm(string $referenceId, int $points, ?string $accountNumber = null, ?string $reason = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return null;
            }
        }

        try {
            // Get endpoint from .env or use default
            $endpoint = env('D365_API_ADD_POINTS', rtrim($this->baseUrl, '/') . '/api/services/MasterData/Customer/AddPoints');

            $requestData = [
                'ReferenceId' => $referenceId,
                'Points' => $points,
            ];

            if ($accountNumber) {
                $requestData['AccountNumber'] = $accountNumber;
            }

            if ($reason) {
                $requestData['Reason'] = $reason;
            }

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($endpoint, $requestData);

            if ($response->successful()) {
                $responseData = $response->json();
                return $responseData;
            }

            Log::error('D365 Retail Realm AddPoints failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'referenceId' => $referenceId,
                'points' => $points
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('D365 Retail Realm AddPoints exception', [
                'message' => $e->getMessage(),
                'referenceId' => $referenceId
            ]);
            return null;
        }
    }

    /**
     * Create Address using Retail Realm API
     *
     * @param string $referenceId ReferenceId (phone number)
     * @param array $addressData Address data (structure to be confirmed with D365 team)
     *   Expected fields may include:
     *   - AddressLine1, AddressLine2
     *   - City, State, Country, PostalCode
     *   - AddressType (Home, Work, etc.)
     * @param string|null $accountNumber Optional AccountNumber
     * @return array|null Response with Status, or null on failure
     */
    public function createAddressRetailRealm(string $referenceId, array $addressData, ?string $accountNumber = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return null;
            }
        }

        try {
            // Get endpoint from .env - update this when you receive the actual endpoint URL
            $endpoint = env('D365_API_CREATE_ADDRESS', rtrim($this->baseUrl, '/') . '/api/services/MasterData/Customer/CreateAddress');

            // Request structure to be confirmed with D365 team
            // Expected format may be: {"Address": [{"ReferenceId": "...", "AddressLine1": "...", ...}]}
            $requestData = [
                'ReferenceId' => $referenceId,
                ...$addressData // Spread address data
            ];

            if ($accountNumber) {
                $requestData['AccountNumber'] = $accountNumber;
            }

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($endpoint, $requestData);

            if ($response->successful()) {
                $responseData = $response->json();
                return $responseData;
            }

            Log::error('D365 Retail Realm CreateAddress failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'referenceId' => $referenceId
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('D365 Retail Realm CreateAddress exception', [
                'message' => $e->getMessage(),
                'referenceId' => $referenceId
            ]);
            return null;
        }
    }

    /**
     * Update Address using Retail Realm API
     *
     * @param string $referenceId ReferenceId (phone number)
     * @param string $addressId Address ID to update
     * @param array $addressData Updated address data
     * @param string|null $accountNumber Optional AccountNumber
     * @return array|null Response with Status, or null on failure
     */
    public function updateAddressRetailRealm(string $referenceId, string $addressId, array $addressData, ?string $accountNumber = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return null;
            }
        }

        try {
            // Get endpoint from .env - update this when you receive the actual endpoint URL
            $endpoint = env('D365_API_UPDATE_ADDRESS', rtrim($this->baseUrl, '/') . '/api/services/MasterData/Customer/UpdateAddress');

            $requestData = [
                'ReferenceId' => $referenceId,
                'AddressId' => $addressId,
                ...$addressData
            ];

            if ($accountNumber) {
                $requestData['AccountNumber'] = $accountNumber;
            }

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($endpoint, $requestData);

            if ($response->successful()) {
                $responseData = $response->json();
                return $responseData;
            }

            Log::error('D365 Retail Realm UpdateAddress failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'referenceId' => $referenceId,
                'addressId' => $addressId
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('D365 Retail Realm UpdateAddress exception', [
                'message' => $e->getMessage(),
                'referenceId' => $referenceId
            ]);
            return null;
        }
    }

    /**
     * Create Phone using Retail Realm API
     *
     * @param string $referenceId ReferenceId (phone number)
     * @param array $phoneData Phone data (structure to be confirmed with D365 team)
     *   Expected fields may include:
     *   - PhoneNumber
     *   - PhoneType (Mobile, Home, Work, etc.)
     *   - IsPrimary (boolean)
     * @param string|null $accountNumber Optional AccountNumber
     * @return array|null Response with Status, or null on failure
     */
    public function createPhoneRetailRealm(string $referenceId, array $phoneData, ?string $accountNumber = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return null;
            }
        }

        try {
            // Get endpoint from .env - update this when you receive the actual endpoint URL
            $endpoint = env('D365_API_CREATE_PHONE', rtrim($this->baseUrl, '/') . '/api/services/MasterData/Customer/CreatePhone');

            // Request structure to be confirmed with D365 team
            $requestData = [
                'ReferenceId' => $referenceId,
                ...$phoneData
            ];

            if ($accountNumber) {
                $requestData['AccountNumber'] = $accountNumber;
            }

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($endpoint, $requestData);

            if ($response->successful()) {
                $responseData = $response->json();
                return $responseData;
            }

            Log::error('D365 Retail Realm CreatePhone failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'referenceId' => $referenceId
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('D365 Retail Realm CreatePhone exception', [
                'message' => $e->getMessage(),
                'referenceId' => $referenceId
            ]);
            return null;
        }
    }

    /**
     * Update Phone using Retail Realm API
     *
     * @param string $referenceId ReferenceId (phone number)
     * @param string $phoneId Phone ID to update
     * @param array $phoneData Updated phone data
     * @param string|null $accountNumber Optional AccountNumber
     * @return array|null Response with Status, or null on failure
     */
    public function updatePhoneRetailRealm(string $referenceId, string $phoneId, array $phoneData, ?string $accountNumber = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Ensure we have a valid access token
        if (empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return null;
            }
        }

        try {
            // Get endpoint from .env - update this when you receive the actual endpoint URL
            $endpoint = env('D365_API_UPDATE_PHONE', rtrim($this->baseUrl, '/') . '/api/services/MasterData/Customer/UpdatePhone');

            $requestData = [
                'ReferenceId' => $referenceId,
                'PhoneId' => $phoneId,
                ...$phoneData
            ];

            if ($accountNumber) {
                $requestData['AccountNumber'] = $accountNumber;
            }

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($endpoint, $requestData);

            if ($response->successful()) {
                $responseData = $response->json();
                return $responseData;
            }

            Log::error('D365 Retail Realm UpdatePhone failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'referenceId' => $referenceId,
                'phoneId' => $phoneId
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('D365 Retail Realm UpdatePhone exception', [
                'message' => $e->getMessage(),
                'referenceId' => $referenceId
            ]);
            return null;
        }
    }
}

