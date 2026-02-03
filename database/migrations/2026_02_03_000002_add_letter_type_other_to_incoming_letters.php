<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_incoming_letters') &&
            !Schema::hasColumn('corsec_incoming_letters', 'letter_type_other')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->string('letter_type_other', 150)->nullable()->after('letter_type_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_incoming_letters') &&
            Schema::hasColumn('corsec_incoming_letters', 'letter_type_other')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->dropColumn('letter_type_other');
            });
        }
    }
};
