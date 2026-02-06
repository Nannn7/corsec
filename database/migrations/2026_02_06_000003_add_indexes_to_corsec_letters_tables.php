<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_incoming_letters')) {
            if (Schema::hasColumn('corsec_incoming_letters', 'target_directorate_id')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_incoming_letters_target_status_idx ON corsec_incoming_letters (target_directorate_id, status)');
            }
            if (Schema::hasColumn('corsec_incoming_letters', 'created_by')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_incoming_letters_created_status_idx ON corsec_incoming_letters (created_by, status)');
            }
            if (Schema::hasColumn('corsec_incoming_letters', 'letter_date')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_incoming_letters_letter_date_idx ON corsec_incoming_letters (letter_date)');
            }
            if (Schema::hasColumn('corsec_incoming_letters', 'received_date')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_incoming_letters_received_date_idx ON corsec_incoming_letters (received_date)');
            }
            if (Schema::hasColumn('corsec_incoming_letters', 'sender_id')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_incoming_letters_sender_id_idx ON corsec_incoming_letters (sender_id)');
            }
            if (Schema::hasColumn('corsec_incoming_letters', 'letter_type_id')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_incoming_letters_letter_type_id_idx ON corsec_incoming_letters (letter_type_id)');
            }
        }

        if (Schema::hasTable('corsec_incoming_letter_directorates')) {
            DB::statement('CREATE INDEX IF NOT EXISTS corsec_incoming_letter_directorates_directorate_idx ON corsec_incoming_letter_directorates (directorate_id, incoming_letter_id)');
        }

        if (Schema::hasTable('corsec_incoming_letter_routes')) {
            if (Schema::hasColumn('corsec_incoming_letter_routes', 'incoming_letter_id')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_incoming_letter_routes_incoming_sent_idx ON corsec_incoming_letter_routes (incoming_letter_id, sent_at)');
            }
            if (Schema::hasColumn('corsec_incoming_letter_routes', 'to_directorate_id')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_incoming_letter_routes_to_directorate_sent_idx ON corsec_incoming_letter_routes (to_directorate_id, sent_at)');
            }
            if (Schema::hasColumn('corsec_incoming_letter_routes', 'from_directorate_id')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_incoming_letter_routes_from_directorate_sent_idx ON corsec_incoming_letter_routes (from_directorate_id, sent_at)');
            }
        }

        if (Schema::hasTable('corsec_outgoing_letters')) {
            if (Schema::hasColumn('corsec_outgoing_letters', 'requester_directorate_id')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_requester_status_idx ON corsec_outgoing_letters (requester_directorate_id, status)');
            }
            if (Schema::hasColumn('corsec_outgoing_letters', 'order_date')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_order_date_idx ON corsec_outgoing_letters (order_date)');
            }
            if (Schema::hasColumn('corsec_outgoing_letters', 'letter_no')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_letter_no_idx ON corsec_outgoing_letters (letter_no)');
            }
            if (Schema::hasColumn('corsec_outgoing_letters', 'perihal_incoming_letter_id')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_perihal_incoming_idx ON corsec_outgoing_letters (perihal_incoming_letter_id)');
            }
        }

        if (Schema::hasTable('corsec_outgoing_letter_number_requests')) {
            if (Schema::hasColumn('corsec_outgoing_letter_number_requests', 'outgoing_letter_id')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_outgoing_letter_number_requests_letter_req_idx ON corsec_outgoing_letter_number_requests (outgoing_letter_id, requested_at)');
            }
            if (Schema::hasColumn('corsec_outgoing_letter_number_requests', 'requested_by')) {
                DB::statement('CREATE INDEX IF NOT EXISTS corsec_outgoing_letter_number_requests_requested_by_idx ON corsec_outgoing_letter_number_requests (requested_by)');
            }
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letter_number_requests_requested_by_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letter_number_requests_letter_req_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_perihal_incoming_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_letter_no_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_order_date_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_outgoing_letters_requester_status_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letter_routes_from_directorate_sent_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letter_routes_to_directorate_sent_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letter_routes_incoming_sent_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letter_directorates_directorate_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_letter_type_id_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_sender_id_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_received_date_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_letter_date_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_created_status_idx');
        DB::statement('DROP INDEX IF EXISTS corsec_incoming_letters_target_status_idx');
    }
};