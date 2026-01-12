<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('corsec_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('file_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('hash')->nullable()->index();

            // audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('corsec_attachables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained('corsec_attachments')->cascadeOnDelete();

            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');

            // draft/final/evidence/material/minutes/underlying/dll
            $table->string('category')->nullable()->index();
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id'], 'corsec_attachables_morph_idx');
            $table->unique(['attachment_id', 'attachable_type', 'attachable_id', 'category'], 'corsec_attachables_unique');
        });

        Schema::create('corsec_comments', function (Blueprint $table) {
            $table->id();

            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id');

            $table->text('body');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id'], 'corsec_comments_morph_idx');
        });

        Schema::create('corsec_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // incoming_letter/outgoing_letter/meeting/work_program
            $table->string('name');
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('corsec_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('corsec_workflows')->cascadeOnDelete();

            $table->unsignedInteger('step_order');
            $table->string('name');

            // spatie roles
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();

            $table->boolean('can_return')->default(true);
            $table->unsignedInteger('sla_days')->nullable();

            $table->timestamps();
            $table->unique(['workflow_id', 'step_order'], 'corsec_workflow_steps_unique');
        });

        Schema::create('corsec_approvals', function (Blueprint $table) {
            $table->id();

            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');

            $table->foreignId('workflow_id')->nullable()->constrained('corsec_workflows')->nullOnDelete();
            $table->foreignId('workflow_step_id')->nullable()->constrained('corsec_workflow_steps')->nullOnDelete();

            $table->string('status')->default('pending')->index(); // pending/approved/rejected/returned
            $table->text('note')->nullable();

            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();

            $table->timestamps();
            $table->index(['approvable_type', 'approvable_id'], 'corsec_approvals_morph_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corsec_approvals');
        Schema::dropIfExists('corsec_workflow_steps');
        Schema::dropIfExists('corsec_workflows');
        Schema::dropIfExists('corsec_comments');
        Schema::dropIfExists('corsec_attachables');
        Schema::dropIfExists('corsec_attachments');
    }
};
