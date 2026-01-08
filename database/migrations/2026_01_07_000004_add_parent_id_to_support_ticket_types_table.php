<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ticket_types', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->index()->after('id');
            $table->foreign('parent_id', 'support_ticket_types_parent_id_fk')
                ->references('id')
                ->on('support_ticket_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_types', function (Blueprint $table) {
            $table->dropForeign('support_ticket_types_parent_id_fk');
            $table->dropColumn('parent_id');
        });
    }
};


