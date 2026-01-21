<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_incoming_letters') && !Schema::hasColumn('corsec_incoming_letters', 'sender_other')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->string('sender_other', 150)->nullable()->after('sender_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_incoming_letters') && Schema::hasColumn('corsec_incoming_letters', 'sender_other')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->dropColumn('sender_other');
            });
        }
    }
};
