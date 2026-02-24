<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('corsec_meeting_participants')) {
            return;
        }

        // Legacy unique by (meeting_id, directorate_id) blocks multiple users in same directorate.
        DB::statement('ALTER TABLE corsec_meeting_participants DROP CONSTRAINT IF EXISTS corsec_meeting_participants_unique');
        DB::statement('DROP INDEX IF EXISTS corsec_meeting_participants_unique');

        // Keep one generic directorate row (without specific user) per meeting+directorate.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS corsec_meeting_participants_meeting_directorate_null_user_unique '
            . 'ON corsec_meeting_participants (meeting_id, directorate_id) '
            . 'WHERE user_id IS NULL AND directorate_id IS NOT NULL'
        );

        // Ensure no duplicate user participant rows in same meeting.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS corsec_meeting_participants_meeting_user_unique '
            . 'ON corsec_meeting_participants (meeting_id, user_id)'
        );

        // Restore lookup performance after dropping legacy unique index.
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_meeting_participants_meeting_directorate_idx '
            . 'ON corsec_meeting_participants (meeting_id, directorate_id)'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('corsec_meeting_participants')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS corsec_meeting_participants_meeting_directorate_null_user_unique');
        DB::statement('DROP INDEX IF EXISTS corsec_meeting_participants_meeting_directorate_idx');
        DB::statement(
            'ALTER TABLE corsec_meeting_participants '
            . 'ADD CONSTRAINT corsec_meeting_participants_unique UNIQUE (meeting_id, directorate_id)'
        );
    }
};
