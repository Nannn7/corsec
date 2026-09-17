<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix for QAX Pentest Report 2026-08-19, finding 4.1
 * "Unauthenticated attachment download" (High Risk).
 *
 * All attachment/document upload code paths now write to the 'private'
 * filesystem disk (see config/filesystems.php) instead of 'public', so new
 * uploads are no longer physically reachable through the public/storage
 * symlink. This migration only changes the column DEFAULT for future rows.
 *
 * Existing rows that still have disk = 'public' / file_disk = 'public' are
 * NOT touched here — moving the underlying files and updating those rows is
 * handled by the `corsec:migrate-attachments-to-private` Artisan command,
 * which must be run once as part of this deployment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('corsec_attachments') && Schema::hasColumn('corsec_attachments', 'disk')) {
            Schema::table('corsec_attachments', function (Blueprint $table) {
                $table->string('disk')->default('private')->change();
            });
        }

        if (Schema::hasTable('corsec_library_items') && Schema::hasColumn('corsec_library_items', 'file_disk')) {
            Schema::table('corsec_library_items', function (Blueprint $table) {
                $table->string('file_disk', 50)->default('private')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_attachments') && Schema::hasColumn('corsec_attachments', 'disk')) {
            Schema::table('corsec_attachments', function (Blueprint $table) {
                $table->string('disk')->default('public')->change();
            });
        }

        if (Schema::hasTable('corsec_library_items') && Schema::hasColumn('corsec_library_items', 'file_disk')) {
            Schema::table('corsec_library_items', function (Blueprint $table) {
                $table->string('file_disk', 50)->default('public')->change();
            });
        }
    }
};