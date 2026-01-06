<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->text('description')->nullable();

            // Use decimal for stable distance calculations.
            $table->decimal('latitude', 10, 7)->nullable()->index();
            $table->decimal('longitude', 10, 7)->nullable()->index();

            $table->text('page_url')->nullable();
            $table->text('maps_url')->nullable();
            $table->text('resolved_maps_url')->nullable();

            // Optional UI fields
            $table->text('image_url')->nullable();
            $table->unsignedInteger('rank')->nullable()->index();

            // Traceability
            $table->string('source', 50)->default('nstores')->index();
            $table->string('source_key', 191)->nullable()->index(); // e.g. bitly keyword

            $table->timestamps();

            $table->unique(['source', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_locations');
    }
};


