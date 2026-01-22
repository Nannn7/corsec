<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('corsec_incoming_letters') || !Schema::hasTable('corsec_senders')) {
            return;
        }

        if (!Schema::hasColumn('corsec_incoming_letters', 'sender_id') || !Schema::hasColumn('corsec_incoming_letters', 'sender')) {
            return;
        }

        DB::statement("
            UPDATE corsec_incoming_letters AS letters
            SET sender_id = senders.id
            FROM corsec_senders AS senders
            WHERE letters.sender_id IS NULL
              AND letters.sender IS NOT NULL
              AND TRIM(letters.sender) <> ''
              AND (
                LOWER(letters.sender) = LOWER(senders.name)
                OR LOWER(letters.sender) = LOWER(senders.code)
              )
        ");
    }

    public function down(): void
    {
        // no-op: data backfill
    }
};
