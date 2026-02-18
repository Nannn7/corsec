<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('corsec_outgoing_letters')) {
            return;
        }

        DB::table('corsec_outgoing_letters')
            ->where('status', 'numbering')
            ->update([
                'status' => 'waiting_verification',
                'updated_at' => now(),
            ]);

        DB::table('corsec_outgoing_letters')
            ->where('status', 'final_uploaded')
            ->update([
                'status' => 'waiting_final_upload',
                'updated_at' => now(),
            ]);

    }

    public function down(): void
    {
        if (!Schema::hasTable('corsec_outgoing_letters')) {
            return;
        }

        DB::table('corsec_outgoing_letters')
            ->where('status', 'waiting_final_upload')
            ->update([
                'status' => 'final_uploaded',
                'updated_at' => now(),
            ]);
    }
};
