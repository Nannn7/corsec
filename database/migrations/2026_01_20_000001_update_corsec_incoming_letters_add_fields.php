<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_incoming_letters')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_incoming_letters', 'registration_no')) {
                    $table->string('registration_no')->nullable()->unique()->after('uuid');
                }
                if (!Schema::hasColumn('corsec_incoming_letters', 'letter_date')) {
                    $table->date('letter_date')->nullable()->after('external_letter_no');
                }
                if (!Schema::hasColumn('corsec_incoming_letters', 'summary')) {
                    $table->text('summary')->nullable()->after('subject');
                }
            });
        }

        if (!Schema::hasTable('corsec_incoming_letter_directorates')) {
            Schema::create('corsec_incoming_letter_directorates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('incoming_letter_id')
                    ->constrained('corsec_incoming_letters')
                    ->cascadeOnDelete();
                $table->foreignId('directorate_id')
                    ->constrained('corsec_directorates')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['incoming_letter_id', 'directorate_id'], 'incoming_letter_directorate_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_incoming_letter_directorates')) {
            Schema::dropIfExists('corsec_incoming_letter_directorates');
        }

        if (Schema::hasTable('corsec_incoming_letters')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                if (Schema::hasColumn('corsec_incoming_letters', 'summary')) {
                    $table->dropColumn('summary');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'letter_date')) {
                    $table->dropColumn('letter_date');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'registration_no')) {
                    $table->dropUnique('corsec_incoming_letters_registration_no_unique');
                    $table->dropColumn('registration_no');
                }
            });
        }
    }
};
