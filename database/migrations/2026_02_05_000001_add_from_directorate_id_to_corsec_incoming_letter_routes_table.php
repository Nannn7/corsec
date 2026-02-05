<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_incoming_letter_routes') &&
            !Schema::hasColumn('corsec_incoming_letter_routes', 'from_directorate_id')) {
            Schema::table('corsec_incoming_letter_routes', function (Blueprint $table) {
                $table->foreignId('from_directorate_id')
                    ->nullable()
                    ->after('incoming_letter_id')
                    ->constrained('corsec_directorates')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_incoming_letter_routes') &&
            Schema::hasColumn('corsec_incoming_letter_routes', 'from_directorate_id')) {
            Schema::table('corsec_incoming_letter_routes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('from_directorate_id');
            });
        }
    }
};
