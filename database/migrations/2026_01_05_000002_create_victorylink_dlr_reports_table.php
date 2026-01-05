<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('victorylink_dlr_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_sms_id')->index();
            $table->string('dlr_response_status')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('victorylink_dlr_reports');
    }
};


