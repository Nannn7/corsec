<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('corsec_outgoing_letters')) {
            return;
        }

        Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
            if (!Schema::hasColumn('corsec_outgoing_letters', 'final_upload_date')) {
                $table->date('final_upload_date')->nullable()->after('final_attachment_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('corsec_outgoing_letters')) {
            return;
        }

        Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
            if (Schema::hasColumn('corsec_outgoing_letters', 'final_upload_date')) {
                $table->dropColumn('final_upload_date');
            }
        });
    }
};
