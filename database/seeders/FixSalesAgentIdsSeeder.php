<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixSalesAgentIdsSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'sales_agent_ids')) {
            $this->command?->warn('No users.sales_agent_ids column found. Nothing to fix.');
            return;
        }

        // Try to normalize invalid values to a valid JSON array.
        // Works on MySQL/MariaDB that support JSON_VALID().
        try {
            DB::statement("UPDATE users SET sales_agent_ids = JSON_ARRAY() WHERE sales_agent_ids IS NULL OR sales_agent_ids = '' OR JSON_VALID(sales_agent_ids)=0");
            $this->command?->info('Normalized users.sales_agent_ids (NULL/empty/invalid -> []).');
            return;
        } catch (\Throwable $e) {
            // Fallback: at least fix NULL/empty (still helpful if JSON_VALID not supported).
            DB::table('users')
                ->whereNull('sales_agent_ids')
                ->orWhere('sales_agent_ids', '')
                ->update(['sales_agent_ids' => '[]']);

            $this->command?->warn('JSON_VALID not available; fixed only NULL/empty sales_agent_ids -> [].');
        }
    }
}




