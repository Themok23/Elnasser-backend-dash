<?php

namespace App\Console\Commands;

use App\Models\User;
use App\CentralLogics\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rap2hpoutre\FastExcel\FastExcel;
use Exception;

class ImportUsersFromExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:import-excel 
                            {file : Path to the Excel file}
                            {--skip-existing : Skip users that already exist}
                            {--dry-run : Show what would be imported without actually importing}
                            {--chunk-size=1000 : Number of records to process in each batch}
                            {--memory-limit=512M : Memory limit for the import process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import users from Excel file (NAMEALIAS, PRIMARYCONTACTEMAIL, PRIMARYCONTACTPHONE). Optimized for large datasets (250k+ users).';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $skipExisting = $this->option('skip-existing');
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk-size');
        $memoryLimit = $this->option('memory-limit');

        // Set memory limit for large imports
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
        }

        // Check if file exists
        if (!file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");
            return Command::FAILURE;
        }

        $this->info('========================================');
        $this->info('Excel User Import (Optimized for Large Datasets)');
        $this->info('========================================');
        $this->info("File: {$filePath}");
        $this->info("Skip Existing: " . ($skipExisting ? 'Yes' : 'No'));
        $this->info("Dry Run: " . ($dryRun ? 'Yes' : 'No'));
        $this->info("Chunk Size: {$chunkSize}");
        $this->info("Memory Limit: " . ini_get('memory_limit'));
        $this->info('========================================');
        $this->newLine();

        try {
            // Read Excel file
            $this->info('📖 Reading Excel file...');
            $collections = (new FastExcel)->import($filePath);
            $totalRows = $collections->count();
            $this->info("✓ Found {$totalRows} rows");
            $this->newLine();

            // Validate required columns
            $firstRow = $collections->first();
            if (!$firstRow) {
                $this->error("❌ Excel file is empty or has no data");
                return Command::FAILURE;
            }

            $requiredColumns = ['NAMEALIAS', 'PRIMARYCONTACTEMAIL', 'PRIMARYCONTACTPHONE'];
            $missingColumns = [];
            foreach ($requiredColumns as $col) {
                if (!isset($firstRow[$col])) {
                    $missingColumns[] = $col;
                }
            }

            if (!empty($missingColumns)) {
                $this->error("❌ Missing required columns: " . implode(', ', $missingColumns));
                $this->info("Available columns: " . implode(', ', array_keys($firstRow)));
                return Command::FAILURE;
            }

            $this->info('✓ All required columns found');
            $this->newLine();

            // Process rows in chunks for better memory management
            $this->info("🔄 Processing users in chunks of {$chunkSize}...");
            $this->newLine();

            $createdCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $updatedCount = 0;
            $errors = [];
            $processedCount = 0;

            $progressBar = $this->output->createProgressBar($totalRows);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();

            // Pre-fetch existing phone numbers if skipping existing
            $existingPhones = [];
            if ($skipExisting) {
                $this->info('📋 Loading existing phone numbers...');
                $existingPhones = User::pluck('phone')->toArray();
                $existingPhones = array_flip($existingPhones); // For O(1) lookup
                $this->info('✓ Found ' . count($existingPhones) . ' existing users');
                $this->newLine();
            }

            // Process in chunks
            $chunk = [];
            $chunkIndex = 0;

            foreach ($collections as $index => $row) {
                $rowNumber = $index + 2; // +2 because Excel rows start at 1 and we skip header

                try {
                    // Extract data
                    $nameAlias = trim($row['NAMEALIAS'] ?? '');
                    $email = trim($row['PRIMARYCONTACTEMAIL'] ?? '');
                    $phone = trim($row['PRIMARYCONTACTPHONE'] ?? '');

                    // Validate required fields
                    if (empty($nameAlias)) {
                        throw new Exception("NAMEALIAS is empty");
                    }

                    if (empty($phone)) {
                        throw new Exception("PRIMARYCONTACTPHONE is empty");
                    }

                    // Split NAMEALIAS into firstname and lastname
                    $nameParts = $this->splitName($nameAlias);
                    $firstName = $nameParts['first_name'];
                    $lastName = $nameParts['last_name'];

                    // Normalize phone number - add Egypt country code if missing
                    $phone = $this->normalizePhoneNumber($phone);

                    // Check if user already exists (using pre-loaded array for speed)
                    if ($skipExisting && isset($existingPhones[$phone])) {
                        $skippedCount++;
                        $progressBar->advance();
                        $processedCount++;
                        continue;
                    }

                    // Add to chunk for batch processing
                    $chunk[] = [
                        'row_number' => $rowNumber,
                        'f_name' => $firstName,
                        'l_name' => $lastName,
                        'phone' => $phone,
                        'email' => !empty($email) ? $email : null,
                        'name_alias' => $nameAlias,
                    ];

                    // Process chunk when it reaches chunk size
                    if (count($chunk) >= $chunkSize) {
                        $result = $this->processChunk($chunk, $skipExisting, $dryRun);
                        $createdCount += $result['created'];
                        $updatedCount += $result['updated'];
                        $errorCount += $result['errors'];
                        $errors = array_merge($errors, $result['error_details']);
                        $processedCount += count($chunk);
                        $progressBar->advance(count($chunk));
                        
                        // Clear chunk and free memory
                        $chunk = [];
                        $chunkIndex++;
                        
                        // Force garbage collection every 10 chunks
                        if ($chunkIndex % 10 == 0) {
                            gc_collect_cycles();
                        }
                    }

                } catch (Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'name' => $nameAlias ?? 'N/A',
                        'phone' => $phone ?? 'N/A',
                        'error' => $e->getMessage()
                    ];
                    Log::error("ImportUsersFromExcel: Row {$rowNumber} failed", [
                        'row' => $rowNumber,
                        'data' => $row,
                        'error' => $e->getMessage()
                    ]);
                    $progressBar->advance();
                    $processedCount++;
                }
            }

            // Process remaining chunk
            if (!empty($chunk)) {
                $result = $this->processChunk($chunk, $skipExisting, $dryRun);
                $createdCount += $result['created'];
                $updatedCount += $result['updated'];
                $errorCount += $result['errors'];
                $errors = array_merge($errors, $result['error_details']);
                $processedCount += count($chunk);
                $progressBar->advance(count($chunk));
            }

            $progressBar->finish();
            $this->newLine(2);

            // Summary
            $this->info('========================================');
            $this->info('Import Summary:');
            $this->info('========================================');
            $this->info("Total rows processed: {$totalRows}");
            $this->info("✅ Successfully created: {$createdCount}");
            $this->info("🔄 Updated: {$updatedCount}");
            $this->info("⚠️  Skipped (already exists): {$skippedCount}");
            $this->info("❌ Errors: {$errorCount}");
            $this->info('========================================');
            $this->newLine();

            // Show errors if any
            if ($errorCount > 0) {
                $this->warn("⚠️  {$errorCount} errors occurred:");
                $this->newLine();
                foreach ($errors as $error) {
                    $this->error("  Row {$error['row']}: {$error['name']} ({$error['phone']}) - {$error['error']}");
                }
                $this->newLine();
                $this->info("Check logs for details: storage/logs/laravel.log");
            }

            if ($dryRun) {
                $this->warn("⚠️  DRY RUN MODE - No data was actually imported");
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('========================================');
            $this->error('FATAL ERROR:');
            $this->error('========================================');
            $this->error("Message: " . $e->getMessage());
            $this->error("File: " . $e->getFile());
            $this->error("Line: " . $e->getLine());
            $this->error('========================================');

            Log::error("ImportUsersFromExcel: Fatal error", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Process a chunk of users for batch insertion
     *
     * @param array $chunk
     * @param bool $skipExisting
     * @param bool $dryRun
     * @return array
     */
    private function processChunk(array $chunk, bool $skipExisting, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $errors = 0;
        $errorDetails = [];

        if ($dryRun) {
            // Just count what would be created
            foreach ($chunk as $item) {
                $existing = User::where('phone', $item['phone'])->exists();
                if (!$existing) {
                    $created++;
                } elseif (!$skipExisting) {
                    $updated++;
                }
            }
            return [
                'created' => $created,
                'updated' => $updated,
                'errors' => 0,
                'error_details' => []
            ];
        }

        try {
            DB::beginTransaction();

            // Get existing phones for this chunk
            $phones = array_column($chunk, 'phone');
            $existingUsers = User::whereIn('phone', $phones)
                ->get()
                ->keyBy('phone');

            $usersToInsert = [];
            $usersToUpdate = [];
            $refCodesToGenerate = [];
            $storageRecords = [];

            $defaultPassword = bcrypt('Password123');
            $now = now();

            foreach ($chunk as $item) {
                $phone = $item['phone'];
                $existingUser = $existingUsers->get($phone);

                if ($existingUser) {
                    if (!$skipExisting) {
                        // Update existing user
                        $existingUser->f_name = $item['f_name'];
                        $existingUser->l_name = $item['l_name'];
                        if (!empty($item['email'])) {
                            $existingUser->email = $item['email'];
                        }
                        $usersToUpdate[] = $existingUser;
                        $updated++;
                    }
                } else {
                    // Prepare for batch insert
                    $userData = [
                        'f_name' => $item['f_name'],
                        'l_name' => $item['l_name'],
                        'phone' => $phone,
                        'email' => $item['email'],
                        'password' => $defaultPassword,
                        'status' => 1,
                        'is_phone_verified' => 1,
                        'is_email_verified' => !empty($item['email']) ? 1 : 0,
                        'email_verified_at' => !empty($item['email']) ? $now : null,
                        'login_medium' => 'manual',
                        'current_language_key' => 'en',
                        'image' => 'def.png',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $usersToInsert[] = $userData;
                    $created++;
                }
            }

            // Batch insert new users
            if (!empty($usersToInsert)) {
                // Insert in smaller batches to avoid query size limits
                $insertChunks = array_chunk($usersToInsert, 500);
                foreach ($insertChunks as $insertChunk) {
                    User::insert($insertChunk);
                }

                // Get inserted IDs and generate ref_codes
                $insertedPhones = array_column($usersToInsert, 'phone');
                $insertedUsers = User::whereIn('phone', $insertedPhones)
                    ->get(['id', 'phone']);

                foreach ($insertedUsers as $user) {
                    try {
                        $refCode = Helpers::generate_referer_code();
                        User::where('id', $user->id)->update(['ref_code' => $refCode]);
                    } catch (Exception $e) {
                        Log::warning("Failed to generate ref_code for user {$user->id}", [
                            'error' => $e->getMessage()
                        ]);
                    }

                    // Prepare storage records
                    $storageRecords[] = [
                        'data_type' => User::class,
                        'data_id' => $user->id,
                        'key' => 'image',
                        'value' => 'public',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Batch insert storage records
                if (!empty($storageRecords)) {
                    $storageChunks = array_chunk($storageRecords, 500);
                    foreach ($storageChunks as $storageChunk) {
                        DB::table('storages')->insertOrIgnore($storageChunk);
                    }
                }
            }

            // Batch update existing users
            if (!empty($usersToUpdate)) {
                foreach ($usersToUpdate as $user) {
                    $user->save();
                }
            }

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            $errors = count($chunk);
            foreach ($chunk as $item) {
                $errorDetails[] = [
                    'row' => $item['row_number'],
                    'name' => $item['name_alias'],
                    'phone' => $item['phone'],
                    'error' => $e->getMessage()
                ];
            }
            Log::error("ImportUsersFromExcel: Chunk processing failed", [
                'chunk_size' => count($chunk),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'error_details' => $errorDetails
        ];
    }

    /**
     * Split NAMEALIAS into firstname and lastname
     *
     * @param string $nameAlias
     * @return array
     */
    private function splitName(string $nameAlias): array
    {
        $nameAlias = trim($nameAlias);
        
        // Remove extra spaces
        $nameAlias = preg_replace('/\s+/', ' ', $nameAlias);
        
        // Split by space
        $parts = explode(' ', $nameAlias);
        
        if (count($parts) == 1) {
            // Only one name, use as first name
            return [
                'first_name' => $parts[0],
                'last_name' => ''
            ];
        } elseif (count($parts) == 2) {
            // Two parts: first name and last name
            return [
                'first_name' => $parts[0],
                'last_name' => $parts[1]
            ];
        } else {
            // More than two parts: first name is first part, rest is last name
            $firstName = array_shift($parts);
            $lastName = implode(' ', $parts);
            return [
                'first_name' => $firstName,
                'last_name' => $lastName
            ];
        }
    }

    /**
     * Normalize phone number - add Egypt country code if missing
     *
     * @param string $phone
     * @return string
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Remove leading zeros
        $phone = ltrim($phone, '0');
        
        // Check if already has country code
        if (strpos($phone, '+20') === 0) {
            // Already has Egypt country code
            return $phone;
        } elseif (strpos($phone, '20') === 0 && strlen($phone) > 10) {
            // Has country code without +
            return '+' . $phone;
        } elseif (strpos($phone, '+') === 0) {
            // Has different country code, return as is
            return $phone;
        } else {
            // No country code, assume Egypt and add +20
            // Remove leading zero if present
            $phone = ltrim($phone, '0');
            return '+20' . $phone;
        }
    }
}
