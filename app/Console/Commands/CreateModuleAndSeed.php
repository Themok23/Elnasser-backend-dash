<?php

namespace App\Console\Commands;

use Database\Seeders\SecureOrderSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateModuleAndSeed extends Command
{
    protected $signature = 'app:create-module-and-seed
        {--id= : Desired module id (e.g. 3)}
        {--name= : Module name (e.g. Ecommerce)}
        {--type= : Module type (e.g. ecommerce)}
        {--clone-from= : Optional: clone icon/thumbnail/description from an existing module id}
        {--seed-users : Run UserSeeder after creating module}
        {--seed-orders : Run SecureOrderSeeder after creating module}
        {--orders-count=200 : How many orders to seed (used only if --seed-orders)}
        {--move-existing : Move existing stores/orders that currently belong to clone-from module into the new module id}
        {--dry-run : Print what would happen without writing anything}';

    protected $description = 'Create a module with a specific id (e.g. module_id=3) and optionally seed users and orders into it.';

    public function handle(): int
    {
        $id = (int) ($this->option('id') ?? 0);
        $name = (string) ($this->option('name') ?? '');
        $type = (string) ($this->option('type') ?? '');
        $cloneFrom = $this->option('clone-from') !== null ? (int) $this->option('clone-from') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($id <= 0) {
            $this->error('Missing/invalid --id. Example: --id=3');
            return self::FAILURE;
        }
        if ($name === '') {
            $this->error('Missing --name. Example: --name=Ecommerce');
            return self::FAILURE;
        }
        if ($type === '') {
            $this->error('Missing --type. Example: --type=ecommerce');
            return self::FAILURE;
        }
        if (!Schema::hasTable('modules')) {
            $this->error('Missing modules table.');
            return self::FAILURE;
        }

        $existing = DB::table('modules')->where('id', $id)->first();
        if ($existing) {
            $this->warn("Module id={$id} already exists ({$existing->module_name} / {$existing->module_type}). Nothing to create.");
        } else {
            $template = null;
            if ($cloneFrom !== null) {
                $template = DB::table('modules')->where('id', $cloneFrom)->first();
                if (!$template) {
                    $this->error("clone-from module id={$cloneFrom} not found.");
                    return self::FAILURE;
                }
            }

            $payload = [
                'id' => $id,
                'module_name' => $name,
                'module_type' => $type,
            ];

            // Optional columns (guarded by Schema checks)
            if (Schema::hasColumn('modules', 'status')) {
                $payload['status'] = 1;
            }
            if (Schema::hasColumn('modules', 'theme_id')) {
                $payload['theme_id'] = (int) ($template->theme_id ?? 1);
            }
            if (Schema::hasColumn('modules', 'stores_count')) {
                $payload['stores_count'] = 0;
            }
            if (Schema::hasColumn('modules', 'all_zone_service')) {
                $payload['all_zone_service'] = (int) ($template->all_zone_service ?? 0);
            }
            if (Schema::hasColumn('modules', 'description')) {
                $payload['description'] = $template->description ?? null;
            }
            if (Schema::hasColumn('modules', 'icon')) {
                $payload['icon'] = $template->icon ?? null;
            }
            if (Schema::hasColumn('modules', 'thumbnail')) {
                $payload['thumbnail'] = $template->thumbnail ?? null;
            }
            if (Schema::hasColumn('modules', 'created_at')) {
                $payload['created_at'] = now();
            }
            if (Schema::hasColumn('modules', 'updated_at')) {
                $payload['updated_at'] = now();
            }

            $this->info("Creating module id={$id} name={$name} type={$type}" . ($cloneFrom ? " (clone-from={$cloneFrom})" : ''));
            if ($dryRun) {
                $this->line('[dry-run] Would insert into modules: ' . json_encode($payload));
            } else {
                DB::table('modules')->insert($payload);
            }

            // Copy module_zone relations if present
            if ($cloneFrom !== null && Schema::hasTable('module_zone')) {
                $rows = DB::table('module_zone')->where('module_id', $cloneFrom)->get();
                if ($rows->count() > 0) {
                    $this->info("Cloning module_zone rows: {$rows->count()}");
                    if (!$dryRun) {
                        foreach ($rows as $r) {
                            DB::table('module_zone')->insert([
                                'module_id' => $id,
                                'zone_id' => $r->zone_id,
                                'per_km_shipping_charge' => $r->per_km_shipping_charge ?? null,
                                'minimum_shipping_charge' => $r->minimum_shipping_charge ?? null,
                            ]);
                        }
                    }
                }
            }

            // Create translations if table exists (optional)
            if (Schema::hasTable('translations')) {
                $this->info('Ensuring translations for module_name/description...');
                if (!$dryRun) {
                    DB::table('translations')->insert([
                        'translationable_type' => 'App\\Models\\Module',
                        'translationable_id' => $id,
                        'locale' => 'en',
                        'key' => 'module_name',
                        'value' => $name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Storages table is optional; if present and icon/thumbnail exists, write disk=public rows
            if (Schema::hasTable('storages')) {
                $this->info('Ensuring storages rows for icon/thumbnail (public) if present...');
                if (!$dryRun) {
                    $icon = $payload['icon'] ?? null;
                    $thumbnail = $payload['thumbnail'] ?? null;
                    if ($icon) {
                        DB::table('storages')->updateOrInsert([
                            'data_type' => 'App\\Models\\Module',
                            'data_id' => (string) $id,
                            'key' => 'icon',
                        ], [
                            'value' => 'public',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    if ($thumbnail) {
                        DB::table('storages')->updateOrInsert([
                            'data_type' => 'App\\Models\\Module',
                            'data_id' => (string) $id,
                            'key' => 'thumbnail',
                        ], [
                            'value' => 'public',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Optional: move existing stores/orders into the new module id
            if ($this->option('move-existing') && $cloneFrom !== null) {
                $this->warn("Moving existing data from module {$cloneFrom} -> {$id} (stores + orders + users.module_ids if present)...");
                if (!$dryRun) {
                    if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'module_id')) {
                        DB::table('stores')->where('module_id', $cloneFrom)->update(['module_id' => $id]);
                    }
                    if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'module_id')) {
                        DB::table('orders')->where('module_id', $cloneFrom)->update(['module_id' => $id]);
                    }
                    // Users store interested modules as JSON in a string column `module_ids` in many installs.
                    // For demo environments, it's usually fine (and desired) to point everyone to the new module.
                    if (Schema::hasTable('users') && Schema::hasColumn('users', 'module_ids')) {
                        $all = DB::table('users')->select('id', 'module_ids')->get();
                        foreach ($all as $u) {
                            $ids = [];
                            if (!empty($u->module_ids)) {
                                $decoded = json_decode($u->module_ids, true);
                                if (is_array($decoded)) {
                                    $ids = $decoded;
                                }
                            }
                            // Replace old module id with new, or if empty set to [new]
                            $ids = array_values(array_unique(array_map('intval', $ids)));
                            if (empty($ids)) {
                                $ids = [$id];
                            } else {
                                $ids = array_values(array_filter($ids, fn ($x) => $x !== (int) $cloneFrom));
                                $ids[] = $id;
                                $ids = array_values(array_unique($ids));
                            }
                            DB::table('users')->where('id', $u->id)->update(['module_ids' => json_encode($ids)]);
                        }
                    }
                }
            }
        }

        // Seed users/orders for this module id
        if ($this->option('seed-users') || $this->option('seed-orders')) {
            $this->info("Seeding into module_id={$id} via SEED_MODULE_ID...");
            putenv("SEED_MODULE_ID={$id}");
            $_ENV['SEED_MODULE_ID'] = (string) $id;

            if ($this->option('seed-orders')) {
                $count = (int) $this->option('orders-count');
                putenv("SEED_ORDERS_COUNT={$count}");
                $_ENV['SEED_ORDERS_COUNT'] = (string) $count;
            }
        }

        if ($this->option('seed-users')) {
            if ($dryRun) {
                $this->line('[dry-run] Would run UserSeeder');
            } else {
                $this->callSilent('db:seed', ['--class' => UserSeeder::class, '--force' => true]);
                $this->info('UserSeeder completed.');
            }
        }

        if ($this->option('seed-orders')) {
            if ($dryRun) {
                $this->line('[dry-run] Would run SecureOrderSeeder');
            } else {
                $this->callSilent('db:seed', ['--class' => SecureOrderSeeder::class, '--force' => true]);
                $this->info('SecureOrderSeeder completed.');
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}


