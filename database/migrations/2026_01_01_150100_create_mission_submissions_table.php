<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_submissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mission_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('proof_image_path')->nullable();
            $table->text('note_user')->nullable();
            $table->text('note_admin')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('approved_points')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamps();

            $table->index(['mission_id', 'user_id']);
            $table->index(['status', 'created_at']);

            $table->foreign('mission_id')->references('id')->on('missions')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by_admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_submissions');
    }
};



