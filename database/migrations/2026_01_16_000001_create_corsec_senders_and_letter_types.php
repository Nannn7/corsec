<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('corsec_senders')) {
            Schema::create('corsec_senders', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->string('code', 50)->unique();
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

        if (!Schema::hasTable('corsec_letter_types')) {
            Schema::create('corsec_letter_types', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->string('code', 50)->unique();
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

        if (Schema::hasTable('corsec_incoming_letters') && !Schema::hasColumn('corsec_incoming_letters', 'sender_id')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->foreignId('sender_id')
                    ->nullable()
                    ->constrained('corsec_senders')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('corsec_incoming_letters') && !Schema::hasColumn('corsec_incoming_letters', 'letter_type_id')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->foreignId('letter_type_id')
                    ->nullable()
                    ->constrained('corsec_letter_types')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_incoming_letters') && Schema::hasColumn('corsec_incoming_letters', 'letter_type_id')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->dropConstrainedForeignId('letter_type_id');
            });
        }

        if (Schema::hasTable('corsec_incoming_letters') && Schema::hasColumn('corsec_incoming_letters', 'sender_id')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sender_id');
            });
        }

        if (Schema::hasTable('corsec_letter_types')) {
            Schema::dropIfExists('corsec_letter_types');
        }

        if (Schema::hasTable('corsec_senders')) {
            Schema::dropIfExists('corsec_senders');
        }
    }
};
