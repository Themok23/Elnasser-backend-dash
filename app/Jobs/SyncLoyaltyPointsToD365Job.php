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

class SyncLoyaltyPointsToD365Job implements ShouldQueue
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
     * Create a new job instance.
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        
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
                Log::warning('D365 loyalty points sync job skipped - D365 integration not enabled', [
                    'user_id' => $this->user->id
                ]);
                return;
            }

            // Refresh user to get latest points and tier
            $this->user->refresh();

            // Update only loyalty points and tier (more efficient)
            $result = $dynamicsService->updateLoyaltyPoints($this->user);

            if ($result) {
                Log::info('D365 loyalty points sync completed successfully', [
                    'user_id' => $this->user->id,
                    'points' => $this->user->loyalty_point,
                    'tier' => $this->user->tier_level,
                    'attempt' => $this->attempts()
                ]);
            } else {
                throw new \Exception('D365 sync returned false');
            }

        } catch (\Exception $e) {
            Log::error('D365 loyalty points sync job failed', [
                'user_id' => $this->user->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'points' => $this->user->loyalty_point ?? 0,
                'tier' => $this->user->tier_level ?? 'unknown'
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
        Log::error('D365 loyalty points sync job failed permanently after all retries', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
            'user_data' => [
                'phone' => $this->user->phone,
                'points' => $this->user->loyalty_point,
                'tier' => $this->user->tier_level
            ]
        ]);
    }
}




