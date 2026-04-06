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
        $this->ensureAgendaDecisionLinkColumns();
        $this->ensureAgendaMinutesDiscussionColumn();
        $this->ensureDirectorateTabulationLabel();
        $this->ensureDecisionIssueColumns();
        $this->ensureDecisionOccurrencesTable();
        $this->backfillIssueTrackingData();
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_meeting_decision_occurrences')) {
            Schema::dropIfExists('corsec_meeting_decision_occurrences');
        }

        if (Schema::hasTable('corsec_meeting_decision_support_directorates')) {
            Schema::dropIfExists('corsec_meeting_decision_support_directorates');
        }

        if (Schema::hasTable('corsec_meeting_decision_support_users')) {
            Schema::dropIfExists('corsec_meeting_decision_support_users');
        }

        if (Schema::hasTable('corsec_meeting_decisions')) {
            DB::statement('DROP INDEX IF EXISTS corsec_meeting_decisions_decision_key_unique');
            DB::statement('DROP INDEX IF EXISTS corsec_meeting_decisions_root_idx');
            DB::statement('DROP INDEX IF EXISTS corsec_meeting_decisions_source_idx');
            DB::statement('DROP INDEX IF EXISTS corsec_meeting_decisions_issue_key_idx');
            DB::statement('DROP INDEX IF EXISTS corsec_meeting_decisions_first_discussed_idx');
            DB::statement('DROP INDEX IF EXISTS corsec_meeting_decisions_last_discussed_idx');

            Schema::table('corsec_meeting_decisions', function (Blueprint $table) {
                foreach ([
                    'issue_key',
                    'first_discussed_at',
                    'last_discussed_at',
                    'discussion_count',
                    'latest_update_at',
                    'latest_update_note',
                    'latest_progress_percent',
                    'aging_days',
                    'aging_bucket',
                ] as $column) {
                    if (Schema::hasColumn('corsec_meeting_decisions', $column)) {
                        $table->dropColumn($column);
                    }
                }
                if (Schema::hasColumn('corsec_meeting_decisions', 'source_decision_id')) {
                    $table->dropConstrainedForeignId('source_decision_id');
                }
                if (Schema::hasColumn('corsec_meeting_decisions', 'agenda_id')) {
                    $table->dropConstrainedForeignId('agenda_id');
                }
                if (Schema::hasColumn('corsec_meeting_decisions', 'root_decision_id')) {
                    $table->dropConstrainedForeignId('root_decision_id');
                }
                if (Schema::hasColumn('corsec_meeting_decisions', 'decision_key')) {
                    $table->dropColumn('decision_key');
                }
            });
        }

        if (Schema::hasTable('corsec_meeting_agendas')) {
            Schema::table('corsec_meeting_agendas', function (Blueprint $table) {
                if (Schema::hasColumn('corsec_meeting_agendas', 'minutes_discussion')) {
                    $table->dropColumn('minutes_discussion');
                }
                if (Schema::hasColumn('corsec_meeting_agendas', 'source_decision_id')) {
                    $table->dropConstrainedForeignId('source_decision_id');
                }
            });
        }

        if (Schema::hasTable('corsec_directorates') && Schema::hasColumn('corsec_directorates', 'tabulation_label')) {
            Schema::table('corsec_directorates', function (Blueprint $table) {
                $table->dropColumn('tabulation_label');
            });
        }

        if (Schema::hasTable('corsec_meetings')) {
            DB::statement('DROP INDEX IF EXISTS corsec_meetings_dir_reminder_sent_idx');

            Schema::table('corsec_meetings', function (Blueprint $table) {
                if (Schema::hasColumn('corsec_meetings', 'directorate_reminder_sent_at')) {
                    $table->dropColumn('directorate_reminder_sent_at');
                }
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
            if (!Schema::hasColumn('corsec_meetings', 'directorate_reminder_sent_at')) {
                $table->timestamp('directorate_reminder_sent_at')->nullable()->index('corsec_meetings_dir_reminder_sent_idx');
            }
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

    private function ensureAgendaDecisionLinkColumns(): void
    {
        if (Schema::hasTable('corsec_meeting_agendas')) {
            Schema::table('corsec_meeting_agendas', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_meeting_agendas', 'source_decision_id')) {
                    $table->foreignId('source_decision_id')
                        ->nullable()
                        ->after('pic_user_id')
                        ->constrained('corsec_meeting_decisions')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('corsec_meeting_decisions')) {
            Schema::table('corsec_meeting_decisions', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_meeting_decisions', 'agenda_id')) {
                    $table->foreignId('agenda_id')
                        ->nullable()
                        ->after('meeting_id')
                        ->constrained('corsec_meeting_agendas')
                        ->nullOnDelete();
                }
            });
        }
    }

    private function ensureAgendaMinutesDiscussionColumn(): void
    {
        if (!Schema::hasTable('corsec_meeting_agendas')) {
            return;
        }

        Schema::table('corsec_meeting_agendas', function (Blueprint $table) {
            if (!Schema::hasColumn('corsec_meeting_agendas', 'minutes_discussion')) {
                $table->longText('minutes_discussion')->nullable()->after('description');
            }
        });
    }

    private function ensureDirectorateTabulationLabel(): void
    {
        if (!Schema::hasTable('corsec_directorates')) {
            return;
        }

        Schema::table('corsec_directorates', function (Blueprint $table) {
            if (!Schema::hasColumn('corsec_directorates', 'tabulation_label')) {
                $table->string('tabulation_label', 150)->nullable()->after('name');
            }
        });

        DB::table('corsec_directorates')
            ->whereNull('tabulation_label')
            ->update([
                'tabulation_label' => DB::raw('name'),
                'updated_at' => now(),
            ]);
    }

    private function ensureDecisionIssueColumns(): void
    {
        if (!Schema::hasTable('corsec_meeting_decisions')) {
            return;
        }

        Schema::table('corsec_meeting_decisions', function (Blueprint $table) {
            if (!Schema::hasColumn('corsec_meeting_decisions', 'issue_key')) {
                $table->string('issue_key', 50)->nullable()->after('decision_key');
            }
            if (!Schema::hasColumn('corsec_meeting_decisions', 'first_discussed_at')) {
                $table->date('first_discussed_at')->nullable()->after('target_date');
            }
            if (!Schema::hasColumn('corsec_meeting_decisions', 'last_discussed_at')) {
                $table->date('last_discussed_at')->nullable()->after('first_discussed_at');
            }
            if (!Schema::hasColumn('corsec_meeting_decisions', 'discussion_count')) {
                $table->unsignedInteger('discussion_count')->default(1)->after('last_discussed_at');
            }
            if (!Schema::hasColumn('corsec_meeting_decisions', 'latest_update_at')) {
                $table->date('latest_update_at')->nullable()->after('discussion_count');
            }
            if (!Schema::hasColumn('corsec_meeting_decisions', 'latest_update_note')) {
                $table->text('latest_update_note')->nullable()->after('latest_update_at');
            }
            if (!Schema::hasColumn('corsec_meeting_decisions', 'latest_progress_percent')) {
                $table->unsignedTinyInteger('latest_progress_percent')->default(0)->after('latest_update_note');
            }
            if (!Schema::hasColumn('corsec_meeting_decisions', 'aging_days')) {
                $table->unsignedInteger('aging_days')->nullable()->after('latest_progress_percent');
            }
            if (!Schema::hasColumn('corsec_meeting_decisions', 'aging_bucket')) {
                $table->string('aging_bucket', 20)->nullable()->after('aging_days');
            }
        });

        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_meeting_decisions_issue_key_idx ON corsec_meeting_decisions (issue_key)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_meeting_decisions_first_discussed_idx ON corsec_meeting_decisions (first_discussed_at)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_meeting_decisions_last_discussed_idx ON corsec_meeting_decisions (last_discussed_at)'
        );
    }

    private function ensureDecisionSupportPivot(): void
    {
        if (
            Schema::hasTable('corsec_meeting_decision_support_directorates')
            || !Schema::hasTable('corsec_meeting_decisions')
            || !Schema::hasTable('corsec_directorates')
        ) {
            return;
        }

        Schema::create('corsec_meeting_decision_support_directorates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_decision_id')
                ->constrained('corsec_meeting_decisions')
                ->cascadeOnDelete();
            $table->foreignId('directorate_id')
                ->constrained('corsec_directorates')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['meeting_decision_id', 'directorate_id'],
                'corsec_meeting_decision_support_directorates_unique'
            );
        });
    }

    private function ensureDecisionSupportUserPivot(): void
    {
        if (
            Schema::hasTable('corsec_meeting_decision_support_users')
            || !Schema::hasTable('corsec_meeting_decisions')
            || !Schema::hasTable('users')
        ) {
            return;
        }

        Schema::create('corsec_meeting_decision_support_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_decision_id')
                ->constrained('corsec_meeting_decisions')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['meeting_decision_id', 'user_id'],
                'corsec_meeting_decision_support_users_unique'
            );
        });
    }

    private function ensureDecisionOccurrencesTable(): void
    {
        if (
            Schema::hasTable('corsec_meeting_decision_occurrences')
            || !Schema::hasTable('corsec_meeting_decisions')
            || !Schema::hasTable('corsec_meetings')
        ) {
            return;
        }

        Schema::create('corsec_meeting_decision_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('root_decision_id')
                ->constrained('corsec_meeting_decisions')
                ->cascadeOnDelete();
            $table->foreignId('meeting_decision_id')
                ->constrained('corsec_meeting_decisions')
                ->cascadeOnDelete();
            $table->foreignId('meeting_id')
                ->constrained('corsec_meetings')
                ->cascadeOnDelete();
            $table->foreignId('source_decision_id')
                ->nullable()
                ->constrained('corsec_meeting_decisions')
                ->nullOnDelete();
            $table->date('occurred_at')->nullable()->index();
            $table->string('status_snapshot', 30)->nullable()->index();
            $table->unsignedTinyInteger('progress_snapshot')->default(0);
            $table->text('note_snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['root_decision_id', 'meeting_id'],
                'corsec_meeting_decision_occurrences_root_meeting_unique'
            );
            $table->unique(
                ['meeting_decision_id'],
                'corsec_meeting_decision_occurrences_decision_unique'
            );
        });
    }

    private function backfillIssueTrackingData(): void
    {
        if (!Schema::hasTable('corsec_meeting_decisions')) {
            return;
        }

        DB::statement(
            "UPDATE corsec_meeting_decisions\n"
            . "SET issue_key = 'ISS-' || LPAD(COALESCE(root_decision_id, id)::text, 6, '0')\n"
            . "WHERE issue_key IS NULL OR issue_key = ''"
        );

        if (Schema::hasTable('corsec_meeting_decision_occurrences') && Schema::hasTable('corsec_meetings')) {
            DB::statement(
                "INSERT INTO corsec_meeting_decision_occurrences (\n"
                . "    root_decision_id,\n"
                . "    meeting_decision_id,\n"
                . "    meeting_id,\n"
                . "    source_decision_id,\n"
                . "    occurred_at,\n"
                . "    status_snapshot,\n"
                . "    progress_snapshot,\n"
                . "    note_snapshot,\n"
                . "    created_by,\n"
                . "    created_at,\n"
                . "    updated_at\n"
                . ")\n"
                . "SELECT\n"
                . "    COALESCE(d.root_decision_id, d.id) AS root_decision_id,\n"
                . "    d.id AS meeting_decision_id,\n"
                . "    d.meeting_id,\n"
                . "    d.source_decision_id,\n"
                . "    COALESCE(m.meeting_at::date, d.created_at::date) AS occurred_at,\n"
                . "    d.status AS status_snapshot,\n"
                . "    COALESCE(du.progress_percent, CASE WHEN d.status = 'done' THEN 100 ELSE 0 END) AS progress_snapshot,\n"
                . "    du.note AS note_snapshot,\n"
                . "    d.created_by,\n"
                . "    COALESCE(d.created_at, now()) AS created_at,\n"
                . "    COALESCE(d.updated_at, now()) AS updated_at\n"
                . "FROM corsec_meeting_decisions d\n"
                . "INNER JOIN corsec_meetings m ON m.id = d.meeting_id\n"
                . "LEFT JOIN LATERAL (\n"
                . "    SELECT progress_percent, note\n"
                . "    FROM corsec_decision_updates updates\n"
                . "    WHERE updates.meeting_decision_id = d.id\n"
                . "    ORDER BY COALESCE(updates.happened_at, updates.created_at::date) DESC, updates.id DESC\n"
                . ") du ON true\n"
                . "ON CONFLICT ON CONSTRAINT corsec_meeting_decision_occurrences_decision_unique DO NOTHING"
            );

            DB::statement(
                "WITH occurrence_stats AS (\n"
                . "    SELECT\n"
                . "        root_decision_id,\n"
                . "        MIN(occurred_at) AS first_discussed_at,\n"
                . "        MAX(occurred_at) AS last_discussed_at,\n"
                . "        COUNT(*) AS discussion_count\n"
                . "    FROM corsec_meeting_decision_occurrences\n"
                . "    GROUP BY root_decision_id\n"
                . "),\n"
                . "latest_updates AS (\n"
                . "    SELECT DISTINCT ON (COALESCE(d.root_decision_id, d.id))\n"
                . "        COALESCE(d.root_decision_id, d.id) AS root_decision_id,\n"
                . "        COALESCE(updates.happened_at, updates.created_at::date) AS latest_update_at,\n"
                . "        updates.note AS latest_update_note,\n"
                . "        updates.progress_percent AS latest_progress_percent\n"
                . "    FROM corsec_meeting_decisions d\n"
                . "    INNER JOIN corsec_decision_updates updates ON updates.meeting_decision_id = d.id\n"
                . "    ORDER BY COALESCE(d.root_decision_id, d.id), COALESCE(updates.happened_at, updates.created_at::date) DESC, updates.id DESC\n"
                . ")\n"
                . "UPDATE corsec_meeting_decisions root\n"
                . "SET\n"
                . "    first_discussed_at = occurrence_stats.first_discussed_at,\n"
                . "    last_discussed_at = occurrence_stats.last_discussed_at,\n"
                . "    discussion_count = occurrence_stats.discussion_count,\n"
                . "    latest_update_at = latest_updates.latest_update_at,\n"
                . "    latest_update_note = latest_updates.latest_update_note,\n"
                . "    latest_progress_percent = COALESCE(latest_updates.latest_progress_percent, root.latest_progress_percent)\n"
                . "FROM occurrence_stats\n"
                . "LEFT JOIN latest_updates ON latest_updates.root_decision_id = occurrence_stats.root_decision_id\n"
                . "WHERE root.id = occurrence_stats.root_decision_id"
            );

            DB::statement(
                "UPDATE corsec_meeting_decisions\n"
                . "SET aging_days = CASE\n"
                . "        WHEN first_discussed_at IS NULL THEN NULL\n"
                . "        WHEN status IN ('done', 'dropped') THEN NULL\n"
                . "        ELSE GREATEST(0, CURRENT_DATE - first_discussed_at)\n"
                . "    END,\n"
                . "    aging_bucket = CASE\n"
                . "        WHEN first_discussed_at IS NULL OR status IN ('done', 'dropped') THEN NULL\n"
                . "        WHEN CURRENT_DATE - first_discussed_at < 30 THEN 'cat_1'\n"
                . "        WHEN CURRENT_DATE - first_discussed_at < 91 THEN 'cat_2'\n"
                . "        WHEN CURRENT_DATE - first_discussed_at < 181 THEN 'cat_3'\n"
                . "        WHEN CURRENT_DATE - first_discussed_at < 271 THEN 'cat_4'\n"
                . "        ELSE 'cat_5'\n"
                . "    END"
            );
        }
    }
};
