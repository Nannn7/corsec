<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $this->createBtreeIndexes();
        $this->createTrigramIndexesIfAvailable();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Incoming/outgoing letter indexes
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_status_created_at_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_perihal_status_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_letter_type_created_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_status_created_at_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_target_created_at_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_created_by_created_at_idx');

        // Meeting/workplan indexes
        DB::statement('DROP INDEX IF EXISTS corsec_meetings_meeting_at_id_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_meetings_status_meeting_at_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_meetings_created_by_meeting_at_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_meeting_agendas_pic_user_meeting_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_meeting_agendas_owner_directorate_meeting_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_meeting_decisions_pic_user_meeting_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_meeting_decisions_owner_directorate_meeting_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_work_programs_status_year_created_at_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_work_programs_created_by_status_year_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_work_programs_directorate_year_status_idx');

        // Trigram indexes
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_subject_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_summary_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_registration_no_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_letter_no_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_perihal_text_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_recipient_other_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_subject_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_summary_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_registration_no_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_external_letter_no_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_senders_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_senders_code_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_letter_types_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_letter_types_code_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_meetings_title_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_work_programs_title_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_work_programs_description_trgm_idx');
    }

    private function createBtreeIndexes(): void
    {
        if (Schema::hasTable('corsec_outgoing_letters')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_status_created_at_idx '
                . 'ON corsec_outgoing_letters (status, created_at DESC)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_perihal_status_idx '
                . 'ON corsec_outgoing_letters (perihal_type, perihal_incoming_letter_id, status)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_letter_type_created_idx '
                . 'ON corsec_outgoing_letters (letter_type_id, created_at DESC)'
            );
        }

        if (Schema::hasTable('corsec_incoming_letters')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_status_created_at_idx '
                . 'ON corsec_incoming_letters (status, created_at DESC)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_target_created_at_idx '
                . 'ON corsec_incoming_letters (target_directorate_id, created_at DESC)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_created_by_created_at_idx '
                . 'ON corsec_incoming_letters (created_by, created_at DESC)'
            );
        }

        if (Schema::hasTable('corsec_meetings')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_meetings_meeting_at_id_idx '
                . 'ON corsec_meetings (meeting_at DESC, id DESC)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_meetings_status_meeting_at_idx '
                . 'ON corsec_meetings (status, meeting_at DESC)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_meetings_created_by_meeting_at_idx '
                . 'ON corsec_meetings (created_by, meeting_at DESC)'
            );
        }

        if (Schema::hasTable('corsec_meeting_agendas')) {
            if (Schema::hasColumn('corsec_meeting_agendas', 'pic_user_id')) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS corsec_meeting_agendas_pic_user_meeting_idx '
                    . 'ON corsec_meeting_agendas (pic_user_id, meeting_id)'
                );
            }
            if (Schema::hasColumn('corsec_meeting_agendas', 'owner_directorate_id')) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS corsec_meeting_agendas_owner_directorate_meeting_idx '
                    . 'ON corsec_meeting_agendas (owner_directorate_id, meeting_id)'
                );
            }
        }

        if (Schema::hasTable('corsec_meeting_decisions')) {
            if (Schema::hasColumn('corsec_meeting_decisions', 'pic_user_id')) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS corsec_meeting_decisions_pic_user_meeting_idx '
                    . 'ON corsec_meeting_decisions (pic_user_id, meeting_id)'
                );
            }
            if (Schema::hasColumn('corsec_meeting_decisions', 'owner_directorate_id')) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS corsec_meeting_decisions_owner_directorate_meeting_idx '
                    . 'ON corsec_meeting_decisions (owner_directorate_id, meeting_id)'
                );
            }
        }

        if (Schema::hasTable('corsec_work_programs')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_work_programs_status_year_created_at_idx '
                . 'ON corsec_work_programs (status, year, created_at DESC)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_work_programs_created_by_status_year_idx '
                . 'ON corsec_work_programs (created_by, status, year)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_work_programs_directorate_year_status_idx '
                . 'ON corsec_work_programs (directorate_id, year, status)'
            );
        }
    }

    private function createTrigramIndexesIfAvailable(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        } catch (\Throwable) {
            return;
        }

        if (Schema::hasTable('corsec_outgoing_letters')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_subject_trgm_idx '
                . 'ON corsec_outgoing_letters USING gin (subject gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_summary_trgm_idx '
                . 'ON corsec_outgoing_letters USING gin (summary gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_registration_no_trgm_idx '
                . 'ON corsec_outgoing_letters USING gin (registration_no gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_letter_no_trgm_idx '
                . 'ON corsec_outgoing_letters USING gin (letter_no gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_perihal_text_trgm_idx '
                . 'ON corsec_outgoing_letters USING gin (perihal_text gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_recipient_other_trgm_idx '
                . 'ON corsec_outgoing_letters USING gin (recipient_other gin_trgm_ops)'
            );
        }

        if (Schema::hasTable('corsec_incoming_letters')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_subject_trgm_idx '
                . 'ON corsec_incoming_letters USING gin (subject gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_summary_trgm_idx '
                . 'ON corsec_incoming_letters USING gin (summary gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_registration_no_trgm_idx '
                . 'ON corsec_incoming_letters USING gin (registration_no gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_external_letter_no_trgm_idx '
                . 'ON corsec_incoming_letters USING gin (external_letter_no gin_trgm_ops)'
            );
        }

        if (Schema::hasTable('corsec_senders')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_senders_name_trgm_idx '
                . 'ON corsec_senders USING gin (name gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_senders_code_trgm_idx '
                . 'ON corsec_senders USING gin (code gin_trgm_ops)'
            );
        }

        if (Schema::hasTable('corsec_letter_types')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_letter_types_name_trgm_idx '
                . 'ON corsec_letter_types USING gin (name gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_letter_types_code_trgm_idx '
                . 'ON corsec_letter_types USING gin (code gin_trgm_ops)'
            );
        }

        if (Schema::hasTable('corsec_meetings')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_meetings_title_trgm_idx '
                . 'ON corsec_meetings USING gin (title gin_trgm_ops)'
            );
        }

        if (Schema::hasTable('corsec_work_programs')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_work_programs_title_trgm_idx '
                . 'ON corsec_work_programs USING gin (title gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_work_programs_description_trgm_idx '
                . 'ON corsec_work_programs USING gin (description gin_trgm_ops)'
            );
        }
    }
};
