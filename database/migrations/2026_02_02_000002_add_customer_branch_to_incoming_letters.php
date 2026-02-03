<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_incoming_letters') &&
            !Schema::hasColumn('corsec_incoming_letters', 'customer_branch_id')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->foreignId('customer_branch_id')
                    ->nullable()
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_incoming_letters') &&
            Schema::hasColumn('corsec_incoming_letters', 'customer_branch_id')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->dropConstrainedForeignId('customer_branch_id');
            });
        }
    }
};
