<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->normalizeApprovalRequestsUserColumns();
        $this->ensureLetterTypeScopeAndLinks();
        $this->ensureIncomingLetterSchema();
        $this->ensureOutgoingLetterSchema();
        $this->mergeAndRemoveLegacyLetterTables();
        $this->normalizeLegacyOutgoingStatuses();
        $this->ensureMeetingSchema();
        $this->ensureMeetingParticipantSchema();
        $this->ensureWorkProgramSchema();
        $this->removeLegacyBankSchema();
    }

    public function down(): void
    {
        // Consolidated migration: rollback is intentionally skipped.
    }

    private function normalizeApprovalRequestsUserColumns(): void
    {
        if (!Schema::hasTable('approval_requests')) {
            return;
        }

        $columns = ['created_by', 'updated_by', 'deleted_by', 'authorized_by'];
        foreach ($columns as $column) {
            if (!Schema::hasColumn('approval_requests', $column)) {
                continue;
            }

            DB::statement(
                "ALTER TABLE approval_requests ALTER COLUMN {$column} TYPE BIGINT USING NULLIF({$column}::text, '')::bigint"
            );

            $constraintName = 'approval_requests_' . $column . '_foreign';
            if ($this->foreignKeyExists('approval_requests', $constraintName)) {
                DB::statement("ALTER TABLE approval_requests DROP CONSTRAINT IF EXISTS {$constraintName}");
            }
        }

        Schema::table('approval_requests', function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                if (!Schema::hasColumn('approval_requests', $column)) {
                    continue;
                }

                $constraintName = 'approval_requests_' . $column . '_foreign';
                if (!$this->foreignKeyExists('approval_requests', $constraintName)) {
                    $table->foreign($column, $constraintName)->references('id')->on('users')->nullOnDelete();
                }
            }
        });
    }

    private function ensureLetterTypeScopeAndLinks(): void
    {
        if (Schema::hasTable('corsec_letter_types')) {
            if (!Schema::hasColumn('corsec_letter_types', 'scope')) {
                Schema::table('corsec_letter_types', function (Blueprint $table) {
                    $table->string('scope', 10)->default('in')->index('corsec_letter_types_scope_idx');
                });
            }

            DB::table('corsec_letter_types')
                ->whereNull('scope')
                ->update(['scope' => 'in']);

            DB::statement('ALTER TABLE corsec_letter_types DROP CONSTRAINT IF EXISTS corsec_letter_types_code_unique');
            DB::statement('DROP INDEX IF EXISTS corsec_letter_types_code_unique');
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS corsec_letter_types_code_scope_unique ON corsec_letter_types (code, scope)'
            );
        }

        if (Schema::hasTable('corsec_outgoing_letters') && !Schema::hasColumn('corsec_outgoing_letters', 'letter_type_id')) {
            Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
                $table->foreignId('letter_type_id')->nullable()->constrained('corsec_letter_types')->nullOnDelete();
            });
        }
    }

    private function ensureIncomingLetterSchema(): void
    {
        if (!Schema::hasTable('corsec_incoming_letters')) {
            return;
        }

        Schema::table('corsec_incoming_letters', function (Blueprint $table) {
            if (!Schema::hasColumn('corsec_incoming_letters', 'registration_no')) {
                $table->string('registration_no')->nullable()->unique();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'letter_date')) {
                $table->date('letter_date')->nullable();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'summary')) {
                $table->text('summary')->nullable();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'sender_id')) {
                $table->foreignId('sender_id')->nullable()->constrained('corsec_senders')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'sender_other')) {
                $table->string('sender_other', 150)->nullable();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'customer_branch_id')) {
                $table->foreignId('customer_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'letter_type_id')) {
                $table->foreignId('letter_type_id')->nullable()->constrained('corsec_letter_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'letter_type_other')) {
                $table->string('letter_type_other', 150)->nullable();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'target_directorate_id')) {
                $table->foreignId('target_directorate_id')->nullable()->constrained('corsec_directorates')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'followup_action')) {
                $table->string('followup_action', 100)->nullable();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'followup_detail')) {
                $table->json('followup_detail')->nullable();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'followup_note')) {
                $table->text('followup_note')->nullable();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'followup_submitted_at')) {
                $table->timestamp('followup_submitted_at')->nullable();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'followup_submitted_by')) {
                $table->foreignId('followup_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'last_routed_from_directorate_id')) {
                $table->foreignId('last_routed_from_directorate_id')->nullable()->constrained('corsec_directorates')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'last_routed_to_directorate_id')) {
                $table->foreignId('last_routed_to_directorate_id')->nullable()->constrained('corsec_directorates')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'last_routed_from_user_id')) {
                $table->foreignId('last_routed_from_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'last_routed_to_user_id')) {
                $table->foreignId('last_routed_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'last_routed_at')) {
                $table->timestamp('last_routed_at')->nullable()->index('corsec_incoming_letters_last_routed_at_idx');
            }
            if (!Schema::hasColumn('corsec_incoming_letters', 'last_route_note')) {
                $table->text('last_route_note')->nullable();
            }
        });

        if (!Schema::hasTable('corsec_incoming_letter_directorates')) {
            Schema::create('corsec_incoming_letter_directorates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('incoming_letter_id')->constrained('corsec_incoming_letters')->cascadeOnDelete();
                $table->foreignId('directorate_id')->constrained('corsec_directorates')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['incoming_letter_id', 'directorate_id'], 'incoming_letter_directorate_unique');
            });
        }

        if (Schema::hasTable('corsec_incoming_letter_routes') && !Schema::hasColumn('corsec_incoming_letter_routes', 'from_directorate_id')) {
            Schema::table('corsec_incoming_letter_routes', function (Blueprint $table) {
                $table->foreignId('from_directorate_id')->nullable()->constrained('corsec_directorates')->nullOnDelete();
            });
        }

        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_target_status_idx ON corsec_incoming_letters (target_directorate_id, status)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_created_status_idx ON corsec_incoming_letters (created_by, status)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_letter_date_idx ON corsec_incoming_letters (letter_date)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_received_date_idx ON corsec_incoming_letters (received_date)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_sender_id_idx ON corsec_incoming_letters (sender_id)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_incoming_letters_letter_type_id_idx ON corsec_incoming_letters (letter_type_id)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_incoming_letter_directorates_directorate_idx ON corsec_incoming_letter_directorates (directorate_id, incoming_letter_id)'
        );
    }

    private function ensureOutgoingLetterSchema(): void
    {
        if (!Schema::hasTable('corsec_outgoing_letters')) {
            return;
        }

        Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
            if (!Schema::hasColumn('corsec_outgoing_letters', 'registration_no')) {
                $table->string('registration_no')->nullable()->unique();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'order_date')) {
                $table->date('order_date')->nullable();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'recipient_id')) {
                $table->foreignId('recipient_id')->nullable()->constrained('corsec_senders')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'recipient_other')) {
                $table->string('recipient_other', 150)->nullable();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'summary')) {
                $table->text('summary')->nullable();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'perihal_type')) {
                $table->string('perihal_type')->nullable();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'perihal_incoming_letter_id')) {
                $table->foreignId('perihal_incoming_letter_id')->nullable()->constrained('corsec_incoming_letters')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'perihal_text')) {
                $table->string('perihal_text')->nullable();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'note')) {
                $table->text('note')->nullable();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'compliance_attachment_id')) {
                $table->foreignId('compliance_attachment_id')->nullable()->constrained('corsec_attachments')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'letter_no')) {
                $table->string('letter_no')->nullable();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'number_requested_at')) {
                $table->timestamp('number_requested_at')->nullable()->index('corsec_outgoing_letters_number_requested_at_idx');
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'number_requested_by')) {
                $table->foreignId('number_requested_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'number_request_note')) {
                $table->text('number_request_note')->nullable();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'requester_directorate_id')) {
                $table->foreignId('requester_directorate_id')->nullable()->constrained('corsec_directorates')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'letter_type_id')) {
                $table->foreignId('letter_type_id')->nullable()->constrained('corsec_letter_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'final_upload_date')) {
                $table->date('final_upload_date')->nullable();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'cancel_previous_status')) {
                $table->string('cancel_previous_status')->nullable();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'cancel_requested_at')) {
                $table->timestamp('cancel_requested_at')->nullable()->index('corsec_outgoing_letters_cancel_requested_at_idx');
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'cancel_requested_by')) {
                $table->foreignId('cancel_requested_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->index('corsec_outgoing_letters_cancelled_at_idx');
            }
            if (!Schema::hasColumn('corsec_outgoing_letters', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_requester_status_idx ON corsec_outgoing_letters (requester_directorate_id, status)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_order_date_idx ON corsec_outgoing_letters (order_date)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_letter_no_idx ON corsec_outgoing_letters (letter_no)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS corsec_outgoing_letters_perihal_incoming_idx ON corsec_outgoing_letters (perihal_incoming_letter_id)'
        );
    }

    private function mergeAndRemoveLegacyLetterTables(): void
    {
        if (Schema::hasTable('corsec_incoming_letter_routes') && Schema::hasTable('corsec_incoming_letters')) {
            DB::statement(
                "UPDATE corsec_incoming_letters AS letters\n"
                . "SET last_routed_from_directorate_id = routes.from_directorate_id,\n"
                . "    last_routed_to_directorate_id = routes.to_directorate_id,\n"
                . "    last_routed_from_user_id = routes.from_user_id,\n"
                . "    last_routed_to_user_id = routes.to_user_id,\n"
                . "    last_route_note = routes.note,\n"
                . "    last_routed_at = routes.sent_at\n"
                . "FROM (\n"
                . "    SELECT DISTINCT ON (incoming_letter_id) incoming_letter_id, from_directorate_id, to_directorate_id, from_user_id, to_user_id, note, sent_at, id\n"
                . "    FROM corsec_incoming_letter_routes\n"
                . "    ORDER BY incoming_letter_id, sent_at DESC NULLS LAST, id DESC\n"
                . ") AS routes\n"
                . "WHERE letters.id = routes.incoming_letter_id"
            );
        }

        if (Schema::hasTable('corsec_outgoing_letter_number_requests') && Schema::hasTable('corsec_outgoing_letters')) {
            DB::statement(
                "UPDATE corsec_outgoing_letters AS letters\n"
                . "SET number_requested_at = req.requested_at,\n"
                . "    number_requested_by = req.requested_by,\n"
                . "    number_request_note = req.note\n"
                . "FROM (\n"
                . "    SELECT DISTINCT ON (outgoing_letter_id) outgoing_letter_id, requested_at, requested_by, note, id\n"
                . "    FROM corsec_outgoing_letter_number_requests\n"
                . "    ORDER BY outgoing_letter_id, requested_at DESC NULLS LAST, id DESC\n"
                . ") AS req\n"
                . "WHERE letters.id = req.outgoing_letter_id"
            );
        }

        if (Schema::hasTable('corsec_outgoing_letters') && Schema::hasColumn('corsec_outgoing_letters', 'letter_number_id')) {
            DB::statement('ALTER TABLE corsec_outgoing_letters DROP CONSTRAINT IF EXISTS corsec_outgoing_letters_letter_number_id_foreign');
            Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
                $table->dropColumn('letter_number_id');
            });
        }

        if (Schema::hasTable('corsec_incoming_letter_routes')) {
            Schema::dropIfExists('corsec_incoming_letter_routes');
        }
        if (Schema::hasTable('corsec_outgoing_letter_number_requests')) {
            Schema::dropIfExists('corsec_outgoing_letter_number_requests');
        }
        if (Schema::hasTable('corsec_letter_numbers')) {
            Schema::dropIfExists('corsec_letter_numbers');
        }
    }

    private function normalizeLegacyOutgoingStatuses(): void
    {
        if (!Schema::hasTable('corsec_outgoing_letters')) {
            return;
        }

        DB::table('corsec_outgoing_letters')
            ->where('status', 'numbering')
            ->update([
                'status' => 'waiting_verification',
                'updated_at' => now(),
            ]);

        DB::table('corsec_outgoing_letters')
            ->where('status', 'final_uploaded')
            ->update([
                'status' => 'waiting_final_upload',
                'updated_at' => now(),
            ]);
    }

    private function ensureMeetingSchema(): void
    {
        if (Schema::hasTable('corsec_meetings')) {
            Schema::table('corsec_meetings', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_meetings', 'schedule_sent_at')) {
                    $table->timestamp('schedule_sent_at')->nullable()->index('corsec_meetings_schedule_sent_at_idx');
                }
                if (!Schema::hasColumn('corsec_meetings', 'conducted_at')) {
                    $table->timestamp('conducted_at')->nullable()->index('corsec_meetings_conducted_at_idx');
                }
                if (!Schema::hasColumn('corsec_meetings', 'finished_at')) {
                    $table->timestamp('finished_at')->nullable()->index('corsec_meetings_finished_at_idx');
                }
            });

            DB::table('corsec_meetings')
                ->where(function ($query) {
                    $query->whereNull('status')->orWhere('status', 'planned');
                })
                ->update([
                    'status' => 'draft',
                    'updated_at' => now(),
                ]);

            DB::statement("ALTER TABLE corsec_meetings ALTER COLUMN status SET DEFAULT 'draft'");
        }

        if (Schema::hasTable('corsec_meeting_minutes')) {
            Schema::table('corsec_meeting_minutes', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_meeting_minutes', 'final_minutes_attachment_id')) {
                    $table->foreignId('final_minutes_attachment_id')->nullable()->constrained('corsec_attachments')->nullOnDelete();
                }
                if (!Schema::hasColumn('corsec_meeting_minutes', 'circulated_at')) {
                    $table->timestamp('circulated_at')->nullable()->index('corsec_meeting_minutes_circulated_at_idx');
                }
                if (!Schema::hasColumn('corsec_meeting_minutes', 'finalized_at')) {
                    $table->timestamp('finalized_at')->nullable()->index('corsec_meeting_minutes_finalized_at_idx');
                }
            });
        }

        if (Schema::hasTable('corsec_decision_updates')) {
            Schema::table('corsec_decision_updates', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_decision_updates', 'update_type')) {
                    $table->string('update_type')->default('progress')->index('corsec_decision_updates_update_type_idx');
                }
                if (!Schema::hasColumn('corsec_decision_updates', 'happened_at')) {
                    $table->date('happened_at')->nullable()->index('corsec_decision_updates_happened_at_idx');
                }
                if (!Schema::hasColumn('corsec_decision_updates', 'is_on_target')) {
                    $table->boolean('is_on_target')->nullable();
                }
                if (!Schema::hasColumn('corsec_decision_updates', 'reason')) {
                    $table->text('reason')->nullable();
                }
            });
        }

        if (Schema::hasTable('corsec_meeting_agendas') && !Schema::hasColumn('corsec_meeting_agendas', 'pic_user_id')) {
            Schema::table('corsec_meeting_agendas', function (Blueprint $table) {
                $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('corsec_meeting_decisions') && !Schema::hasColumn('corsec_meeting_decisions', 'pic_user_id')) {
            Schema::table('corsec_meeting_decisions', function (Blueprint $table) {
                $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('corsec_meeting_agendas')) {
            DB::statement(
                "UPDATE corsec_meeting_agendas AS agendas\n"
                . "SET owner_directorate_id = users.directorate_id\n"
                . "FROM users\n"
                . "WHERE agendas.owner_directorate_id IS NULL\n"
                . "  AND agendas.pic_user_id IS NOT NULL\n"
                . "  AND users.id = agendas.pic_user_id"
            );
        }

        if (Schema::hasTable('corsec_meeting_decisions')) {
            DB::statement(
                "UPDATE corsec_meeting_decisions AS decisions\n"
                . "SET owner_directorate_id = users.directorate_id\n"
                . "FROM users\n"
                . "WHERE decisions.owner_directorate_id IS NULL\n"
                . "  AND decisions.pic_user_id IS NOT NULL\n"
                . "  AND users.id = decisions.pic_user_id"
            );
        }

        if (Schema::hasTable('corsec_meeting_decisions')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_meeting_decisions_owner_status_idx ON corsec_meeting_decisions (owner_directorate_id, status)'
            );
        }

        if (Schema::hasTable('corsec_decision_updates')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_decision_updates_decision_status_idx ON corsec_decision_updates (meeting_decision_id, status)'
            );
        }
    }

    private function ensureMeetingParticipantSchema(): void
    {
        if (!Schema::hasTable('corsec_meeting_participants')) {
            Schema::create('corsec_meeting_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_id')->constrained('corsec_meetings')->cascadeOnDelete();
                $table->foreignId('directorate_id')->nullable()->constrained('corsec_directorates')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['meeting_id', 'directorate_id'], 'corsec_meeting_participants_unique');
            });
        }

        if (Schema::hasTable('corsec_meeting_participants')) {
            if (!Schema::hasColumn('corsec_meeting_participants', 'user_id')) {
                Schema::table('corsec_meeting_participants', function (Blueprint $table) {
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                });
            }

            if (Schema::hasColumn('corsec_meeting_participants', 'directorate_id')) {
                DB::statement('ALTER TABLE corsec_meeting_participants ALTER COLUMN directorate_id DROP NOT NULL');
            }

            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_meeting_participants_directorate_idx ON corsec_meeting_participants (directorate_id, meeting_id)'
            );
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS corsec_meeting_participants_meeting_user_unique ON corsec_meeting_participants (meeting_id, user_id)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_meeting_participants_user_meeting_idx ON corsec_meeting_participants (user_id, meeting_id)'
            );
        }
    }

    private function ensureWorkProgramSchema(): void
    {
        if (Schema::hasTable('corsec_work_program_items')) {
            Schema::table('corsec_work_program_items', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_work_program_items', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->index('corsec_work_program_items_completed_at_idx');
                }
                if (!Schema::hasColumn('corsec_work_program_items', 'initial_target_date')) {
                    $table->date('initial_target_date')->nullable()->index('corsec_work_program_items_initial_target_date_idx');
                }
            });

            DB::table('corsec_work_program_items')
                ->whereNull('initial_target_date')
                ->update(['initial_target_date' => DB::raw('target_date')]);

            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_work_program_items_program_status_idx ON corsec_work_program_items (work_program_id, status)'
            );
        }

        if (Schema::hasTable('corsec_work_program_updates')) {
            Schema::table('corsec_work_program_updates', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_work_program_updates', 'action')) {
                    $table->string('action')->default('progress')->index('corsec_work_program_updates_action_idx');
                }
                if (!Schema::hasColumn('corsec_work_program_updates', 'revised_target_date')) {
                    $table->date('revised_target_date')->nullable()->index('corsec_work_program_updates_revised_target_date_idx');
                }
            });

            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_work_program_updates_item_status_idx ON corsec_work_program_updates (work_program_item_id, status)'
            );
        }

        if (Schema::hasTable('corsec_work_programs')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS corsec_work_programs_directorate_status_idx ON corsec_work_programs (directorate_id, status)'
            );
        }
    }

    private function removeLegacyBankSchema(): void
    {
        if (Schema::hasTable('corsec_incoming_letters')) {
            DB::statement('ALTER TABLE corsec_incoming_letters DROP COLUMN IF EXISTS counterparty_bank_id CASCADE');
        }

        DB::statement('DROP TABLE IF EXISTS corsec_banks CASCADE');
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $result = DB::select(
            "SELECT 1\n"
            . "FROM information_schema.table_constraints\n"
            . "WHERE table_schema = current_schema()\n"
            . "  AND table_name = ?\n"
            . "  AND constraint_name = ?\n"
            . "  AND constraint_type = 'FOREIGN KEY'\n"
            . "LIMIT 1",
            [$table, $constraint]
        );

        return count($result) > 0;
    }
};
