<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('corsec_meeting_participants')) {
            Schema::create('corsec_meeting_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_id')->constrained('corsec_meetings')->cascadeOnDelete();
                $table->foreignId('directorate_id')->constrained('corsec_directorates')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['meeting_id', 'directorate_id'], 'corsec_meeting_participants_unique');
                $table->index(['directorate_id', 'meeting_id'], 'corsec_meeting_participants_directorate_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('corsec_meeting_participants');
    }
};