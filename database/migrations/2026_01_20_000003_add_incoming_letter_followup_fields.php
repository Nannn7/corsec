<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_incoming_letters')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_incoming_letters', 'followup_action')) {
                    $table->string('followup_action', 100)->nullable()->after('target_date');
                }
                if (!Schema::hasColumn('corsec_incoming_letters', 'followup_detail')) {
                    $table->json('followup_detail')->nullable()->after('followup_action');
                }
                if (!Schema::hasColumn('corsec_incoming_letters', 'followup_note')) {
                    $table->text('followup_note')->nullable()->after('followup_detail');
                }
                if (!Schema::hasColumn('corsec_incoming_letters', 'followup_submitted_at')) {
                    $table->timestamp('followup_submitted_at')->nullable()->after('followup_note');
                }
                if (!Schema::hasColumn('corsec_incoming_letters', 'followup_submitted_by')) {
                    $table->foreignId('followup_submitted_by')->nullable()->after('followup_submitted_at')
                        ->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_incoming_letters')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                if (Schema::hasColumn('corsec_incoming_letters', 'followup_submitted_by')) {
                    $table->dropConstrainedForeignId('followup_submitted_by');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'followup_submitted_at')) {
                    $table->dropColumn('followup_submitted_at');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'followup_note')) {
                    $table->dropColumn('followup_note');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'followup_detail')) {
                    $table->dropColumn('followup_detail');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'followup_action')) {
                    $table->dropColumn('followup_action');
                }
            });
        }
    }
};
