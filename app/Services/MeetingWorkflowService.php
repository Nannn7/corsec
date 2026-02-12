<?php

namespace Modules\Corsec\Services;

use Illuminate\Support\Facades\DB;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Models\Directorate;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\MeetingDecision;
use Modules\Corsec\Notifications\CorsecFlowNotification;
use Modules\Usermanagement\Models\User;

class MeetingWorkflowService
{
    public function submitPlan(Meeting $meeting, User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($meeting, $actor, $note) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [Meeting::STATUS_DRAFT, Meeting::STATUS_RETURNED_BY_CORSEC]);
            $this->ensureNoPendingApproval($meeting);

            $meeting->update([
                'status' => Meeting::STATUS_WAITING_CORSEC_APPROVAL,
                'authorized_status' => 'pending',
                'authorized_at' => null,
                'authorized_by' => null,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => Meeting::class,
                'approvable_id' => $meeting->id,
                'status' => 'pending',
                'note' => $this->buildApprovalNote('Menunggu approval EO Corp Affair + Kepala Corsec', $note),
            ]);

            $approvalUserIds = $this->getCorpSecretaryApprovalUserIds();
            $this->notifyUsers(
                $approvalUserIds,
                'meeting_corsec_approval',
                $this->meetingNotificationData(
                    $meeting,
                    $actor,
                    'Approval Jadwal Rapat',
                    'Jadwal rapat menunggu approval EO Corp Affair + Kepala Corsec.'
                )
            );
        });
    }

    public function handleCorsecApproval(Meeting $meeting, User $actor, string $action, ?string $note = null): void
    {
        DB::transaction(function () use ($meeting, $actor, $action, $note) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [Meeting::STATUS_WAITING_CORSEC_APPROVAL]);
            $this->ensureCorsecCheckerActor($actor);

            $pending = $this->latestPendingApproval($meeting);
            if (!$pending) {
                abort(422, 'Tidak ada approval meeting yang pending.');
            }

            if ($action === 'approve') {
                $pending->update([
                    'status' => 'approved',
                    'note' => $this->buildApprovalNote('EO Corp Affair + Kepala Corsec Approved', $note),
                    'acted_by' => $actor->id,
                    'acted_at' => now(),
                ]);

                $meeting->update([
                    'status' => Meeting::STATUS_JADWAL_TERKIRIM,
                    'schedule_sent_at' => $meeting->schedule_sent_at ?: now(),
                    'authorized_status' => 'authorized',
                    'authorized_at' => now(),
                    'authorized_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                $this->notifyUsers(
                    [$meeting->created_by],
                    'meeting_corsec_action',
                    $this->meetingNotificationData(
                        $meeting,
                        $actor,
                        'Approval Corsec',
                        'Rencana rapat disetujui EO Corp Affair + Kepala Corsec.'
                    )
                );

                return;
            }

            $pending->update([
                'status' => 'returned',
                'note' => $this->buildApprovalNote('EO Corp Affair + Kepala Corsec Returned', $note),
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            $meeting->update([
                'status' => Meeting::STATUS_RETURNED_BY_CORSEC,
                'authorized_status' => 'returned',
                'authorized_at' => null,
                'authorized_by' => null,
                'updated_by' => $actor->id,
            ]);

            $this->addComment($meeting, $actor, '[RETURN EO + KEPALA CORSEC]', $note);
            $this->notifyUsers(
                [$meeting->created_by],
                'meeting_corsec_action',
                $this->meetingNotificationData(
                    $meeting,
                    $actor,
                    'Approval Corsec',
                    'Rencana rapat dikembalikan EO Corp Affair + Kepala Corsec.'
                )
            );
        });
    }

    public function markPendingDirectorate(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [Meeting::STATUS_JADWAL_TERKIRIM, Meeting::STATUS_RETURNED_BY_DIREKTORAT]);

            $meeting->update([
                'status' => Meeting::STATUS_PENDING_DIREKTORAT,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function submitDirectoratePreparation(Meeting $meeting, User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($meeting, $actor, $note) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [
                Meeting::STATUS_JADWAL_TERKIRIM,
                Meeting::STATUS_PENDING_DIREKTORAT,
                Meeting::STATUS_RETURNED_BY_DIREKTORAT,
            ]);
            $this->ensureNoPendingApproval($meeting);

            $meeting->update([
                'status' => Meeting::STATUS_WAITING_DIREKTORAT_APPROVAL,
                'authorized_status' => 'pending',
                'authorized_at' => null,
                'authorized_by' => null,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => Meeting::class,
                'approvable_id' => $meeting->id,
                'status' => 'pending',
                'note' => $this->buildApprovalNote('Menunggu approval EO + DD Direktorat', $note),
            ]);

            $approvalUserIds = $this->getMeetingDirectorateApprovalUserIds($meeting, $actor);
            $this->notifyUsers(
                $approvalUserIds,
                'meeting_directorate_approval',
                $this->meetingNotificationData(
                    $meeting,
                    $actor,
                    'Approval Direktorat',
                    'Persiapan rapat menunggu approval EO + DD Direktorat.'
                )
            );
        });
    }

    public function handleDirectorateApproval(Meeting $meeting, User $actor, string $action, ?string $note = null): void
    {
        DB::transaction(function () use ($meeting, $actor, $action, $note) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [Meeting::STATUS_WAITING_DIREKTORAT_APPROVAL]);

            $pending = $this->latestPendingApproval($meeting);
            if (!$pending) {
                abort(422, 'Tidak ada approval meeting yang pending.');
            }

            if ($action === 'approve') {
                $pending->update([
                    'status' => 'approved',
                    'note' => $this->buildApprovalNote('EO + DD Direktorat Approved', $note),
                    'acted_by' => $actor->id,
                    'acted_at' => now(),
                ]);

                $meeting->update([
                    'status' => Meeting::STATUS_DATA_TERKIRIM,
                    'authorized_status' => 'authorized',
                    'authorized_at' => now(),
                    'authorized_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                $this->notifyUsers(
                    [$meeting->created_by],
                    'meeting_directorate_action',
                    $this->meetingNotificationData(
                        $meeting,
                        $actor,
                        'Approval Direktorat',
                        'Persiapan rapat disetujui EO + DD Direktorat.'
                    )
                );

                return;
            }

            $pending->update([
                'status' => 'returned',
                'note' => $this->buildApprovalNote('EO + DD Direktorat Returned', $note),
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            $meeting->update([
                'status' => Meeting::STATUS_RETURNED_BY_DIREKTORAT,
                'authorized_status' => 'returned',
                'authorized_at' => null,
                'authorized_by' => null,
                'updated_by' => $actor->id,
            ]);

            $this->addComment($meeting, $actor, '[RETURN EO + DD DIREKTORAT]', $note);
            $this->notifyUsers(
                [$meeting->created_by],
                'meeting_directorate_action',
                $this->meetingNotificationData(
                    $meeting,
                    $actor,
                    'Approval Direktorat',
                    'Persiapan rapat dikembalikan EO + DD Direktorat.'
                )
            );
        });
    }

    public function startMinutes(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [Meeting::STATUS_DATA_TERKIRIM, Meeting::STATUS_PROSES_PEMBUATAN_NOTULEN]);

            $meeting->update([
                'status' => Meeting::STATUS_PROSES_PEMBUATAN_NOTULEN,
                'conducted_at' => $meeting->conducted_at ?: now(),
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function circulateMinutes(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [Meeting::STATUS_PROSES_PEMBUATAN_NOTULEN]);

            $meeting->update([
                'status' => Meeting::STATUS_PROSES_SIRKULASI_TANDATANGAN,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function finalizeMinutes(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [
                Meeting::STATUS_PROSES_PEMBUATAN_NOTULEN,
                Meeting::STATUS_PROSES_SIRKULASI_TANDATANGAN,
            ]);

            $meeting->update([
                'status' => Meeting::STATUS_NOTULEN_FINAL,
                'updated_by' => $actor->id,
            ]);

            $audienceUserIds = $this->getMeetingAudienceUserIds($meeting);
            $this->notifyUsers(
                $audienceUserIds,
                'meeting_minutes_final',
                $this->meetingNotificationData(
                    $meeting,
                    $actor,
                    'Notulen Final',
                    'Notulen final rapat sudah tersedia.'
                )
            );
        });
    }

    public function startFollowup(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [
                Meeting::STATUS_NOTULEN_FINAL,
                Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT,
            ]);

            $meeting->update([
                'status' => Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT,
                'finished_at' => null,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function completeFollowup(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [
                Meeting::STATUS_NOTULEN_FINAL,
                Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT,
                Meeting::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT,
            ]);

            $meeting->loadMissing('decisions');
            $hasPendingDecision = $meeting->decisions->contains(function (MeetingDecision $decision) {
                return !in_array((string) $decision->status, [
                    MeetingDecision::STATUS_DONE,
                    MeetingDecision::STATUS_DROPPED,
                ], true);
            });

            if ($hasPendingDecision) {
                abort(422, 'Masih ada tindaklanjut rapat yang belum selesai.');
            }

            $meeting->update([
                'status' => Meeting::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT,
                'finished_at' => now(),
                'updated_by' => $actor->id,
            ]);

            $audienceUserIds = $this->getMeetingAudienceUserIds($meeting);
            $this->notifyUsers(
                $audienceUserIds,
                'meeting_followup_done',
                $this->meetingNotificationData(
                    $meeting,
                    $actor,
                    'Tindaklanjut Rapat Selesai',
                    'Seluruh tindaklanjut hasil rapat telah dinyatakan selesai.'
                )
            );
        });
    }

    public function syncFollowupStatusFromDecisions(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor) {
            $meeting = $this->lockMeeting($meeting);
            $meeting->loadMissing('decisions');

            if ($meeting->decisions->isEmpty()) {
                return;
            }

            $hasPending = $meeting->decisions->contains(function (MeetingDecision $decision) {
                return !in_array((string) $decision->status, [
                    MeetingDecision::STATUS_DONE,
                    MeetingDecision::STATUS_DROPPED,
                ], true);
            });

            if ($hasPending) {
                $meeting->update([
                    'status' => Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT,
                    'finished_at' => null,
                    'updated_by' => $actor->id,
                ]);
                return;
            }

            $meeting->update([
                'status' => Meeting::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT,
                'finished_at' => now(),
                'updated_by' => $actor->id,
            ]);
        });
    }

    private function lockMeeting(Meeting $meeting): Meeting
    {
        return Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
    }

    private function latestPendingApproval(Meeting $meeting): ?Approval
    {
        return Approval::query()
            ->where('approvable_type', Meeting::class)
            ->where('approvable_id', $meeting->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    private function ensureNoPendingApproval(Meeting $meeting): void
    {
        if ($this->latestPendingApproval($meeting)) {
            abort(422, 'Masih ada approval meeting yang belum diproses.');
        }
    }

    private function assertStatus(Meeting $meeting, array $allowedStatuses): void
    {
        if (!in_array((string) $meeting->status, $allowedStatuses, true)) {
            abort(422, 'Status meeting tidak sesuai untuk aksi ini.');
        }
    }

    private function getCorpSecretaryApprovalUserIds()
    {
        $directorateId = $this->getCorpSecretaryDirectorateId();
        if (!$directorateId) {
            return collect();
        }

        return User::query()
            ->where('directorate_id', $directorateId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'checker');
            })
            ->pluck('id');
    }

    private function ensureCorsecCheckerActor(User $actor): void
    {
        if ($actor->hasRole('administrator')) {
            return;
        }

        $directorateId = $this->getCorpSecretaryDirectorateId();
        if (!$directorateId) {
            abort(403, 'Direktorat Corporate Secretary belum terkonfigurasi.');
        }

        $isChecker = $actor->hasRole('checker');
        $isCorpSecretary = (int) ($actor->directorate_id ?? 0) === (int) $directorateId;

        if (!$isChecker || !$isCorpSecretary) {
            abort(403, 'Approval EO/Kepala Corsec hanya untuk role checker di direktorat Corporate Secretary.');
        }
    }

    private function getCorpSecretaryDirectorateId(): ?int
    {
        $corpCode = (string) config('corsec.eo_corp_affair_directorate_code', '');
        if ($corpCode !== '') {
            $directorateId = Directorate::query()
                ->where('code', $corpCode)
                ->value('id');
            if ($directorateId) {
                return (int) $directorateId;
            }
        }

        $directorateId = Directorate::query()
            ->where('name', 'ilike', '%corporate secretary%')
            ->value('id');

        return $directorateId ? (int) $directorateId : null;
    }

    private function getMeetingDirectorateApprovalUserIds(Meeting $meeting, User $actor)
    {
        $directorateIds = $this->getTargetDirectorateIds($meeting, (int) ($actor->directorate_id ?? 0));
        if ($directorateIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('directorate_id', $directorateIds->all())
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['checker', 'approver']);
            })
            ->pluck('id');
    }

    private function getMeetingAudienceUserIds(Meeting $meeting)
    {
        $directorateIds = $this->getTargetDirectorateIds($meeting);

        $audience = collect([$meeting->created_by])->filter();
        if ($directorateIds->isNotEmpty()) {
            $directorateUsers = User::query()
                ->whereIn('directorate_id', $directorateIds->all())
                ->pluck('id');
            $audience = $audience->merge($directorateUsers);
        }

        return $audience->filter()->unique()->values();
    }

    private function getTargetDirectorateIds(Meeting $meeting, int $fallbackDirectorateId = 0)
    {
        $meeting->loadMissing('participants', 'agendas');

        $participantDirectorateIds = $meeting->participants
            ->pluck('directorate_id')
            ->filter();

        $agendaDirectorateIds = $meeting->agendas
            ->pluck('owner_directorate_id')
            ->filter();

        $directorateIds = $participantDirectorateIds
            ->merge($agendaDirectorateIds)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($directorateIds->isEmpty() && $fallbackDirectorateId > 0) {
            $directorateIds = collect([$fallbackDirectorateId]);
        }

        return $directorateIds;
    }

    private function meetingNotificationData(Meeting $meeting, User $actor, string $title, string $message, array $extra = []): array
    {
        return array_merge([
            'title' => $title,
            'message' => $message,
            'meeting_id' => $meeting->id,
            'meeting_uuid' => $meeting->uuid,
            'meeting_title' => $meeting->title,
            'meeting_type' => $meeting->meeting_type,
            'meeting_at' => optional($meeting->meeting_at)->toDateTimeString(),
            'status' => $meeting->status,
            'created_by' => [
                'id' => $actor->id,
                'name' => $actor->name,
            ],
        ], $extra);
    }

    private function notifyUsers(iterable $userIds, string $type, array $data): void
    {
        CorsecFlowNotification::insertForUsers($userIds, $type, $data);
    }

    private function buildApprovalNote(string $label, ?string $note): string
    {
        $note = trim((string) $note);
        return $note !== '' ? $label . ' - ' . $note : $label;
    }

    private function addComment(Meeting $meeting, User $actor, string $label, ?string $note): void
    {
        $note = trim((string) $note);
        if ($note === '') {
            return;
        }

        Comment::create([
            'commentable_type' => Meeting::class,
            'commentable_id' => $meeting->id,
            'body' => $label . ' ' . $note,
            'created_by' => $actor->id,
        ]);
    }
}
