<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('corsec_outgoing_letters')) {
            Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
                if (!Schema::hasColumn('corsec_outgoing_letters', 'registration_no')) {
                    $table->string('registration_no')->nullable()->unique()->after('uuid');
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'order_date')) {
                    $table->date('order_date')->nullable()->after('registration_no');
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'recipient_id')) {
                    $table->foreignId('recipient_id')->nullable()->after('order_date')
                        ->constrained('corsec_senders')->nullOnDelete();
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'recipient_other')) {
                    $table->string('recipient_other')->nullable()->after('recipient_id');
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'summary')) {
                    $table->text('summary')->nullable()->after('subject');
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'perihal_type')) {
                    $table->string('perihal_type')->nullable()->after('summary');
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'perihal_incoming_letter_id')) {
                    $table->foreignId('perihal_incoming_letter_id')->nullable()->after('perihal_type')
                        ->constrained('corsec_incoming_letters')->nullOnDelete();
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'perihal_text')) {
                    $table->string('perihal_text')->nullable()->after('perihal_incoming_letter_id');
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'note')) {
                    $table->text('note')->nullable()->after('perihal_text');
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'compliance_attachment_id')) {
                    $table->foreignId('compliance_attachment_id')->nullable()->after('draft_attachment_id')
                        ->constrained('corsec_attachments')->nullOnDelete();
                }
                if (!Schema::hasColumn('corsec_outgoing_letters', 'letter_no')) {
                    $table->string('letter_no')->nullable()->after('letter_number_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corsec_outgoing_letters')) {
            Schema::table('corsec_outgoing_letters', function (Blueprint $table) {
                if (Schema::hasColumn('corsec_outgoing_letters', 'letter_no')) {
                    $table->dropColumn('letter_no');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'compliance_attachment_id')) {
                    $table->dropForeign(['compliance_attachment_id']);
                    $table->dropColumn('compliance_attachment_id');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'note')) {
                    $table->dropColumn('note');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'perihal_text')) {
                    $table->dropColumn('perihal_text');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'perihal_incoming_letter_id')) {
                    $table->dropForeign(['perihal_incoming_letter_id']);
                    $table->dropColumn('perihal_incoming_letter_id');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'perihal_type')) {
                    $table->dropColumn('perihal_type');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'summary')) {
                    $table->dropColumn('summary');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'recipient_other')) {
                    $table->dropColumn('recipient_other');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'recipient_id')) {
                    $table->dropForeign(['recipient_id']);
                    $table->dropColumn('recipient_id');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'order_date')) {
                    $table->dropColumn('order_date');
                }
                if (Schema::hasColumn('corsec_outgoing_letters', 'registration_no')) {
                    $table->dropUnique('corsec_outgoing_letters_registration_no_unique');
                    $table->dropColumn('registration_no');
                }
            });
        }
    }
};
