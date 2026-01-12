<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('corsec_work_programs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->unsignedSmallInteger('year')->index();
            $table->string('title')->index();
            $table->text('description')->nullable();

            $table->string('status')->default('draft')->index(); // draft/on_approval/active/done/returned/rejected

            // authorized
            $table->timestamp('authorized_at')->nullable();
            $table->string('authorized_status')->nullable()->index(); // STRING
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();

            // audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'year', 'title'], 'corsec_work_programs_branch_year_title_unique');
        });

        Schema::create('corsec_work_program_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_program_id')->constrained('corsec_work_programs')->cascadeOnDelete();

            $table->string('title')->index();
            $table->text('description')->nullable();
            $table->date('target_date')->nullable()->index();

            $table->unsignedTinyInteger('weight')->nullable();
            $table->string('status')->default('pending')->index(); // pending/in_progress/done

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('corsec_work_program_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_program_item_id')->constrained('corsec_work_program_items')->cascadeOnDelete();

            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('status')->default('in_progress')->index();
            $table->text('note')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // authorized
            $table->timestamp('authorized_at')->nullable();
            $table->string('authorized_status')->nullable()->index(); // STRING
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corsec_work_program_updates');
        Schema::dropIfExists('corsec_work_program_items');
        Schema::dropIfExists('corsec_work_programs');
    }
};
