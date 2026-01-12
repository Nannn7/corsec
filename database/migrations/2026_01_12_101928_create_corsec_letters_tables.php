<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('corsec_incoming_letters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('external_letter_no')->nullable()->index();
            $table->string('subject')->index();
            $table->string('sender')->nullable();
            $table->date('received_date')->nullable();

            // direktorat/unit -> branches
            $table->foreignId('target_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->string('priority')->nullable()->index();
            $table->date('target_date')->nullable()->index();

            $table->string('status')->default('draft')->index();
            $table->text('description')->nullable();

            // authorized
            $table->timestamp('authorized_at')->nullable();
            $table->string('authorized_status')->nullable()->index(); // STRING (sesuai lo)
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();

            // audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('corsec_incoming_letter_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_letter_id')->constrained('corsec_incoming_letters')->cascadeOnDelete();

            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('note')->nullable();
            $table->timestamp('sent_at')->nullable()->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

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

        Schema::create('corsec_outgoing_letters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('subject')->index();
            $table->foreignId('requesting_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->foreignId('draft_attachment_id')->nullable()->constrained('corsec_attachments')->nullOnDelete();
            $table->foreignId('final_attachment_id')->nullable()->constrained('corsec_attachments')->nullOnDelete();

            $table->foreignId('letter_number_id')->nullable()->constrained('corsec_letter_numbers')->nullOnDelete();

            $table->boolean('need_compliance_review')->default(false);
            $table->string('status')->default('draft')->index();

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

        Schema::create('corsec_outgoing_letter_number_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outgoing_letter_id')->constrained('corsec_outgoing_letters')->cascadeOnDelete();

            $table->timestamp('requested_at')->nullable()->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corsec_outgoing_letter_number_requests');
        Schema::dropIfExists('corsec_outgoing_letters');
        Schema::dropIfExists('corsec_letter_numbers');
        Schema::dropIfExists('corsec_incoming_letter_routes');
        Schema::dropIfExists('corsec_incoming_letters');
    }
};
