<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corsec_incoming_letters', function (Blueprint $table) {
            if (!Schema::hasColumn('corsec_incoming_letters', 'register_due_date')) {
                $table->date('register_due_date')->nullable()->index('corsec_incoming_letters_register_due_date_idx');
            }

            if (!Schema::hasColumn('corsec_incoming_letters', 'corp_secretary_validation_requested_at')) {
                $table->timestamp('corp_secretary_validation_requested_at')
                    ->nullable()
                    ->index('corsec_incoming_letters_validation_requested_at_idx');
            }

            if (!Schema::hasColumn('corsec_incoming_letters', 'corp_secretary_validated_at')) {
                $table->timestamp('corp_secretary_validated_at')
                    ->nullable()
                    ->index('corsec_incoming_letters_validated_at_idx');
            }

            if (!Schema::hasColumn('corsec_incoming_letters', 'corp_secretary_validated_by')) {
                $table->foreignId('corp_secretary_validated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('corsec_incoming_letters', 'corp_secretary_validation_comment')) {
                $table->text('corp_secretary_validation_comment')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('corsec_incoming_letters', function (Blueprint $table) {
            if (Schema::hasColumn('corsec_incoming_letters', 'corp_secretary_validated_by')) {
                $table->dropConstrainedForeignId('corp_secretary_validated_by');
            }

            $columns = [
                'corp_secretary_validation_comment',
                'corp_secretary_validated_at',
                'corp_secretary_validation_requested_at',
                'register_due_date',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('corsec_incoming_letters', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
