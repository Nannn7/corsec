<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->ensureMeetingTypesTable();
        $this->ensureDirektoratFlowColumns();
        $this->ensureDecisionTrackingColumns();
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_meeting_decisions')) {
            DB::statement('DROP INDEX IF EXISTS corsec_meeting_decisions_decision_key_unique');
            DB::statement('DROP INDEX IF EXISTS corsec_meeting_decisions_root_idx');
            DB::statement('DROP INDEX IF EXISTS corsec_meeting_decisions_source_idx');

            Schema::table('corsec_meeting_decisions', function (Blueprint $table) {
                if (Schema::hasColumn('corsec_meeting_decisions', 'source_decision_id')) {
                    $table->dropConstrainedForeignId('source_decision_id');
                }
                if (Schema::hasColumn('corsec_meeting_decisions', 'root_decision_id')) {
                    $table->dropConstrainedForeignId('root_decision_id');
                }
                if (Schema::hasColumn('corsec_meeting_decisions', 'decision_key')) {
                    $table->dropColumn('decision_key');
                }
            });
        }

        if (Schema::hasTable('corsec_meetings')) {
            Schema::table('corsec_meetings', function (Blueprint $table) {
                if (Schema::hasColumn('corsec_meetings', 'directorate_responded_by')) {
                    $table->dropConstrainedForeignId('directorate_responded_by');
                }
                if (Schema::hasColumn('corsec_meetings', 'directorate_responded_at')) {
                    $table->dropColumn('directorate_responded_at');
                }
                if (Schema::hasColumn('corsec_meetings', 'directorate_response_note')) {
                    $table->dropColumn('directorate_response_note');
                }
                if (Schema::hasColumn('corsec_meetings', 'directorate_response_status')) {
                    $table->dropColumn('directorate_response_status');
                }
            });
        }

        if (Schema::hasTable('corsec_meeting_types')) {
            Schema::dropIfExists('corsec_meeting_types');
        }
    }

    private function ensureMeetingTypesTable(): void
    {
        if (!Schema::hasTable('corsec_meeting_types')) {
            Schema::create('corsec_meeting_types', function (Blueprint $table) {
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
            return;
        }

        Schema::table('corsec_meeting_types', function (Blueprint $table) {
            if (!Schema::hasColumn('corsec_meeting_types', 'uuid')) {
                $table->uuid('uuid')->nullable();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'code')) {
                $table->string('code', 50)->nullable();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'name')) {
                $table->string('name', 150)->nullable();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'status')) {
                $table->boolean('status')->default(true);
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'authorized_at')) {
                $table->timestamp('authorized_at')->nullable();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'authorized_status')) {
                $table->string('authorized_status')->nullable();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'authorized_by')) {
                $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'deleted_by')) {
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'deleted_at')) {
                $table->softDeletes();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('corsec_meeting_types', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS corsec_meeting_types_uuid_unique ON corsec_meeting_types (uuid)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS corsec_meeting_types_code_unique ON corsec_meeting_types (code)');
        DB::statement('CREATE INDEX IF NOT EXISTS corsec_meeting_types_status_idx ON corsec_meeting_types (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS corsec_meeting_types_authorized_status_idx ON corsec_meeting_types (authorized_status)');
    }

    private function ensureDirektoratFlowColumns(): void
    {
        if (!Schema::hasTable('corsec_meetings')) {
            return;
        }

        Schema::table('corsec_meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('corsec_meetings', 'directorate_response_status')) {
                $table->string('directorate_response_status')->nullable()->index('corsec_meetings_dir_response_status_idx');
            }
            if (!Schema::hasColumn('corsec_meetings', 'directorate_response_note')) {
                $table->text('directorate_response_note')->nullable();
            }
            if (!Schema::hasColumn('corsec_meetings', 'directorate_responded_at')) {
                $table->timestamp('directorate_responded_at')->nullable()->index('corsec_meetings_dir_responded_at_idx');
            }
            if (!Schema::hasColumn('corsec_meetings', 'directorate_responded_by')) {
                $table->foreignId('directorate_responded_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    private function ensureDecisionTrackingColumns(): void
    {
        if (!Schema::hasTable('corsec_meeting_decisions')) {
            return;
        }

        Schema::table('corsec_meeting_decisions', function (Blueprint $table) {
            if (!Schema::hasColumn('corsec_meeting_decisions', 'decision_key')) {
                $table->string('decision_key')->nullable();
            }
            if (!Schema::hasColumn('corsec_meeting_decisions', 'root_decision_id')) {
                $table->foreignId('root_decision_id')->nullable()->constrained('corsec_meeting_decisions')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_meeting_decisions', 'source_decision_id')) {
                $table->foreignId('source_decision_id')->nullable()->constrained('corsec_meeting_decisions')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('corsec_meeting_decisions', 'decision_key')) {
            DB::statement(
                "UPDATE corsec_meeting_decisions\n"
                . "SET decision_key = 'TLR-' || LPAD(id::text, 6, '0')\n"
                . "WHERE decision_key IS NULL OR decision_key = ''"
            );
        }

        if (Schema::hasColumn('corsec_meeting_decisions', 'root_decision_id')) {
            DB::statement(
                'UPDATE corsec_meeting_decisions SET root_decision_id = id WHERE root_decision_id IS NULL'
            );
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS corsec_meeting_decisions_decision_key_unique '
            . 'ON corsec_meeting_decisions (decision_key)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_meeting_decisions_root_idx '
            . 'ON corsec_meeting_decisions (root_decision_id)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_meeting_decisions_source_idx '
            . 'ON corsec_meeting_decisions (source_decision_id)'
        );
    }
};
