<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_views', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('story_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->unique(['story_id', 'user_id']);
            $table->foreign('story_id')->references('id')->on('stories')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['story_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_views');
    }
};

