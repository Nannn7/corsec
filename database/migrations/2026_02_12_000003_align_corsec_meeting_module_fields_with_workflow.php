<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_meetings')) {
            Schema::table('corsec_meetings', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_meetings', 'schedule_sent_at')) {
                    $table->timestamp('schedule_sent_at')->nullable()
                        ->index('corsec_meetings_schedule_sent_at_idx');
                }

                if (!Schema::hasColumn('corsec_meetings', 'conducted_at')) {
                    $table->timestamp('conducted_at')->nullable()
                        ->index('corsec_meetings_conducted_at_idx');
                }

                if (!Schema::hasColumn('corsec_meetings', 'finished_at')) {
                    $table->timestamp('finished_at')->nullable()
                        ->index('corsec_meetings_finished_at_idx');
                }
            });

            DB::table('corsec_meetings')
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhere('status', 'planned');
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
                    $table->foreignId('final_minutes_attachment_id')
                        ->nullable()
                        ->constrained('corsec_attachments')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('corsec_meeting_minutes', 'circulated_at')) {
                    $table->timestamp('circulated_at')->nullable()
                        ->index('corsec_meeting_minutes_circulated_at_idx');
                }

                if (!Schema::hasColumn('corsec_meeting_minutes', 'finalized_at')) {
                    $table->timestamp('finalized_at')->nullable()
                        ->index('corsec_meeting_minutes_finalized_at_idx');
                }
            });
        }

        if (Schema::hasTable('corsec_decision_updates')) {
            Schema::table('corsec_decision_updates', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_decision_updates', 'update_type')) {
                    $table->string('update_type')->default('progress')
                        ->index('corsec_decision_updates_update_type_idx');
                }

                if (!Schema::hasColumn('corsec_decision_updates', 'happened_at')) {
                    $table->date('happened_at')->nullable()
                        ->index('corsec_decision_updates_happened_at_idx');
                }

                if (!Schema::hasColumn('corsec_decision_updates', 'is_on_target')) {
                    $table->boolean('is_on_target')->nullable();
                }

                if (!Schema::hasColumn('corsec_decision_updates', 'reason')) {
                    $table->text('reason')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_decision_updates')) {
            if (Schema::hasColumn('corsec_decision_updates', 'reason')) {
                DB::statement('ALTER TABLE corsec_decision_updates DROP COLUMN IF EXISTS reason CASCADE');
            }
            if (Schema::hasColumn('corsec_decision_updates', 'is_on_target')) {
                DB::statement('ALTER TABLE corsec_decision_updates DROP COLUMN IF EXISTS is_on_target CASCADE');
            }
            if (Schema::hasColumn('corsec_decision_updates', 'happened_at')) {
                DB::statement('ALTER TABLE corsec_decision_updates DROP COLUMN IF EXISTS happened_at CASCADE');
            }
            if (Schema::hasColumn('corsec_decision_updates', 'update_type')) {
                DB::statement('ALTER TABLE corsec_decision_updates DROP COLUMN IF EXISTS update_type CASCADE');
            }
        }

        if (Schema::hasTable('corsec_meeting_minutes')) {
            if (Schema::hasColumn('corsec_meeting_minutes', 'finalized_at')) {
                DB::statement('ALTER TABLE corsec_meeting_minutes DROP COLUMN IF EXISTS finalized_at CASCADE');
            }
            if (Schema::hasColumn('corsec_meeting_minutes', 'circulated_at')) {
                DB::statement('ALTER TABLE corsec_meeting_minutes DROP COLUMN IF EXISTS circulated_at CASCADE');
            }
            if (Schema::hasColumn('corsec_meeting_minutes', 'final_minutes_attachment_id')) {
                DB::statement('ALTER TABLE corsec_meeting_minutes DROP COLUMN IF EXISTS final_minutes_attachment_id CASCADE');
            }
        }

        if (Schema::hasTable('corsec_meetings')) {
            DB::statement("ALTER TABLE corsec_meetings ALTER COLUMN status SET DEFAULT 'planned'");

            if (Schema::hasColumn('corsec_meetings', 'finished_at')) {
                DB::statement('ALTER TABLE corsec_meetings DROP COLUMN IF EXISTS finished_at CASCADE');
            }
            if (Schema::hasColumn('corsec_meetings', 'conducted_at')) {
                DB::statement('ALTER TABLE corsec_meetings DROP COLUMN IF EXISTS conducted_at CASCADE');
            }
            if (Schema::hasColumn('corsec_meetings', 'schedule_sent_at')) {
                DB::statement('ALTER TABLE corsec_meetings DROP COLUMN IF EXISTS schedule_sent_at CASCADE');
            }
        }
    }
};
