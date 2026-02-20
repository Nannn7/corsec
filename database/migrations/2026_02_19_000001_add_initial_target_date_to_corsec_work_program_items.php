<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_work_program_items') &&
            !Schema::hasColumn('corsec_work_program_items', 'initial_target_date')) {
            Schema::table('corsec_work_program_items', function (Blueprint $table) {
                $table->date('initial_target_date')
                    ->nullable()
                    ->index('corsec_work_program_items_initial_target_date_idx');
            });
        }

        if (Schema::hasTable('corsec_work_program_items') &&
            Schema::hasColumn('corsec_work_program_items', 'initial_target_date') &&
            Schema::hasColumn('corsec_work_program_items', 'target_date')) {
            DB::table('corsec_work_program_items')
                ->whereNull('initial_target_date')
                ->update([
                    'initial_target_date' => DB::raw('target_date'),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_work_program_items') &&
            Schema::hasColumn('corsec_work_program_items', 'initial_target_date')) {
            Schema::table('corsec_work_program_items', function (Blueprint $table) {
                $table->dropIndex('corsec_work_program_items_initial_target_date_idx');
                $table->dropColumn('initial_target_date');
            });
        }
    }
};
