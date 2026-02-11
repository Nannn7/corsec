<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_incoming_letters')) {
            DB::statement('ALTER TABLE corsec_incoming_letters DROP COLUMN IF EXISTS counterparty_bank_id CASCADE');
        }

        DB::statement('DROP TABLE IF EXISTS corsec_banks CASCADE');
    }

    public function down(): void
    {
        if (!Schema::hasTable('corsec_banks')) {
            Schema::create('corsec_banks', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('code', 50)->unique();
                $table->string('swift_code', 50)->nullable()->index();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->boolean('status')->default(true)->index();
                $table->timestamp('authorized_at')->nullable();
                $table->string('authorized_status')->nullable()->index();
                $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('corsec_incoming_letters') && !Schema::hasColumn('corsec_incoming_letters', 'counterparty_bank_id')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->foreignId('counterparty_bank_id')
                    ->nullable()
                    ->constrained('corsec_banks')
                    ->nullOnDelete();
            });
        }
    }
};

