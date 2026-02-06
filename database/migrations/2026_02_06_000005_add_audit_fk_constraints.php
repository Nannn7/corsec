<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        $this->addAuditForeignKeys('branches');
        $this->addAuditForeignKeys('currencies');
        $this->addAuditForeignKeys('holiday_calendars');
        $this->addAuditForeignKeys('permission_groups');
        $this->addAuditForeignKeys('users');
    }

    public function down(): void
    {
        $this->dropAuditForeignKeys('users');
        $this->dropAuditForeignKeys('permission_groups');
        $this->dropAuditForeignKeys('holiday_calendars');
        $this->dropAuditForeignKeys('currencies');
        $this->dropAuditForeignKeys('branches');
    }

    private function addAuditForeignKeys(string $table): void
    {
        $columns = ['created_by', 'updated_by', 'deleted_by', 'authorized_by'];
        foreach ($columns as $column) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }
            $name = $table . '_' . $column . '_foreign';
            if ($this->foreignKeyExists($table, $name)) {
                continue;
            }
            Schema::table($table, function (Blueprint $table) use ($column, $name) {
                $table->foreign($column, $name)
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    private function dropAuditForeignKeys(string $table): void
    {
        $columns = ['created_by', 'updated_by', 'deleted_by', 'authorized_by'];
        foreach ($columns as $column) {
            $name = $table . '_' . $column . '_foreign';
            if ($this->foreignKeyExists($table, $name)) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
            }
        }
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $result = DB::select(
            "SELECT constraint_name\n"
            . "FROM information_schema.table_constraints\n"
            . "WHERE table_schema = current_schema()\n"
            . "  AND table_name = ?\n"
            . "  AND constraint_name = ?\n"
            . "  AND constraint_type = 'FOREIGN KEY'",
            [$table, $name]
        );

        return count($result) > 0;
    }
};
