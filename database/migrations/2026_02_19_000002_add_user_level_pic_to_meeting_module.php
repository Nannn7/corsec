<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_meeting_participants')) {
            Schema::table('corsec_meeting_participants', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_meeting_participants', 'user_id')) {
                    $table->foreignId('user_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });

            if (Schema::hasColumn('corsec_meeting_participants', 'directorate_id')) {
                DB::statement('ALTER TABLE corsec_meeting_participants ALTER COLUMN directorate_id DROP NOT NULL');
            }

            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS corsec_meeting_participants_meeting_user_unique
                 ON corsec_meeting_participants (meeting_id, user_id)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_meeting_participants_user_meeting_idx
                 ON corsec_meeting_participants (user_id, meeting_id)'
            );
        }

        if (Schema::hasTable('corsec_meeting_agendas')) {
            Schema::table('corsec_meeting_agendas', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_meeting_agendas', 'pic_user_id')) {
                    $table->foreignId('pic_user_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('corsec_meeting_decisions')) {
            Schema::table('corsec_meeting_decisions', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_meeting_decisions', 'pic_user_id')) {
                    $table->foreignId('pic_user_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('corsec_meeting_agendas')) {
            DB::statement(
                "UPDATE corsec_meeting_agendas AS agendas
                 SET owner_directorate_id = users.directorate_id
                 FROM users
                 WHERE agendas.owner_directorate_id IS NULL
                   AND agendas.pic_user_id IS NOT NULL
                   AND users.id = agendas.pic_user_id"
            );
        }

        if (Schema::hasTable('corsec_meeting_decisions')) {
            DB::statement(
                "UPDATE corsec_meeting_decisions AS decisions
                 SET owner_directorate_id = users.directorate_id
                 FROM users
                 WHERE decisions.owner_directorate_id IS NULL
                   AND decisions.pic_user_id IS NOT NULL
                   AND users.id = decisions.pic_user_id"
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS corsec_meeting_participants_user_meeting_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_meeting_participants_meeting_user_unique');

        if (Schema::hasTable('corsec_meeting_decisions') &&
            Schema::hasColumn('corsec_meeting_decisions', 'pic_user_id')) {
            DB::statement('ALTER TABLE corsec_meeting_decisions DROP COLUMN IF EXISTS pic_user_id CASCADE');
        }

        if (Schema::hasTable('corsec_meeting_agendas') &&
            Schema::hasColumn('corsec_meeting_agendas', 'pic_user_id')) {
            DB::statement('ALTER TABLE corsec_meeting_agendas DROP COLUMN IF EXISTS pic_user_id CASCADE');
        }

        if (Schema::hasTable('corsec_meeting_participants') &&
            Schema::hasColumn('corsec_meeting_participants', 'user_id')) {
            DB::statement('ALTER TABLE corsec_meeting_participants DROP COLUMN IF EXISTS user_id CASCADE');
        }
    }
};
