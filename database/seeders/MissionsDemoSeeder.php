<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MissionsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('missions')) {
            $this->command?->warn('missions table not found. Run migrations first.');
            return;
        }

        $now = now();

        // Minimal demo missions (global)
        DB::table('missions')->insertOrIgnore([
            [
                'id' => 1,
                'name' => 'Like Alnasser Facebook Page',
                'description' => 'Like our official Facebook page and upload a screenshot as proof.',
                'points' => 50,
                'status' => 1,
                'start_at' => null,
                'end_at' => null,
                'max_per_user' => 1,
                'requires_proof' => 1,
                'proof_instructions' => 'Open Alnasser Facebook page, press Like, take screenshot, upload it.',
                'created_by_admin_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'Follow Alnasser Instagram',
                'description' => 'Follow our Instagram account and upload a screenshot as proof.',
                'points' => 30,
                'status' => 1,
                'start_at' => null,
                'end_at' => null,
                'max_per_user' => 1,
                'requires_proof' => 1,
                'proof_instructions' => 'Follow our Instagram, take screenshot, upload it.',
                'created_by_admin_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->command?->info('Seeded demo missions (if not already present).');
    }
}


