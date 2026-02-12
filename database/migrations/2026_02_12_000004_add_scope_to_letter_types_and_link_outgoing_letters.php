<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_letter_types')) {
            if (!Schema::hasColumn('corsec_letter_types', 'scope')) {
                Schema::table('corsec_letter_types', function (Blueprint $table) {
                    $table->string('scope', 10)
                        ->default('in')
                        ->after('name')
                        ->index('corsec_letter_types_scope_idx');
                });
            }

            DB::table('corsec_letter_types')
                ->whereNull('scope')
                ->update(['scope' => 'in']);

            DB::statement('ALTER TABLE corsec_letter_types DROP CONSTRAINT IF EXISTS corsec_letter_types_code_unique');
            DB::statement('DROP INDEX IF EXISTS corsec_letter_types_code_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS corsec_letter_types_code_scope_unique ON corsec_letter_types (code, scope)');
        }

        if (Schema::hasTable('corsec_outgoing_letters') && !Schema::hasColumn('corsec_outgoing_letters', 'letter_type_id')) {
            Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
                $table->foreignId('letter_type_id')
                    ->nullable()
                    ->after('subject')
                    ->constrained('corsec_letter_types')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_outgoing_letters') && Schema::hasColumn('corsec_outgoing_letters', 'letter_type_id')) {
            Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
                $table->dropConstrainedForeignId('letter_type_id');
            });
        }

        if (Schema::hasTable('corsec_letter_types')) {
            DB::statement('DROP INDEX IF EXISTS corsec_letter_types_code_scope_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS corsec_letter_types_code_unique ON corsec_letter_types (code)');

            if (Schema::hasColumn('corsec_letter_types', 'scope')) {
                DB::statement('DROP INDEX IF EXISTS corsec_letter_types_scope_idx');
                Schema::table('corsec_letter_types', function (Blueprint $table) {
                    $table->dropColumn('scope');
                });
            }
        }
    }
};
