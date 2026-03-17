<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('corsec_meeting_agendas')) {
            return;
        }

        Schema::table('corsec_meeting_agendas', function (Blueprint $table) {
            if (!Schema::hasColumn('corsec_meeting_agendas', 'source_decision_id')) {
                $table->foreignId('source_decision_id')
                    ->nullable()
                    ->after('pic_user_id')
                    ->constrained('corsec_meeting_decisions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('corsec_meeting_agendas') || !Schema::hasColumn('corsec_meeting_agendas', 'source_decision_id')) {
            return;
        }

        Schema::table('corsec_meeting_agendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_decision_id');
        });
    }
};
