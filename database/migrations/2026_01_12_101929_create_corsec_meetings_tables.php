<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('corsec_meetings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('title')->index();
            $table->timestamp('meeting_at')->nullable()->index();
            $table->string('location')->nullable();
            $table->string('meeting_type')->nullable()->index();

            $table->string('status')->default('planned')->index();
            $table->text('description')->nullable();

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
        });

        Schema::create('corsec_meeting_agendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('corsec_meetings')->cascadeOnDelete();

            $table->unsignedInteger('order_no')->default(1);
            $table->string('title');
            $table->text('description')->nullable();

            $table->foreignId('owner_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->timestamps();
            $table->unique(['meeting_id', 'order_no'], 'corsec_meeting_agendas_unique');
        });

        Schema::create('corsec_meeting_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('corsec_meetings')->cascadeOnDelete();
            $table->foreignId('agenda_id')->nullable()->constrained('corsec_meeting_agendas')->nullOnDelete();

            $table->foreignId('attachment_id')->constrained('corsec_attachments')->cascadeOnDelete();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable()->index();

            // authorized (kalau material perlu approval)
            $table->timestamp('authorized_at')->nullable();
            $table->string('authorized_status')->nullable()->index(); // STRING
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('corsec_meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('corsec_meetings')->cascadeOnDelete();

            $table->text('minutes_text')->nullable();
            $table->foreignId('minutes_attachment_id')->nullable()->constrained('corsec_attachments')->nullOnDelete();

            $table->string('status')->default('draft')->index(); // draft/submitted/approved
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->unique(['meeting_id'], 'corsec_meeting_minutes_meeting_unique');
        });

        Schema::create('corsec_meeting_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('corsec_meetings')->cascadeOnDelete();

            $table->text('decision_text');
            $table->foreignId('owner_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->date('target_date')->nullable()->index();
            $table->string('status')->default('pending')->index(); // pending/in_progress/done/dropped
            $table->timestamp('closed_at')->nullable();

            // audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('corsec_decision_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_decision_id')->constrained('corsec_meeting_decisions')->cascadeOnDelete();

            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('status')->default('in_progress')->index();
            $table->text('note')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // authorized (kalau update butuh approval)
            $table->timestamp('authorized_at')->nullable();
            $table->string('authorized_status')->nullable()->index(); // STRING
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corsec_decision_updates');
        Schema::dropIfExists('corsec_meeting_decisions');
        Schema::dropIfExists('corsec_meeting_minutes');
        Schema::dropIfExists('corsec_meeting_materials');
        Schema::dropIfExists('corsec_meeting_agendas');
        Schema::dropIfExists('corsec_meetings');
    }
};
