<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop unique to allow updating keys safely.
        Schema::table('branch_locations', function (Blueprint $table) {
            $table->dropUnique('branch_locations_source_source_key_unique');
        });

        // Resize source_key to a fixed hash length.
        Schema::table('branch_locations', function (Blueprint $table) {
            $table->string('source_key', 64)->nullable()->change();
        });

        // Backfill hashed keys based on page_url (preferred) or name.
        $rows = DB::table('branch_locations')->select('id', 'page_url', 'name')->get();
        foreach ($rows as $row) {
            $keySource = $row->page_url ?: $row->name;
            $hash = $keySource ? hash('sha256', (string) $keySource) : null;
            DB::table('branch_locations')->where('id', $row->id)->update(['source_key' => $hash]);
        }

        Schema::table('branch_locations', function (Blueprint $table) {
            $table->unique(['source', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::table('branch_locations', function (Blueprint $table) {
            $table->dropUnique('branch_locations_source_source_key_unique');
        });

        Schema::table('branch_locations', function (Blueprint $table) {
            $table->string('source_key', 191)->nullable()->change();
        });

        Schema::table('branch_locations', function (Blueprint $table) {
            $table->unique(['source', 'source_key']);
        });
    }
};


