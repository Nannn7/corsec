<?php

namespace Modules\Corsec\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Models\Directorate;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\MeetingDecision;
use Modules\Corsec\Notifications\CorsecFlowNotification;
use Modules\Corsec\Support\DirectorateApprovalFlow;
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
                'directorate_reminder_sent_at' => null,
                'directorate_response_status' => $meeting->isDirektoratType() ? Meeting::RESPONSE_PENDING : null,
                'directorate_response_note' => null,
                'directorate_responded_at' => null,
                'directorate_responded_by' => null,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => Meeting::class,
                'approvable_id' => $meeting->id,
                'status' => 'pending',
                'note' => $this->buildApprovalNote('Menunggu Corporate Secretary', $note),
            ]);

            $approvalUserIds = $this->getCorpSecretaryApprovalUserIds();
            $this->notifyUsers(
                $approvalUserIds,
                'meeting_corsec_approval',
                $this->meetingNotificationData(
                    $meeting,
                    $actor,
                    'Approval Jadwal Rapat',
                    'Jadwal rapat menunggu Corporate Secretary.'
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
                    'note' => $this->buildApprovalNote('Corporate Secretary Approved', $note),
                    'acted_by' => $actor->id,
                    'acted_at' => now(),
                ]);

                $meeting->update([
                    'status' => Meeting::STATUS_JADWAL_TERKIRIM,
                    'schedule_sent_at' => $meeting->schedule_sent_at ?: now(),
                    'directorate_reminder_sent_at' => null,
                    'authorized_status' => 'authorized',
                    'authorized_at' => now(),
                    'authorized_by' => $actor->id,
                    'directorate_response_status' => $meeting->isDirektoratType()
                        ? Meeting::RESPONSE_PENDING
                        : $meeting->directorate_response_status,
                    'directorate_response_note' => null,
                    'directorate_responded_at' => null,
                    'directorate_responded_by' => null,
                    'updated_by' => $actor->id,
                ]);

                $audienceUserIds = collect([$meeting->created_by])
                    ->merge($this->getMeetingAssignedPicUserIds($meeting))
                    ->filter()
                    ->unique()
                    ->values();

                $directorateResponseDeadlineLabel = $meeting->directorateResponseDeadlineLabel();
                $corsecApprovalMessage = $meeting->isDirektoratType()
                    ? 'Rencana rapat disetujui Corporate Secretary. PIC direktorat diminta memberi tanggapan jadwal.'
                    : 'Rencana rapat disetujui Corporate Secretary.';
                if ($meeting->isDirektoratType()) {
                    $corsecApprovalMessage .= $directorateResponseDeadlineLabel
                        ? " Target tanggapan H-1 ({$directorateResponseDeadlineLabel})."
                        : ' Target tanggapan H-1 dari tanggal meeting.';
                }

                $this->notifyUsers(
                    $audienceUserIds,
                    'meeting_corsec_action',
                    $this->meetingNotificationData(
                        $meeting,
                        $actor,
                        'Approval Corsec',
                        $corsecApprovalMessage,
                        $this->directorateResponseDeadlineNotificationExtra($meeting)
                    )
                );

                return;
            }

            $pending->update([
                'status' => 'returned',
                'note' => $this->buildApprovalNote('Corporate Secretary Returned', $note),
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

            $this->addComment($meeting, $actor, '[RETURN CORPORATE SECRETARY]', $note);
            $this->notifyUsers(
                [$meeting->created_by],
                'meeting_corsec_action',
                $this->meetingNotificationData(
                        $meeting,
                        $actor,
                        'Approval Corsec',
                        'Rencana rapat dikembalikan Corporate Secretary.'
                    )
                );
        });
    }

    public function sendDirectorateResponseReminder(Meeting $meeting, ?Carbon $referenceTime = null): bool
    {
        $referenceTime = $referenceTime ?: now();

        return DB::transaction(function () use ($meeting, $referenceTime) {
            $meeting = $this->lockMeeting($meeting);

            if (!$meeting->isDirectorateResponseReminderWindow($referenceTime) || $meeting->directorate_reminder_sent_at) {
                return false;
            }

            $recipientIds = collect([$meeting->created_by])
                ->merge($this->getMeetingAssignedPicUserIds($meeting))
                ->filter()
                ->unique()
                ->values();

            $deadlineLabel = $meeting->directorateResponseDeadlineLabel() ?? 'H-1 dari jadwal rapat';
            $this->notifyUsers(
                $recipientIds,
                'meeting_directorate_response_reminder',
                $this->systemMeetingNotificationData(
                    $meeting,
                    'Reminder Tanggapan Jadwal Direktorat',
                    "H-1 jadwal rapat direktorat. Mohon beri tanggapan on schedule, cancel, atau reschedule sebelum {$deadlineLabel}.",
                    $this->directorateResponseDeadlineNotificationExtra($meeting)
                )
            );

            $meeting->update([
                'directorate_reminder_sent_at' => $referenceTime,
            ]);

            return true;
        });
    }

    public function autoCloseMissingDirectorateResponse(Meeting $meeting, ?Carbon $referenceTime = null): bool
    {
        $referenceTime = $referenceTime ?: now();

        return DB::transaction(function () use ($meeting, $referenceTime) {
            $meeting = $this->lockMeeting($meeting);

            if (!$meeting->shouldAutoCloseForMissingDirectorateResponse($referenceTime)) {
                return false;
            }

            $meeting->update([
                'status' => Meeting::STATUS_CLOSED_NOT_CONDUCTED,
                'directorate_response_status' => Meeting::RESPONSE_NO_RESPONSE,
                'directorate_response_note' => 'Meeting otomatis ditutup karena tidak ada tanggapan dari direktorat sampai hari H.',
                'directorate_reminder_sent_at' => $meeting->directorate_reminder_sent_at ?: $referenceTime,
            ]);

            return true;
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

    public function respondDirectorateSchedule(Meeting $meeting, User $actor, string $action, ?string $note = null): void
    {
        DB::transaction(function () use ($meeting, $actor, $action, $note) {
            $meeting = $this->lockMeeting($meeting);

            if (!$meeting->isDirektoratType()) {
                abort(422, 'Tanggapan jadwal hanya berlaku untuk rapat direktorat.');
            }

            $this->assertStatus($meeting, [
                Meeting::STATUS_JADWAL_TERKIRIM,
                Meeting::STATUS_PENDING_DIREKTORAT,
                Meeting::STATUS_RETURNED_BY_DIREKTORAT,
            ]);
            if ($meeting->isDirektoratType() && !$actor->hasRole('administrator') && !$this->isActorAssignedToMeeting($meeting, $actor)) {
                abort(403, 'Tanggapan jadwal hanya untuk PIC user direktorat yang ditugaskan.');
            }
            $this->ensureActorInTargetDirectorate(
                $meeting,
                $actor,
                'Tanggapan jadwal hanya untuk PIC user/direktorat peserta rapat.'
            );

            if ($action === Meeting::RESPONSE_CANCEL) {
                $meeting->update([
                    'status' => Meeting::STATUS_CANCELLED_DIREKTORAT,
                    'directorate_response_status' => Meeting::RESPONSE_CANCEL,
                    'directorate_response_note' => $note,
                    'directorate_responded_at' => now(),
                    'directorate_responded_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                $this->addComment($meeting, $actor, '[TANGGAPAN DIREKTORAT - CANCEL]', $note);
                $this->notifyUsers(
                    [$meeting->created_by],
                    'meeting_directorate_action',
                    $this->meetingNotificationData(
                        $meeting,
                        $actor,
                        'Tanggapan Jadwal Direktorat',
                        'Rapat direktorat ditandai cancel oleh PIC direktorat.'
                    )
                );

                return;
            }

            if ($action === Meeting::RESPONSE_RESCHEDULE) {
                $meeting->update([
                    'status' => Meeting::STATUS_RETURNED_BY_DIREKTORAT,
                    'authorized_status' => 'returned',
                    'authorized_at' => null,
                    'authorized_by' => null,
                    'directorate_response_status' => Meeting::RESPONSE_RESCHEDULE,
                    'directorate_response_note' => $note,
                    'directorate_responded_at' => now(),
                    'directorate_responded_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                $this->addComment($meeting, $actor, '[TANGGAPAN DIREKTORAT - RESCHEDULE]', $note);
                $this->notifyUsers(
                    [$meeting->created_by],
                    'meeting_directorate_action',
                    $this->meetingNotificationData(
                        $meeting,
                        $actor,
                        'Tanggapan Jadwal Direktorat',
                        'PIC direktorat meminta reschedule jadwal rapat.'
                    )
                );

                return;
            }

            $meeting->update([
                'status' => Meeting::STATUS_PENDING_DIREKTORAT,
                'directorate_response_status' => Meeting::RESPONSE_ON_SCHEDULE,
                'directorate_response_note' => $note,
                'directorate_responded_at' => now(),
                'directorate_responded_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->addComment($meeting, $actor, '[TANGGAPAN DIREKTORAT - ON SCHEDULE]', $note);
        });
    }

    public function submitDirectoratePreparation(Meeting $meeting, User $actor, ?string $note = null): array
    {
        return DB::transaction(function () use ($meeting, $actor, $note) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [
                Meeting::STATUS_JADWAL_TERKIRIM,
                Meeting::STATUS_PENDING_DIREKTORAT,
                Meeting::STATUS_RETURNED_BY_DIREKTORAT,
            ]);

            if ($meeting->isDirektoratType() && (string) $meeting->directorate_response_status !== Meeting::RESPONSE_ON_SCHEDULE) {
                abort(422, 'PIC direktorat wajib memberikan tanggapan on schedule sebelum submit persiapan.');
            }
            if ($meeting->isDirektoratType() && !$actor->hasRole('administrator') && !$this->isActorAssignedToMeeting($meeting, $actor)) {
                abort(403, 'Persiapan rapat direktorat hanya untuk PIC user yang ditugaskan.');
            }

            $this->ensureActorInTargetDirectorate(
                $meeting,
                $actor,
                'Persiapan rapat hanya untuk user pada direktorat peserta/PIC rapat.'
            );
            $this->ensureNoPendingApproval($meeting);

            $preparationReadiness = $this->evaluateDirectoratePreparationReadiness($meeting);
            if (!$preparationReadiness['is_complete']) {
                if ((string) $meeting->status === Meeting::STATUS_JADWAL_TERKIRIM) {
                    $meeting->update([
                        'status' => Meeting::STATUS_PENDING_DIREKTORAT,
                        'updated_by' => $actor->id,
                    ]);
                } elseif ((string) $meeting->status === Meeting::STATUS_PENDING_DIREKTORAT) {
                    $meeting->update([
                        'updated_by' => $actor->id,
                    ]);
                }

                return [
                    'flow' => null,
                    'advanced' => false,
                    'message_type' => 'warning',
                    'message' => $this->directoratePreparationPendingMessage($meeting, $preparationReadiness['issues']),
                ];
            }

            $approvalFlow = $this->resolveDirectoratePreparationApprovalFlow($meeting, $actor);

            if ($approvalFlow === DirectorateApprovalFlow::NONE) {
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
                        'Distribusi Bahan Direktorat',
                        'Persiapan rapat langsung terkirim tanpa approval direktorat karena PIC user berposisi Deputy Director.'
                    )
                );

                return [
                    'flow' => $approvalFlow,
                    'advanced' => true,
                    'message_type' => 'success',
                    'message' => $this->directoratePreparationSubmitSuccessMessage($meeting, $approvalFlow),
                    'success_message' => $this->directoratePreparationSubmitSuccessMessage($meeting, $approvalFlow),
                ];
            }

            $pendingLabel = $approvalFlow === DirectorateApprovalFlow::DD_ONLY
                ? 'Menunggu approval DD Direktorat'
                : 'Menunggu approval EO + DD Direktorat';
            $pendingMessage = $approvalFlow === DirectorateApprovalFlow::DD_ONLY
                ? 'Persiapan rapat menunggu approval DD Direktorat.'
                : 'Persiapan rapat menunggu approval EO + DD Direktorat.';

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
                'note' => $this->buildApprovalNote($pendingLabel, $note),
            ]);

            $approvalUserIds = $approvalFlow === DirectorateApprovalFlow::DD_ONLY
                ? $this->getMeetingDeputyDirectorApproverIds($meeting, $actor)
                : $this->getMeetingDirectorateApprovalUserIds($meeting, $actor);
            $this->notifyUsers(
                $approvalUserIds,
                'meeting_directorate_approval',
                $this->meetingNotificationData(
                    $meeting,
                    $actor,
                    'Approval Direktorat',
                    $pendingMessage
                )
            );

            return [
                'flow' => $approvalFlow,
                'advanced' => true,
                'message_type' => 'success',
                'message' => $this->directoratePreparationSubmitSuccessMessage($meeting, $approvalFlow),
                'success_message' => $this->directoratePreparationSubmitSuccessMessage($meeting, $approvalFlow),
            ];
        });
    }

    public function handleDirectorateApproval(Meeting $meeting, User $actor, string $action, ?string $note = null): string
    {
        return DB::transaction(function () use ($meeting, $actor, $action, $note) {
            $meeting = $this->lockMeeting($meeting);
            $this->assertStatus($meeting, [Meeting::STATUS_WAITING_DIREKTORAT_APPROVAL]);
            $this->ensureActorInTargetDirectorate(
                $meeting,
                $actor,
                'Approval direktorat hanya untuk user pada direktorat peserta/PIC rapat.'
            );

            $pending = $this->latestPendingApproval($meeting);
            if (!$pending) {
                abort(422, 'Tidak ada approval meeting yang pending.');
            }

            $isAdmin = $actor->hasRole('administrator');
            $isCheckerExecutiveOfficer = $actor->can('meeting.checker_action') && $this->isExecutiveOfficer($actor);
            $isApproverDeputyDirector = $actor->can('meeting.approver_action') && $this->isDeputyDirector($actor);
            $requiresCheckerApproval = $this->requiresCheckerApproval($pending);
            $checkerApproved = $requiresCheckerApproval
                ? $this->isCheckerApprovedInCurrentRound($meeting, $pending->created_at)
                : false;

            if ($action === 'approve') {
                if ($requiresCheckerApproval && !$checkerApproved && ($isCheckerExecutiveOfficer || $isAdmin)) {
                    if ($this->actorAlreadyActedInRound($meeting, $actor, 'EO Direktorat', $pending->created_at)) {
                        abort(403, 'Approval EO Direktorat sudah diproses oleh user ini.');
                    }

                    Approval::create([
                        'approvable_type' => Meeting::class,
                        'approvable_id' => $meeting->id,
                        'status' => 'approved',
                        'note' => $this->buildApprovalNote('EO Direktorat Approved', $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);

                    $approverIds = $this->getMeetingDeputyDirectorApproverIds($meeting, $actor);
                    if ($approverIds->isNotEmpty()) {
                        $this->notifyUsers(
                            $approverIds,
                            'meeting_directorate_approval',
                            $this->meetingNotificationData(
                                $meeting,
                                $actor,
                                'Approval Direktorat',
                                'Persiapan rapat menunggu approval DD Direktorat.'
                            )
                        );
                    }

                    $this->notifyUsers(
                        [$meeting->created_by],
                        'meeting_directorate_action',
                        $this->meetingNotificationData(
                            $meeting,
                            $actor,
                            'Approval Direktorat',
                            'Approval EO Direktorat disetujui. Menunggu approval DD Direktorat.'
                        )
                    );

                    return 'Approval EO Direktorat disetujui. Menunggu approval DD Direktorat.';
                }

                if ((!$requiresCheckerApproval || $checkerApproved || $isAdmin) && ($isApproverDeputyDirector || $isAdmin)) {
                    if ($this->actorAlreadyActedInRound($meeting, $actor, 'DD Direktorat', $pending->created_at)) {
                        abort(403, 'Approval DD Direktorat sudah diproses oleh user ini.');
                    }

                    $pending->update([
                        'status' => 'approved',
                        'note' => $this->buildApprovalNote('DD Direktorat Approved', $note),
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
                            $requiresCheckerApproval
                                ? 'Persiapan rapat disetujui EO + DD Direktorat.'
                                : 'Persiapan rapat disetujui DD Direktorat.'
                        )
                    );

                    return 'Approval DD Direktorat disetujui.';
                }

                abort(403, 'Tahap approval direktorat tidak sesuai role user.');
            }

            if ($requiresCheckerApproval && !$checkerApproved && !$isCheckerExecutiveOfficer && !$isAdmin) {
                abort(403, 'Return pada tahap EO Direktorat hanya untuk checker dengan posisi Executive Officer.');
            }
            if ((!$requiresCheckerApproval || $checkerApproved) && !$isApproverDeputyDirector && !$isAdmin) {
                abort(403, 'Return pada tahap DD Direktorat hanya untuk approver Deputy Director.');
            }

            $pending->update([
                'status' => 'returned',
                'note' => $this->buildApprovalNote(
                    ($requiresCheckerApproval && !$checkerApproved) ? 'EO Direktorat Returned' : 'DD Direktorat Returned',
                    $note
                ),
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

            $this->addComment($meeting, $actor, '[RETURN DIREKTORAT]', $note);
            $this->notifyUsers(
                [$meeting->created_by],
                'meeting_directorate_action',
                $this->meetingNotificationData(
                    $meeting,
                    $actor,
                    'Approval Direktorat',
                    ($requiresCheckerApproval && !$checkerApproved)
                        ? 'Persiapan rapat dikembalikan EO Direktorat.'
                        : 'Persiapan rapat dikembalikan DD Direktorat.'
                )
            );

            return ($requiresCheckerApproval && !$checkerApproved)
                ? 'Approval EO Direktorat dikembalikan.'
                : 'Approval DD Direktorat dikembalikan.';
        });
    }

    private function resolveDirectoratePreparationApprovalFlow(Meeting $meeting, User $actor): string
    {
        $meeting->loadMissing('agendas.picUser.position');

        if ($meeting->agendas->isNotEmpty() && $meeting->agendas->contains(function ($agenda) {
            return (int) ($agenda->pic_user_id ?? 0) <= 0;
        })) {
            return DirectorateApprovalFlow::EO_DD;
        }

        return DirectorateApprovalFlow::forUsers($this->getPreparationPicUsers($meeting), $actor);
    }

    private function evaluateDirectoratePreparationReadiness(Meeting $meeting): array
    {
        $meeting->loadMissing(['agendas.materials', 'agendas.picUser.position']);

        $issues = $meeting->agendas
            ->sortBy(function ($agenda) {
                return (int) ($agenda->order_no ?? 0);
            })
            ->map(function ($agenda) {
                $agendaIssues = [];

                if ((int) ($agenda->pic_user_id ?? 0) <= 0) {
                    $agendaIssues[] = 'PIC user belum ditentukan';
                }

                $hasMaterial = $agenda->materials->contains(function ($material) {
                    return (int) ($material->attachment_id ?? 0) > 0;
                });

                if (!$hasMaterial) {
                    $agendaIssues[] = 'bahan rapat belum diupload';
                }

                if ($agendaIssues === []) {
                    return null;
                }

                $agendaTitle = trim((string) ($agenda->title ?? ''));
                $agendaLabel = $agenda->order_no
                    ? 'Agenda #' . $agenda->order_no
                    : 'Agenda';

                if ($agendaTitle !== '') {
                    $agendaLabel .= ' - ' . $agendaTitle;
                }

                return [
                    'label' => $agendaLabel,
                    'issues' => $agendaIssues,
                ];
            })
            ->filter()
            ->values();

        return [
            'is_complete' => $meeting->agendas->isNotEmpty() && $issues->isEmpty(),
            'issues' => $issues,
        ];
    }

    private function getPreparationPicUsers(Meeting $meeting): Collection
    {
        $meeting->loadMissing('agendas.picUser.position');

        return $meeting->agendas
            ->map(fn($agenda) => $agenda->picUser)
            ->filter(fn($user) => $user instanceof User)
            ->unique('id')
            ->values();
    }

    private function directoratePreparationSubmitSuccessMessage(Meeting $meeting, string $approvalFlow): string
    {
        $prefix = $meeting->isDirektoratType()
            ? 'Persiapan rapat direktorat berhasil disubmit'
            : 'Koordinasi rapat berhasil disubmit';

        return match ($approvalFlow) {
            DirectorateApprovalFlow::NONE => $prefix . '. Karena PIC user Deputy Director, data/bahan langsung terkirim tanpa approval direktorat.',
            DirectorateApprovalFlow::DD_ONLY => $prefix . ' untuk approval DD Direktorat.',
            default => $prefix . ' untuk approval EO + DD Direktorat.',
        };
    }

    private function directoratePreparationPendingMessage(Meeting $meeting, Collection $issues): string
    {
        $prefix = $meeting->isDirektoratType()
            ? 'Progress persiapan rapat direktorat disimpan.'
            : 'Progress koordinasi rapat disimpan.';

        if ($issues->isEmpty()) {
            return $prefix . ' Tahap berikutnya belum bisa lanjut karena agenda meeting belum lengkap.';
        }

        $issueSummary = $issues
            ->take(3)
            ->map(function (array $issue) {
                return $issue['label'] . ' (' . implode(', ', $issue['issues']) . ')';
            })
            ->implode('; ');

        $remainingCount = max($issues->count() - 3, 0);
        $remainingLabel = $remainingCount > 0
            ? " dan {$remainingCount} agenda lainnya"
            : '';

        return $prefix
            . ' Tahap berikutnya belum bisa lanjut sebelum seluruh agenda memiliki PIC user dan bahan rapat. '
            . 'Agenda yang masih belum lengkap: '
            . $issueSummary
            . $remainingLabel
            . '.';
    }

    private function requiresCheckerApproval(Approval $pendingApproval): bool
    {
        return DirectorateApprovalFlow::requiresCheckerApproval($pendingApproval);
    }

    private function isCheckerApprovedInCurrentRound(Meeting $meeting, $roundStartedAt): bool
    {
        return Approval::query()
            ->where('approvable_type', Meeting::class)
            ->where('approvable_id', $meeting->id)
            ->where('status', 'approved')
            ->where('created_at', '>=', $roundStartedAt)
            ->where('note', 'ilike', 'EO Direktorat Approved%')
            ->exists();
    }

    private function actorAlreadyActedInRound(Meeting $meeting, User $actor, string $labelPrefix, $roundStartedAt): bool
    {
        return Approval::query()
            ->where('approvable_type', Meeting::class)
            ->where('approvable_id', $meeting->id)
            ->where('acted_by', $actor->id)
            ->where('created_at', '>=', $roundStartedAt)
            ->whereIn('status', ['approved', 'returned'])
            ->get()
            ->contains(function (Approval $approval) use ($labelPrefix) {
                return Str::startsWith((string) $approval->note, $labelPrefix);
            });
    }

    private function isExecutiveOfficer(User $user): bool
    {
        return DirectorateApprovalFlow::isExecutiveOfficer($user);
    }

    private function isDeputyDirector(User $user): bool
    {
        return DirectorateApprovalFlow::isDeputyDirector($user);
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
                    MeetingDecision::STATUS_CONTINUOUS,
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
                    MeetingDecision::STATUS_CONTINUOUS,
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

        $isChecker = $actor->can('meeting.checker_action');
        $isCorpSecretary = (int) ($actor->directorate_id ?? 0) === (int) $directorateId;

        if (!$isChecker || !$isCorpSecretary) {
            abort(403, 'Aksi Corporate Secretary hanya untuk role checker di direktorat Corporate Secretary.');
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

        $eoCheckerIds = User::query()
            ->whereIn('directorate_id', $directorateIds->all())
            ->whereHas('roles', function ($query) {
                $query->where('name', 'checker');
            })
            ->whereHas('position', function ($query) {
                $query->where('name', 'ilike', '%executive officer%');
            })
            ->pluck('id');

        $deputyDirectorApproverIds = User::query()
            ->whereIn('directorate_id', $directorateIds->all())
            ->whereHas('roles', function ($query) {
                $query->where('name', 'approver');
            })
            ->whereHas('position', function ($query) {
                $query->where('name', 'ilike', '%deputy director%');
            })
            ->pluck('id');

        return $eoCheckerIds
            ->merge($deputyDirectorApproverIds)
            ->unique()
            ->values();
    }

    private function getMeetingDeputyDirectorApproverIds(Meeting $meeting, User $actor)
    {
        $directorateIds = $this->getTargetDirectorateIds($meeting, (int) ($actor->directorate_id ?? 0));
        if ($directorateIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('directorate_id', $directorateIds->all())
            ->whereHas('roles', function ($query) {
                $query->where('name', 'approver');
            })
            ->whereHas('position', function ($query) {
                $query->where('name', 'ilike', '%deputy director%');
            })
            ->pluck('id');
    }

    private function getMeetingAudienceUserIds(Meeting $meeting)
    {
        $meeting->loadMissing('participants', 'agendas', 'decisions');
        $directorateIds = $this->getTargetDirectorateIds($meeting);

        $assignedUserIds = $meeting->participants
            ->pluck('user_id')
            ->merge($meeting->agendas->pluck('pic_user_id'))
            ->merge($meeting->decisions->pluck('pic_user_id'))
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $audience = collect([$meeting->created_by])->filter();
        if ($assignedUserIds->isNotEmpty()) {
            $audience = $audience->merge($assignedUserIds);
        }
        if ($directorateIds->isNotEmpty()) {
            $directorateUsers = User::query()
                ->whereIn('directorate_id', $directorateIds->all())
                ->pluck('id');
            $audience = $audience->merge($directorateUsers);
        }

        return $audience->filter()->unique()->values();
    }

    private function getMeetingAssignedPicUserIds(Meeting $meeting)
    {
        $meeting->loadMissing('participants', 'agendas', 'decisions');

        return $meeting->participants
            ->pluck('user_id')
            ->merge($meeting->agendas->pluck('pic_user_id'))
            ->merge($meeting->decisions->pluck('pic_user_id'))
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function getTargetDirectorateIds(Meeting $meeting, int $fallbackDirectorateId = 0)
    {
        $meeting->loadMissing('participants.participantUser', 'agendas.picUser');

        $participantDirectorateIds = $meeting->participants
            ->map(function ($participant) {
                return (int) ($participant->directorate_id ?: ($participant->participantUser?->directorate_id ?? 0));
            })
            ->filter();

        $agendaDirectorateIds = $meeting->agendas
            ->map(function ($agenda) {
                return (int) ($agenda->owner_directorate_id ?: ($agenda->picUser?->directorate_id ?? 0));
            })
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

    private function ensureActorInTargetDirectorate(Meeting $meeting, User $actor, string $forbiddenMessage): void
    {
        if ($actor->hasRole('administrator')) {
            return;
        }

        if ($this->isActorAssignedToMeeting($meeting, $actor)) {
            return;
        }

        $actorDirectorateId = (int) ($actor->directorate_id ?? 0);
        if ($actorDirectorateId <= 0) {
            abort(403, $forbiddenMessage);
        }

        $targetDirectorateIds = $this->getTargetDirectorateIds($meeting);
        if ($targetDirectorateIds->isEmpty()) {
            abort(422, 'Meeting belum memiliki data peserta/PIC direktorat.');
        }

        if (!$targetDirectorateIds->contains($actorDirectorateId)) {
            abort(403, $forbiddenMessage);
        }
    }

    private function isActorAssignedToMeeting(Meeting $meeting, User $actor): bool
    {
        $meeting->loadMissing('participants', 'agendas', 'decisions');
        $actorId = (int) $actor->id;

        return $meeting->participants->contains(function ($participant) use ($actorId) {
            return (int) ($participant->user_id ?? 0) === $actorId;
        }) || $meeting->agendas->contains(function ($agenda) use ($actorId) {
            return (int) ($agenda->pic_user_id ?? 0) === $actorId;
        }) || $meeting->decisions->contains(function ($decision) use ($actorId) {
            return (int) ($decision->pic_user_id ?? 0) === $actorId;
        });
    }

    private function directorateResponseDeadlineNotificationExtra(Meeting $meeting): array
    {
        if (!$meeting->isDirektoratType()) {
            return [];
        }

        return [
            'directorate_response_deadline_at' => optional($meeting->directorateResponseDeadlineAt())->toDateTimeString(),
            'directorate_response_deadline_label' => $meeting->directorateResponseDeadlineLabel(),
        ];
    }

    private function systemMeetingNotificationData(Meeting $meeting, string $title, string $message, array $extra = []): array
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
            'created_by' => null,
        ], $extra);
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
