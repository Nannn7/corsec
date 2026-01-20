<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('approval_requests')) {
            $columns = ['created_by', 'updated_by', 'deleted_by', 'authorized_by'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('approval_requests', $column)) {
                    DB::statement("ALTER TABLE approval_requests ALTER COLUMN {$column} TYPE BIGINT USING NULLIF({$column}, '')::bigint");
                }
            }

            DB::statement('ALTER TABLE approval_requests DROP CONSTRAINT IF EXISTS approval_requests_created_by_foreign');
            DB::statement('ALTER TABLE approval_requests DROP CONSTRAINT IF EXISTS approval_requests_updated_by_foreign');
            DB::statement('ALTER TABLE approval_requests DROP CONSTRAINT IF EXISTS approval_requests_deleted_by_foreign');
            DB::statement('ALTER TABLE approval_requests DROP CONSTRAINT IF EXISTS approval_requests_authorized_by_foreign');

            Schema::table('approval_requests', function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('authorized_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('approval_requests')) {
            DB::statement('ALTER TABLE approval_requests DROP CONSTRAINT IF EXISTS approval_requests_created_by_foreign');
            DB::statement('ALTER TABLE approval_requests DROP CONSTRAINT IF EXISTS approval_requests_updated_by_foreign');
            DB::statement('ALTER TABLE approval_requests DROP CONSTRAINT IF EXISTS approval_requests_deleted_by_foreign');
            DB::statement('ALTER TABLE approval_requests DROP CONSTRAINT IF EXISTS approval_requests_authorized_by_foreign');

            $columns = ['created_by', 'updated_by', 'deleted_by', 'authorized_by'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('approval_requests', $column)) {
                    DB::statement("ALTER TABLE approval_requests ALTER COLUMN {$column} TYPE CHAR(36) USING {$column}::text");
                }
            }
        }
    }
};
