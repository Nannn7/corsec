<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            // Primary key sebagai UUID
            $table->id()->comment('primary key');

            // Informasi target model dan aksi
            $table->string('model', 255)->nullable(false)->comment('nama tabel / model target');
            $table->enum('action', ['create', 'update', 'delete'])->nullable(false);
            $table->char('target_id', 36)->nullable()->comment('id record target (null untuk create)');

            // Data request (old dan new)
            $table->jsonb('request_old')->nullable()->comment('data lama (sebelum perubahan)');
            $table->jsonb('request_new')->nullable()->comment('data baru (setelah perubahan)');

            // Status approval
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->nullable(false);

            // Deskripsi dan catatan
            $table->string('description', 255)->nullable()->comment('ringkasan request');
            $table->text('review_notes')->nullable()->comment('catatan reviewer');

            // Checksum dan versioning
            $table->char('checksum', 64)->nullable()->comment('hash untuk idempoten');
            $table->integer('version')->nullable()->comment('untuk optimistic locking');

            // Timestamps
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('authorized_at')->nullable();

            // User tracking
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->char('deleted_by', 36)->nullable();
            $table->char('authorized_by', 36)->nullable();

            // Reviewer tracking
            $table->string('reviewer_ip', 45)->nullable()->comment('IPv4/IPv6');
            $table->text('reviewer_agent')->nullable()->comment('user agent browser/app');

            // Indexes untuk performa yang lebih baik
            $table->index('model');
            $table->index('action');
            $table->index('target_id');
            $table->index('status');
            $table->index('created_by');
            $table->index('updated_by');
            $table->index('deleted_by');
            $table->index('authorized_by');
            $table->index('checksum');
            $table->index(['model', 'target_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
