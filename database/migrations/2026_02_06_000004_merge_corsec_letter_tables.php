<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_incoming_letters')) {
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_incoming_letters', 'last_routed_from_directorate_id')) {
                    $table->foreignId('last_routed_from_directorate_id')
                        ->nullable()
                        ->constrained('corsec_directorates')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('corsec_incoming_letters', 'last_routed_to_directorate_id')) {
                    $table->foreignId('last_routed_to_directorate_id')
                        ->nullable()
                        ->constrained('corsec_directorates')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('corsec_incoming_letters', 'last_routed_from_user_id')) {
                    $table->foreignId('last_routed_from_user_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('corsec_incoming_letters', 'last_routed_to_user_id')) {
                    $table->foreignId('last_routed_to_user_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('corsec_incoming_letters', 'last_routed_at')) {
                    $table->timestamp('last_routed_at')->nullable()->index('corsec_incoming_letters_last_routed_at_idx');
                }
                if (!Schema::hasColumn('corsec_incoming_letters', 'last_route_note')) {
                    $table->text('last_route_note')->nullable();
                }
            });
        }

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

        if (Schema::hasTable('corsec_outgoing_letters')) {
            Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_outgoing_letters', 'number_requested_at')) {
                    $table->timestamp('number_requested_at')->nullable()->index('corsec_outgoing_letters_number_requested_at_idx');
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'number_requested_by')) {
                    $table->foreignId('number_requested_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'number_request_note')) {
                    $table->text('number_request_note')->nullable();
                }
            });
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

        if (Schema::hasTable('corsec_letter_numbers')) {
            Schema::dropIfExists('corsec_letter_numbers');
        }

        if (Schema::hasTable('corsec_outgoing_letter_number_requests')) {
            Schema::dropIfExists('corsec_outgoing_letter_number_requests');
        }

        if (Schema::hasTable('corsec_incoming_letter_routes')) {
            Schema::dropIfExists('corsec_incoming_letter_routes');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_incoming_letters')) {
            DB::statement('ALTER TABLE corsec_incoming_letters DROP CONSTRAINT IF EXISTS corsec_incoming_letters_last_routed_from_directorate_id_foreign');
            DB::statement('ALTER TABLE corsec_incoming_letters DROP CONSTRAINT IF EXISTS corsec_incoming_letters_last_routed_to_directorate_id_foreign');
            DB::statement('ALTER TABLE corsec_incoming_letters DROP CONSTRAINT IF EXISTS corsec_incoming_letters_last_routed_from_user_id_foreign');
            DB::statement('ALTER TABLE corsec_incoming_letters DROP CONSTRAINT IF EXISTS corsec_incoming_letters_last_routed_to_user_id_foreign');
            Schema::table('corsec_incoming_letters', function (Blueprint $table) {
                if (Schema::hasColumn('corsec_incoming_letters', 'last_routed_at')) {
                    $table->dropIndex('corsec_incoming_letters_last_routed_at_idx');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'last_route_note')) {
                    $table->dropColumn('last_route_note');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'last_routed_at')) {
                    $table->dropColumn('last_routed_at');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'last_routed_to_user_id')) {
                    $table->dropColumn('last_routed_to_user_id');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'last_routed_from_user_id')) {
                    $table->dropColumn('last_routed_from_user_id');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'last_routed_to_directorate_id')) {
                    $table->dropColumn('last_routed_to_directorate_id');
                }
                if (Schema::hasColumn('corsec_incoming_letters', 'last_routed_from_directorate_id')) {
                    $table->dropColumn('last_routed_from_directorate_id');
                }
            });
        }

        if (!Schema::hasTable('corsec_incoming_letter_routes')) {
            Schema::create('corsec_incoming_letter_routes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('incoming_letter_id')->constrained('corsec_incoming_letters')->cascadeOnDelete();
                $table->foreignId('from_directorate_id')->nullable()->constrained('corsec_directorates')->nullOnDelete();
                $table->foreignId('to_directorate_id')->nullable()->constrained('corsec_directorates')->nullOnDelete();
                $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamp('sent_at')->nullable()->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('corsec_outgoing_letters')) {
            DB::statement('ALTER TABLE corsec_outgoing_letters DROP CONSTRAINT IF EXISTS corsec_outgoing_letters_number_requested_by_foreign');
            Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
                if (Schema::hasColumn('corsec_outgoing_letters', 'number_requested_at')) {
                    $table->dropIndex('corsec_outgoing_letters_number_requested_at_idx');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'number_request_note')) {
                    $table->dropColumn('number_request_note');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'number_requested_by')) {
                    $table->dropColumn('number_requested_by');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'number_requested_at')) {
                    $table->dropColumn('number_requested_at');
                }
            });
        }

        if (!Schema::hasTable('corsec_outgoing_letter_number_requests')) {
            Schema::create('corsec_outgoing_letter_number_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('outgoing_letter_id')->constrained('corsec_outgoing_letters')->cascadeOnDelete();
                $table->timestamp('requested_at')->nullable()->index();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('corsec_letter_numbers')) {
            Schema::create('corsec_letter_numbers', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year')->index();
                $table->unsignedInteger('sequence')->index();
                $table->string('code')->nullable()->index();
                $table->string('number')->unique();
                $table->timestamp('issued_at')->nullable();
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_used')->default(false)->index();
                $table->timestamp('used_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['year', 'sequence', 'code'], 'corsec_letter_numbers_year_seq_code_unique');
            });
        }

        if (Schema::hasTable('corsec_outgoing_letters') && !Schema::hasColumn('corsec_outgoing_letters', 'letter_number_id')) {
            Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
                $table->foreignId('letter_number_id')->nullable()->constrained('corsec_letter_numbers')->nullOnDelete();
            });
        }
    }
};
