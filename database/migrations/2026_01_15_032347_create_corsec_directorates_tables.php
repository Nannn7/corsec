<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        /**
         * 1) MASTER: corsec_directorates
         */
        if (!Schema::hasTable('corsec_directorates')) {
            Schema::create('corsec_directorates', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->string('code', 50)->unique();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->boolean('status')->default(true)->index();
                $table->boolean('is_meeting_operational')->default(false)->index();

                // authorized fields
                $table->timestamp('authorized_at')->nullable();
                $table->string('authorized_status')->nullable()->index();
                $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();

                // audit
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // Compatibility guard: ensure flag column exists on legacy table shape.
        if (Schema::hasTable('corsec_directorates') && !Schema::hasColumn('corsec_directorates', 'is_meeting_operational')) {
            Schema::table('corsec_directorates', function (Blueprint $table) {
                $table->boolean('is_meeting_operational')->default(false)->index();
            });
        }

        /**
         * 2) USERS: directorate_id
         */
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'directorate_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('directorate_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('corsec_directorates')
                    ->nullOnDelete();
            });
        }

        /**
         * 3) LETTERS
         * incoming_letters: target_branch_id -> target_directorate_id
         */
        if (Schema::hasTable('corsec_incoming_letters') && !Schema::hasColumn('corsec_incoming_letters', 'target_directorate_id')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                $table->foreignId('target_directorate_id')
                    ->nullable()
                    ->constrained('corsec_directorates')
                    ->nullOnDelete();
            });
        }

        // Drop kolom branch lama (PG safe, walau kolom gak ada)
        if (Schema::hasTable('corsec_incoming_letters')) {
            DB::statement('ALTER TABLE corsec_incoming_letters DROP COLUMN IF EXISTS target_branch_id CASCADE');
            DB::statement('ALTER TABLE corsec_incoming_letters DROP COLUMN IF EXISTS to_branch_id CASCADE'); // jaga-jaga kalau ada varian
        }

        /**
         * incoming_letter_routes: (di DB lo ada to_branch_id, from_branch_id mungkin ga ada)
         * -> to_directorate_id
         */
        if (Schema::hasTable('corsec_incoming_letter_routes') && !Schema::hasColumn('corsec_incoming_letter_routes', 'to_directorate_id')) {
            Schema::table('corsec_incoming_letter_routes', function (Blueprint $table) {
                $table->foreignId('to_directorate_id')
                    ->nullable()
                    ->constrained('corsec_directorates')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('corsec_incoming_letter_routes')) {
            DB::statement('ALTER TABLE corsec_incoming_letter_routes DROP COLUMN IF EXISTS to_branch_id CASCADE');
            DB::statement('ALTER TABLE corsec_incoming_letter_routes DROP COLUMN IF EXISTS from_branch_id CASCADE'); // aman walau gak ada
        }

        /**
         * outgoing_letters: requester_branch_id -> requester_directorate_id
         */
        if (Schema::hasTable('corsec_outgoing_letters') && !Schema::hasColumn('corsec_outgoing_letters', 'requester_directorate_id')) {
            Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
                $table->foreignId('requester_directorate_id')
                    ->nullable()
                    ->constrained('corsec_directorates')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('corsec_outgoing_letters')) {
            DB::statement('ALTER TABLE corsec_outgoing_letters DROP COLUMN IF EXISTS requester_branch_id CASCADE');
            DB::statement('ALTER TABLE corsec_outgoing_letters DROP COLUMN IF EXISTS requesting_branch_id CASCADE'); // jaga-jaga beda nama
        }

        /**
         * 4) MEETINGS
         * meeting_agendas: owner_branch_id -> owner_directorate_id
         */
        if (Schema::hasTable('corsec_meeting_agendas') && !Schema::hasColumn('corsec_meeting_agendas', 'owner_directorate_id')) {
            Schema::table('corsec_meeting_agendas', function (Blueprint $table) {
                $table->foreignId('owner_directorate_id')
                    ->nullable()
                    ->constrained('corsec_directorates')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('corsec_meeting_agendas')) {
            DB::statement('ALTER TABLE corsec_meeting_agendas DROP COLUMN IF EXISTS owner_branch_id CASCADE');
        }

        /**
         * meeting_decisions: owner_branch_id -> owner_directorate_id
         */
        if (Schema::hasTable('corsec_meeting_decisions') && !Schema::hasColumn('corsec_meeting_decisions', 'owner_directorate_id')) {
            Schema::table('corsec_meeting_decisions', function (Blueprint $table) {
                $table->foreignId('owner_directorate_id')
                    ->nullable()
                    ->constrained('corsec_directorates')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('corsec_meeting_decisions')) {
            DB::statement('ALTER TABLE corsec_meeting_decisions DROP COLUMN IF EXISTS owner_branch_id CASCADE');
        }

        /**
         * 5) WORK PROGRAMS
         * corsec_work_programs.branch_id -> directorate_id
         */
        if (Schema::hasTable('corsec_work_programs') && !Schema::hasColumn('corsec_work_programs', 'directorate_id')) {
            Schema::table('corsec_work_programs', function (Blueprint $table) {
                $table->foreignId('directorate_id')
                    ->nullable()
                    ->constrained('corsec_directorates')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('corsec_work_programs')) {
            // DROP unique lama (PG: ini CONSTRAINT, bukan index)
            DB::statement('ALTER TABLE corsec_work_programs DROP CONSTRAINT IF EXISTS corsec_work_programs_branch_year_title_unique');

            // drop branch_id lama
            DB::statement('ALTER TABLE corsec_work_programs DROP COLUMN IF EXISTS branch_id CASCADE');

            // pastiin constraint baru ga dobel
            DB::statement('ALTER TABLE corsec_work_programs DROP CONSTRAINT IF EXISTS corsec_work_programs_directorate_year_title_unique');

            // bikin unique baru
            DB::statement('ALTER TABLE corsec_work_programs
                   ADD CONSTRAINT corsec_work_programs_directorate_year_title_unique
                   UNIQUE (directorate_id, year, title)');
        }
    }

    public function down(): void
    {
        // NOTE: down-nya minimal & aman (ga balikin branch columns)

        if (Schema::hasTable('corsec_work_programs')) {
            DB::statement('ALTER TABLE corsec_work_programs DROP CONSTRAINT IF EXISTS corsec_work_programs_directorate_year_title_unique');
            DB::statement('ALTER TABLE corsec_work_programs DROP COLUMN IF EXISTS directorate_id CASCADE');
        }

        if (Schema::hasTable('corsec_meeting_decisions')) {
            if (Schema::hasColumn('corsec_meeting_decisions', 'owner_directorate_id')) {
                DB::statement('ALTER TABLE corsec_meeting_decisions DROP COLUMN IF EXISTS owner_directorate_id CASCADE');
            }
        }

        if (Schema::hasTable('corsec_meeting_agendas')) {
            if (Schema::hasColumn('corsec_meeting_agendas', 'owner_directorate_id')) {
                DB::statement('ALTER TABLE corsec_meeting_agendas DROP COLUMN IF EXISTS owner_directorate_id CASCADE');
            }
        }

        if (Schema::hasTable('corsec_outgoing_letters')) {
            if (Schema::hasColumn('corsec_outgoing_letters', 'requester_directorate_id')) {
                DB::statement('ALTER TABLE corsec_outgoing_letters DROP COLUMN IF EXISTS requester_directorate_id CASCADE');
            }
        }

        if (Schema::hasTable('corsec_incoming_letter_routes')) {
            if (Schema::hasColumn('corsec_incoming_letter_routes', 'to_directorate_id')) {
                DB::statement('ALTER TABLE corsec_incoming_letter_routes DROP COLUMN IF EXISTS to_directorate_id CASCADE');
            }
        }

        if (Schema::hasTable('corsec_incoming_letters')) {
            if (Schema::hasColumn('corsec_incoming_letters', 'target_directorate_id')) {
                DB::statement('ALTER TABLE corsec_incoming_letters DROP COLUMN IF EXISTS target_directorate_id CASCADE');
            }
        }

        if (Schema::hasTable('users')) {
            if (Schema::hasColumn('users', 'directorate_id')) {
                DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS directorate_id CASCADE');
            }
        }

        if (Schema::hasTable('corsec_directorates')) {
            Schema::dropIfExists('corsec_directorates');
        }
    }
};
