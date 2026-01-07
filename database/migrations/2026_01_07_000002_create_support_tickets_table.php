<?php

use App\Enums\SupportTicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            $table->string('ticket_number', 50)->unique()->index();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('support_ticket_type_id')->constrained('support_ticket_types')->cascadeOnDelete();
            $table->string('inquiry_type', 50)->index();
            $table->foreignId('branch_location_id')->nullable()->constrained('branch_locations')->nullOnDelete();

            $table->text('problem');

            $table->string('contact_email', 191);
            $table->string('contact_phone', 40);

            $table->boolean('callback_requested')->default(false)->index();
            $table->dateTime('callback_time')->nullable();
            $table->text('callback_notes')->nullable();

            $table->string('status', 30)->default(SupportTicketStatus::OPEN)->index();
            $table->text('admin_notes')->nullable();
            // NOTE: admins table exists in production, but isn't created by migrations in this repo.
            // Keep as plain FK column (no constraint) to avoid migration failures in fresh setups.
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
            $table->dateTime('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};


