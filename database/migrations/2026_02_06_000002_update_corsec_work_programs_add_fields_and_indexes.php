<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_work_program_items') &&
            !Schema::hasColumn('corsec_work_program_items', 'completed_at')) {
            Schema::table('corsec_work_program_items', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable()->index('corsec_work_program_items_completed_at_idx');
            });
        }

        if (Schema::hasTable('corsec_work_program_updates')) {
            if (!Schema::hasColumn('corsec_work_program_updates', 'action')) {
                Schema::table('corsec_work_program_updates', function (Blueprint $table) {
                    $table->string('action')->default('progress')->index('corsec_work_program_updates_action_idx');
                });
            }

            if (!Schema::hasColumn('corsec_work_program_updates', 'revised_target_date')) {
                Schema::table('corsec_work_program_updates', function (Blueprint $table) {
                    $table->date('revised_target_date')->nullable()
                        ->index('corsec_work_program_updates_revised_target_date_idx');
                });
            }
        }

        if (Schema::hasTable('corsec_work_programs')) {
            Schema::table('corsec_work_programs', function (Blueprint $table) {
                $table->index(['directorate_id', 'status'], 'corsec_work_programs_directorate_status_idx');
            });
        }

        if (Schema::hasTable('corsec_work_program_items')) {
            Schema::table('corsec_work_program_items', function (Blueprint $table) {
                $table->index(['work_program_id', 'status'], 'corsec_work_program_items_program_status_idx');
            });
        }

        if (Schema::hasTable('corsec_work_program_updates')) {
            Schema::table('corsec_work_program_updates', function (Blueprint $table) {
                $table->index(['work_program_item_id', 'status'], 'corsec_work_program_updates_item_status_idx');
            });
        }

        if (Schema::hasTable('corsec_meeting_decisions')) {
            Schema::table('corsec_meeting_decisions', function (Blueprint $table) {
                $table->index(['owner_directorate_id', 'status'], 'corsec_meeting_decisions_owner_status_idx');
            });
        }

        if (Schema::hasTable('corsec_decision_updates')) {
            Schema::table('corsec_decision_updates', function (Blueprint $table) {
                $table->index(['meeting_decision_id', 'status'], 'corsec_decision_updates_decision_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_decision_updates')) {
            Schema::table('corsec_decision_updates', function (Blueprint $table) {
                $table->dropIndex('corsec_decision_updates_decision_status_idx');
            });
        }

        if (Schema::hasTable('corsec_meeting_decisions')) {
            Schema::table('corsec_meeting_decisions', function (Blueprint $table) {
                $table->dropIndex('corsec_meeting_decisions_owner_status_idx');
            });
        }

        if (Schema::hasTable('corsec_work_program_updates')) {
            Schema::table('corsec_work_program_updates', function (Blueprint $table) {
                $table->dropIndex('corsec_work_program_updates_item_status_idx');
                $table->dropIndex('corsec_work_program_updates_action_idx');
                $table->dropIndex('corsec_work_program_updates_revised_target_date_idx');
            });

            if (Schema::hasColumn('corsec_work_program_updates', 'action')) {
                Schema::table('corsec_work_program_updates', function (Blueprint $table) {
                    $table->dropColumn('action');
                });
            }

            if (Schema::hasColumn('corsec_work_program_updates', 'revised_target_date')) {
                Schema::table('corsec_work_program_updates', function (Blueprint $table) {
                    $table->dropColumn('revised_target_date');
                });
            }
        }

        if (Schema::hasTable('corsec_work_program_items')) {
            Schema::table('corsec_work_program_items', function (Blueprint $table) {
                $table->dropIndex('corsec_work_program_items_program_status_idx');
                $table->dropIndex('corsec_work_program_items_completed_at_idx');
            });

            if (Schema::hasColumn('corsec_work_program_items', 'completed_at')) {
                Schema::table('corsec_work_program_items', function (Blueprint $table) {
                    $table->dropColumn('completed_at');
                });
            }
        }

        if (Schema::hasTable('corsec_work_programs')) {
            Schema::table('corsec_work_programs', function (Blueprint $table) {
                $table->dropIndex('corsec_work_programs_directorate_status_idx');
            });
        }
    }
};