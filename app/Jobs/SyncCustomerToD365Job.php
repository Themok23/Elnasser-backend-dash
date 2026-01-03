<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Dynamics365Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCustomerToD365Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * The user model instance.
     *
     * @var User
     */
    public $user;

    /**
     * The sync operation type.
     *
     * @var string
     */
    public $operation;

    /**
     * Optional fields to update (for partial updates).
     *
     * @var array|null
     */
    public $fieldsToUpdate;

    /**
     * Additional data for the sync operation.
     *
     * @var array
     */
    public $additionalData;

    /**
     * Create a new job instance.
     *
     * @param User $user
     * @param string $operation 'create' or 'update'
     * @param array|null $fieldsToUpdate Optional fields to update
     * @param array $additionalData Additional data (source, accountNumber, etc.)
     */
    public function __construct(
        User $user,
        string $operation = 'update',
        ?array $fieldsToUpdate = null,
        array $additionalData = []
    ) {
        $this->user = $user;
        $this->operation = $operation;
        $this->fieldsToUpdate = $fieldsToUpdate;
        $this->additionalData = $additionalData;
        
        // Set queue connection from config
        $this->onQueue('d365-sync');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $dynamicsService = new Dynamics365Service();

            if (!$dynamicsService->isEnabled()) {
                Log::warning('D365 sync job skipped - D365 integration not enabled', [
                    'user_id' => $this->user->id,
                    'operation' => $this->operation
                ]);
                return;
            }

            // Refresh user to get latest data
            $this->user->refresh();

            switch ($this->operation) {
                case 'create':
                    // Use Retail Realm API if available
                    $source = $this->additionalData['source'] ?? 'MobileApp';
                    $referenceId = $this->additionalData['referenceId'] ?? null;
                    
                    $result = $dynamicsService->createCustomerRetailRealm(
                        $this->user,
                        $source,
                        $referenceId
                    );

                    if ($result && isset($result['AccountNumber'])) {
                        // Store AccountNumber for future use
                        // TODO: Add account_number field to users table if needed
                        Log::info('Customer created in D365 successfully', [
                            'user_id' => $this->user->id,
                            'account_number' => $result['AccountNumber'],
                            'reference_id' => $result['ReferenceId']
                        ]);
                    }
                    break;

                case 'update':
                default:
                    // Use Retail Realm API if account number available, otherwise use standard sync
                    $accountNumber = $this->additionalData['accountNumber'] ?? null;
                    $referenceId = $this->additionalData['referenceId'] ?? null;

                    if ($accountNumber) {
                        // Use Retail Realm Update API
                        $result = $dynamicsService->updateCustomerRetailRealm(
                            $this->user,
                            $accountNumber,
                            $referenceId
                        );
                    } else {
                        // Use standard sync (fallback)
                        $dynamicsService->syncCustomer($this->user, $this->fieldsToUpdate);
                    }
                    break;
            }

            Log::info('D365 sync job completed successfully', [
                'user_id' => $this->user->id,
                'operation' => $this->operation,
                'attempt' => $this->attempts()
            ]);

        } catch (\Exception $e) {
            Log::error('D365 sync job failed', [
                'user_id' => $this->user->id,
                'operation' => $this->operation,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('D365 sync job failed permanently after all retries', [
            'user_id' => $this->user->id,
            'operation' => $this->operation,
            'error' => $exception->getMessage(),
            'user_data' => [
                'phone' => $this->user->phone,
                'email' => $this->user->email,
                'name' => $this->user->f_name . ' ' . $this->user->l_name
            ]
        ]);

        // Optionally: Store failed sync in a separate table for manual retry
        // You could create a failed_syncs table to track these
    }
}



