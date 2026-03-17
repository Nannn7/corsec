<?php

namespace Modules\Corsec\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Services\MeetingWorkflowService;

class SyncDirectorateMeetingScheduleCommand extends Command
{
    protected $signature = 'corsec:meetings:sync-directorate-schedule {--dry-run : Preview reminders and auto-close targets without saving changes}';

    protected $description = 'Send H-1 reminders for directorate meetings and auto-close meetings without responses on day H.';

    public function handle(MeetingWorkflowService $workflow): int
    {
        if (!Schema::hasTable('corsec_meetings') || !Schema::hasColumn('corsec_meetings', 'directorate_reminder_sent_at')) {
            $this->warn('Skipped. Kolom corsec_meetings.directorate_reminder_sent_at belum tersedia. Jalankan migration terlebih dahulu.');

            return self::SUCCESS;
        }

        $now = now();

        $reminderMeetings = Meeting::query()
            ->whereIn('meeting_type', Meeting::direktoratTypeCodes())
            ->whereIn('status', [
                Meeting::STATUS_JADWAL_TERKIRIM,
                Meeting::STATUS_PENDING_DIREKTORAT,
            ])
            ->where(function ($query) {
                $query->whereNull('directorate_response_status')
                    ->orWhere('directorate_response_status', Meeting::RESPONSE_PENDING);
            })
            ->whereNull('directorate_reminder_sent_at')
            ->whereDate('meeting_at', $now->copy()->addDay()->toDateString())
            ->orderBy('meeting_at')
            ->get();

        $closeMeetings = Meeting::query()
            ->whereIn('meeting_type', Meeting::direktoratTypeCodes())
            ->whereIn('status', [
                Meeting::STATUS_JADWAL_TERKIRIM,
                Meeting::STATUS_PENDING_DIREKTORAT,
            ])
            ->where(function ($query) {
                $query->whereNull('directorate_response_status')
                    ->orWhere('directorate_response_status', Meeting::RESPONSE_PENDING);
            })
            ->whereDate('meeting_at', '<=', $now->toDateString())
            ->orderBy('meeting_at')
            ->get();

        $this->info(sprintf(
            'Directorate meeting sync at %s. Reminders due: %d. Auto-close due: %d.',
            $now->format('Y-m-d H:i:s'),
            $reminderMeetings->count(),
            $closeMeetings->count()
        ));

        if ($this->option('dry-run')) {
            $reminderMeetings->each(function (Meeting $meeting): void {
                $this->line(sprintf(
                    '[DRY-RUN][REMINDER] Meeting #%d - %s (%s)',
                    $meeting->id,
                    (string) $meeting->title,
                    optional($meeting->meeting_at)->format('Y-m-d H:i')
                ));
            });

            $closeMeetings->each(function (Meeting $meeting): void {
                $this->line(sprintf(
                    '[DRY-RUN][AUTO-CLOSE] Meeting #%d - %s (%s)',
                    $meeting->id,
                    (string) $meeting->title,
                    optional($meeting->meeting_at)->format('Y-m-d H:i')
                ));
            });

            return self::SUCCESS;
        }

        $reminderSent = 0;
        foreach ($reminderMeetings as $meeting) {
            if ($workflow->sendDirectorateResponseReminder($meeting, $now->copy())) {
                $reminderSent++;
            }
        }

        $closedCount = 0;
        foreach ($closeMeetings as $meeting) {
            if ($workflow->autoCloseMissingDirectorateResponse($meeting, $now->copy())) {
                $closedCount++;
            }
        }

        $this->info("Reminders sent: {$reminderSent}. Meetings auto-closed: {$closedCount}.");

        return self::SUCCESS;
    }
}
