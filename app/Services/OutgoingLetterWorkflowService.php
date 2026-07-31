<?php

namespace Modules\Corsec\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Models\Directorate;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\OutgoingLetter;
use Modules\Corsec\Notifications\CorsecFlowNotification;
use Modules\Corsec\Support\DirectorateApprovalFlow;
use Modules\Usermanagement\Models\User;

class OutgoingLetterWorkflowService
{
    public function submit(OutgoingLetter $letter, User $actor): array
    {
        return DB::transaction(function () use ($letter, $actor) {
            if (!in_array((string) $letter->status, [OutgoingLetter::STATUS_DRAFT, OutgoingLetter::STATUS_RETURNED], true)) {
                abort(403, 'Surat keluar tidak dapat disubmit pada status ini.');
            }

            if (!$actor->hasRole('administrator') && (int) $letter->requester_directorate_id !== (int) $actor->directorate_id) {
                abort(403, 'Submit surat hanya untuk direktorat pemohon.');
            }

            if (!$letter->draft_attachment_id) {
                abort(422, 'Upload draft surat wajib diisi sebelum submit approval.');
            }

            $approvalFlow = DirectorateApprovalFlow::forActor($actor);

            if ($approvalFlow === DirectorateApprovalFlow::NONE) {
                $letter->update([
                    'authorized_status' => 'pending',
                    'authorized_at' => null,
                    'authorized_by' => null,
                    'number_requested_at' => null,
                    'number_requested_by' => null,
                    'number_request_note' => null,
                    'updated_by' => $actor->id,
                ]);
                $this->completeDirectorateApprovedState(
                    $letter,
                    $actor,
                    'Flow direktorat dilewati karena submitter berposisi Deputy Director.'
                );

                return [
                    'flow' => $approvalFlow,
                    'success_message' => $this->submitSuccessMessage($letter, $approvalFlow),
                ];
            }

            $pendingLabel = $approvalFlow === DirectorateApprovalFlow::DD_ONLY
                ? 'Menunggu approval DD Direktorat'
                : 'Menunggu approval EO dan DD Direktorat';
            $pendingMessage = $approvalFlow === DirectorateApprovalFlow::DD_ONLY
                ? 'Surat keluar menunggu approval DD Direktorat.'
                : 'Surat keluar menunggu approval EO Direktorat.';

            $letter->update([
                'status' => OutgoingLetter::STATUS_WAITING_DIR_APPROVAL,
                'authorized_status' => 'pending',
                'authorized_at' => null,
                'authorized_by' => null,
                'number_requested_at' => null,
                'number_requested_by' => null,
                'number_request_note' => null,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => OutgoingLetter::class,
                'approvable_id' => $letter->id,
                'status' => 'pending',
                'note' => $pendingLabel,
            ]);

            $approvalUserIds = $approvalFlow === DirectorateApprovalFlow::DD_ONLY
                ? $this->getDirectorateApproverIds((int) $letter->requester_directorate_id)
                : $this->getDirectorateCheckerIds((int) $letter->requester_directorate_id);
            if ($approvalUserIds->isNotEmpty()) {
                $this->notifyUsers($approvalUserIds, 'outgoing_letter_dir_approval', [
                    'title' => 'Approval Direktorat Surat Keluar',
                    'message' => $pendingMessage,
                    'outgoing_letter_id' => $letter->id,
                    'registration_no' => $letter->registration_no,
                    'subject' => $letter->subject,
                    'status' => $letter->status,
                    'target_directorate_id' => $letter->requester_directorate_id,
                    'created_by' => [
                        'id' => $actor->id,
                        'name' => $actor->name,
                    ],
                ]);
            }

            return [
                'flow' => $approvalFlow,
                'success_message' => $this->submitSuccessMessage($letter, $approvalFlow),
            ];
        });
    }

    public function requestCancellation(OutgoingLetter $letter, User $actor, string $reason): void
    {
        DB::transaction(function () use ($letter, $actor, $reason) {
            $reason = trim($reason);
            if ($reason === '') {
                abort(422, 'Alasan pembatalan wajib diisi.');
            }

            if ($letter->status === OutgoingLetter::STATUS_WAITING_CANCEL_APPROVAL) {
                abort(422, 'Permintaan pembatalan sudah diajukan.');
            }

            if ($letter->status === OutgoingLetter::STATUS_CANCELLED) {
                abort(422, 'Surat keluar sudah dibatalkan.');
            }

            if (!in_array((string) $letter->status, $this->cancellableRequestStatuses(), true)) {
                abort(422, 'Permintaan pembatalan tidak tersedia pada status ini.');
            }

            if (!$this->canRequestCancellation($letter, $actor)) {
                abort(403, 'Pembatalan hanya dapat diajukan oleh maker staff pembuat surat pada direktorat terkait.');
            }

            $currentStatus = (string) $letter->status;

            $letter->update([
                'status' => OutgoingLetter::STATUS_WAITING_CANCEL_APPROVAL,
                'cancel_previous_status' => $currentStatus,
                'cancel_reason' => $reason,
                'cancel_requested_at' => now(),
                'cancel_requested_by' => $actor->id,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => OutgoingLetter::class,
                'approvable_id' => $letter->id,
                'status' => 'pending',
                'note' => $this->buildApprovalNote('Menunggu approval pembatalan EO Direktorat', $reason),
            ]);

            $checkerIds = $this->getDirectorateCheckerIds((int) $letter->requester_directorate_id);
            if ($checkerIds->isNotEmpty()) {
                $this->notifyUsers($checkerIds, 'outgoing_letter_cancel_approval', [
                    'title' => 'Approval Pembatalan Surat Keluar',
                    'message' => 'Surat keluar menunggu approval pembatalan EO Direktorat.',
                    'outgoing_letter_id' => $letter->id,
                    'registration_no' => $letter->registration_no,
                    'subject' => $letter->subject,
                    'status' => $letter->status,
                    'target_directorate_id' => $letter->requester_directorate_id,
                    'cancel_reason' => $reason,
                    'created_by' => [
                        'id' => $actor->id,
                        'name' => $actor->name,
                    ],
                ]);
            }

            $this->notifyOutgoingDecision(
                $letter,
                $actor,
                'Permintaan pembatalan surat keluar diajukan. Menunggu approval EO Direktorat.'
            );

            $this->addOutgoingComment($letter, $actor, 'REQUEST PEMBATALAN', $reason);
        });
    }

    public function cancellationApproval(OutgoingLetter $letter, User $actor, string $action, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $action, $note) {
            if ($letter->status !== OutgoingLetter::STATUS_WAITING_CANCEL_APPROVAL) {
                abort(403, 'Approval pembatalan hanya tersedia pada status menunggu approval pembatalan.');
            }

            $normalizedAction = Str::lower(trim($action));
            if (!in_array($normalizedAction, ['approve', 'reject', 'return'], true)) {
                abort(422, 'Aksi approval pembatalan tidak valid.');
            }

            if (!$this->canApproveCancellation($letter, $actor)) {
                abort(403, 'Approval pembatalan hanya untuk EO Direktorat pada direktorat pemohon.');
            }

            $approval = $this->latestPendingApproval($letter);

            if ($normalizedAction === 'approve') {
                $this->closeOrCreateApproval(
                    $letter,
                    $approval,
                    'approved',
                    'EO Direktorat Approved Pembatalan',
                    $note,
                    $actor
                );

                $this->closePendingApprovalsAfterCancellation($letter, $actor);

                $letter->update([
                    'status' => OutgoingLetter::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor->id,
                    'authorized_status' => 'cancelled',
                    'authorized_at' => now(),
                    'authorized_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                if (
                    $letter->perihal_type === 'tanggapan_surat_masuk' &&
                    $letter->perihal_incoming_letter_id
                ) {
                    $incomingLetter = IncomingLetter::query()->find($letter->perihal_incoming_letter_id);
                    if ($incomingLetter && $incomingLetter->followup_action === 'response_letter') {
                        $hasOtherActiveResponse = OutgoingLetter::query()
                            ->where('perihal_type', 'tanggapan_surat_masuk')
                            ->where('perihal_incoming_letter_id', $incomingLetter->id)
                            ->where('id', '!=', $letter->id)
                            ->where('status', '!=', OutgoingLetter::STATUS_CANCELLED)
                            ->exists();

                        if (!$hasOtherActiveResponse) {
                            $incomingLetter->update([
                                'status' => IncomingLetter::STATUS_WAITING_RESPONSE_LETTER,
                                'updated_by' => $actor->id,
                            ]);
                        }
                    }
                }

                $this->notifyOutgoingDecision(
                    $letter,
                    $actor,
                    'Permintaan pembatalan surat keluar disetujui EO Direktorat.'
                );

                $this->addOutgoingComment($letter, $actor, 'APPROVE PEMBATALAN', $note);
                return;
            }

            $this->closeOrCreateApproval(
                $letter,
                $approval,
                'returned',
                'EO Direktorat Reject Pembatalan',
                $note,
                $actor
            );

            $restoreStatus = $this->resolveCancellationRestoreStatus($letter->cancel_previous_status);

            $letter->update([
                'status' => $restoreStatus,
                'cancel_previous_status' => null,
                'cancel_reason' => null,
                'cancel_requested_at' => null,
                'cancel_requested_by' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'updated_by' => $actor->id,
            ]);

            $this->notifyOutgoingDecision(
                $letter,
                $actor,
                'Permintaan pembatalan surat keluar ditolak EO Direktorat.'
            );

            $this->addOutgoingComment($letter, $actor, 'REJECT PEMBATALAN', $note);
        });
    }

    public function approvalAction(OutgoingLetter $letter, User $actor, string $action, ?string $note): string
    {
        return DB::transaction(function () use ($letter, $actor, $action, $note) {
            $normalizedAction = Str::lower(trim($action));
            if (!in_array($normalizedAction, ['approve', 'reject', 'return'], true)) {
                abort(422, 'Aksi approval tidak valid.');
            }

            if ($letter->status === OutgoingLetter::STATUS_WAITING_DIR_APPROVAL) {
                return $this->handleDirectorateApproval($letter, $actor, $normalizedAction, $note);
            }

            if ($letter->status === OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL) {
                return $this->handleComplianceApproval($letter, $actor, $normalizedAction, $note);
            }

            abort(403, 'Approval tidak tersedia pada status ini.');
        });
    }

    public function complianceReview(OutgoingLetter $letter, User $actor, Attachment $attachment, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $attachment, $note) {
            if ($letter->status !== OutgoingLetter::STATUS_COMPLIANCE_REVIEW) {
                abort(403, 'Review kepatuhan hanya untuk status compliance review.');
            }

            if (!$this->canSubmitComplianceReview($actor)) {
                abort(403, 'Review kepatuhan hanya untuk staff maker Direktorat Kepatuhan.');
            }

            $letter->update([
                'compliance_attachment_id' => $attachment->id,
                'status' => OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => OutgoingLetter::class,
                'approvable_id' => $letter->id,
                'status' => 'pending',
                'note' => $this->buildApprovalNote('Menunggu approval EO dan DD Direktorat Kepatuhan', $note),
            ]);

            $checkerIds = $this->getComplianceCheckerIds();
            if ($checkerIds->isNotEmpty()) {
                $this->notifyUsers($checkerIds, 'outgoing_letter_compliance_approval', [
                    'title' => 'Approval Kepatuhan Surat Keluar',
                    'message' => 'Surat keluar menunggu approval EO Kepatuhan.',
                    'outgoing_letter_id' => $letter->id,
                    'registration_no' => $letter->registration_no,
                    'subject' => $letter->subject,
                    'status' => $letter->status,
                    'created_by' => [
                        'id' => $actor->id,
                        'name' => $actor->name,
                    ],
                ]);
            }

            $this->notifyOutgoingDecision(
                $letter,
                $actor,
                'Review Direktorat Kepatuhan telah disubmit. Menunggu approval EO dan DD Kepatuhan.'
            );
        });
    }

    public function uploadFinal(
        OutgoingLetter $letter,
        User $actor,
        Attachment $attachment,
        ?string $finalUploadDate = null
    ): void
    {
        DB::transaction(function () use ($letter, $actor, $attachment, $finalUploadDate) {
            if ($letter->status !== OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD) {
                abort(403, 'Upload final surat hanya untuk status waiting final upload.');
            }

            if (!$this->canUploadFinal($letter, $actor)) {
                abort(403, 'Upload final surat hanya untuk staff maker dari direktorat terkait.');
            }

            $payload = [
                'final_attachment_id' => $attachment->id,
                'status' => OutgoingLetter::STATUS_VERIFIED,
                'updated_by' => $actor->id,
            ];

            if ($finalUploadDate) {
                $payload['final_upload_date'] = $finalUploadDate;
            }

            $letter->update($payload);

            if (
                $letter->perihal_type !== 'tanggapan_surat_masuk' ||
                !$letter->perihal_incoming_letter_id
            ) {
                return;
            }

            $incomingLetter = IncomingLetter::query()->find($letter->perihal_incoming_letter_id);
            if (!$incomingLetter || $incomingLetter->followup_action !== 'response_letter') {
                return;
            }

            $incomingLetter->update([
                'status' => IncomingLetter::STATUS_VERIFIED,
                'updated_by' => $actor->id,
            ]);

            $targetUserIds = collect([
                $incomingLetter->created_by,
                $incomingLetter->followup_submitted_by,
            ])
                ->filter()
                ->unique()
                ->values();

            if ($targetUserIds->isNotEmpty()) {
                $this->notifyUsers($targetUserIds, 'incoming_letter_action', [
                    'title' => 'Penyelesaian Surat Jawaban',
                    'message' => 'Surat jawaban selesai diunggah dan surat masuk dinyatakan selesai.',
                    'incoming_letter_id' => $incomingLetter->id,
                    'registration_no' => $incomingLetter->registration_no,
                    'subject' => $incomingLetter->subject,
                    'sender' => $incomingLetter->sender,
                    'status' => $incomingLetter->status,
                    'target_directorate_id' => $incomingLetter->target_directorate_id,
                    'created_by' => [
                        'id' => $actor->id,
                        'name' => $actor->name,
                    ],
                    'outgoing_letter_id' => $letter->id,
                    'outgoing_registration_no' => $letter->registration_no,
                ]);
            }
        });
    }

    private function handleDirectorateApproval(OutgoingLetter $letter, User $actor, string $action, ?string $note): string
    {
        if (!$actor->hasRole('administrator') && (int) $letter->requester_directorate_id !== (int) $actor->directorate_id) {
            abort(403, 'Approval direktorat hanya untuk direktorat pemohon.');
        }

        $approval = $this->latestPendingApproval($letter);
        $roundStartedAt = $approval?->created_at;
        $requiresCheckerApproval = $this->requiresCheckerApproval($approval);
        $checkerApproved = $requiresCheckerApproval
            ? $this->approvalExistsByNotePrefixInRound($letter, 'EO Direktorat Approved', $roundStartedAt)
            : false;

        $isChecker = $actor->hasRole('administrator') || $actor->can('letter.checker_action');
        $isApprover = $actor->hasRole('administrator') || $actor->can('letter.approver_action');

        if ($action === 'approve') {
            if ($requiresCheckerApproval && !$checkerApproved) {
                if (!$isChecker) {
                    abort(403, 'Approval EO Direktorat hanya untuk role checker.');
                }

                if ($this->actorAlreadyActedInRound($letter, $actor, ['EO Direktorat Approved', 'EO Direktorat Returned'], $roundStartedAt)) {
                    abort(403, 'Approval EO Direktorat sudah diproses oleh user ini.');
                }

                Approval::create([
                    'approvable_type' => OutgoingLetter::class,
                    'approvable_id' => $letter->id,
                    'status' => 'approved',
                    'note' => $this->buildApprovalNote('EO Direktorat Approved', $note),
                    'acted_by' => $actor->id,
                    'acted_at' => now(),
                ]);

                $this->notifyOutgoingDecision(
                    $letter,
                    $actor,
                    'Approval EO Direktorat disetujui. Menunggu approval DD Direktorat.'
                );

                $approverIds = $this->getDirectorateApproverIds((int) $letter->requester_directorate_id);
                if ($approverIds->isNotEmpty()) {
                    $this->notifyUsers($approverIds, 'outgoing_letter_dir_approval', [
                        'title' => 'Approval DD Direktorat Surat Keluar',
                        'message' => 'Surat keluar menunggu approval DD Direktorat.',
                        'outgoing_letter_id' => $letter->id,
                        'registration_no' => $letter->registration_no,
                        'subject' => $letter->subject,
                        'status' => $letter->status,
                        'target_directorate_id' => $letter->requester_directorate_id,
                        'created_by' => [
                            'id' => $actor->id,
                            'name' => $actor->name,
                        ],
                    ]);
                }

                return 'Approval EO Direktorat disetujui. Menunggu approval DD Direktorat.';
            }

            if (!$isApprover) {
                abort(403, 'Approval DD Direktorat hanya untuk role approver.');
            }

            if ($this->actorAlreadyActedInRound($letter, $actor, ['DD Direktorat Approved', 'DD Direktorat Returned'], $roundStartedAt)) {
                abort(403, 'Approval DD Direktorat sudah diproses oleh user ini.');
            }

            $this->closeOrCreateApproval($letter, $approval, 'approved', 'DD Direktorat Approved', $note, $actor);

            return $this->completeDirectorateApprovedState($letter, $actor, 'Approval DD Direktorat disetujui.');
        }

        if ($requiresCheckerApproval && !$checkerApproved && !$isChecker) {
            abort(403, 'Approval EO Direktorat hanya untuk role checker.');
        }
        if ((!$requiresCheckerApproval || $checkerApproved) && !$isApprover) {
            abort(403, 'Approval DD Direktorat hanya untuk role approver.');
        }

        $fallbackLabel = 'DD Direktorat Returned';
        if ($requiresCheckerApproval && !$checkerApproved && $isChecker) {
             $fallbackLabel = 'EO Direktorat Returned';
         }

        $this->closeOrCreateApproval($letter, $approval, 'returned', $fallbackLabel, $note, $actor);

        $letter->update([
            'status' => OutgoingLetter::STATUS_RETURNED,
            'authorized_status' => 'returned',
            'authorized_at' => null,
            'authorized_by' => null,
            'updated_by' => $actor->id,
        ]);

        $this->notifyOutgoingDecision(
            $letter,
            $actor,
            'Approval Direktorat dikembalikan.'
        );

        $this->addOutgoingComment($letter, $actor, 'RETURN APPROVAL DIREKTORAT', $note);

        return ($requiresCheckerApproval && !$checkerApproved && $isChecker)
             ? 'Approval EO Direktorat dikembalikan.'
             : 'Approval DD Direktorat dikembalikan.';
    }

    private function handleComplianceApproval(OutgoingLetter $letter, User $actor, string $action, ?string $note): string
    {
        if (!$actor->hasRole('administrator') && !$this->isComplianceDirectorate($actor)) {
            abort(403, 'Approval kepatuhan hanya untuk Direktorat Kepatuhan.');
        }

        $approval = $this->latestPendingApproval($letter);
        $checkerApproved = $this->approvalExistsByNotePrefix($letter, 'EO Kepatuhan Approved');

        $isChecker = $actor->hasRole('administrator') || $actor->can('letter.checker_action');
        $isApprover = $actor->hasRole('administrator') || $actor->can('letter.approver_action');

        if ($action === 'approve') {
            if (!$checkerApproved) {
                if (!$isChecker) {
                    abort(403, 'Approval EO Kepatuhan hanya untuk role checker.');
                }

                if ($this->actorAlreadyActed($letter, $actor, ['EO Kepatuhan Approved', 'EO Kepatuhan Returned'])) {
                    abort(403, 'Approval EO Kepatuhan sudah diproses oleh user ini.');
                }

                Approval::create([
                    'approvable_type' => OutgoingLetter::class,
                    'approvable_id' => $letter->id,
                    'status' => 'approved',
                    'note' => $this->buildApprovalNote('EO Kepatuhan Approved', $note),
                    'acted_by' => $actor->id,
                    'acted_at' => now(),
                ]);

                $this->notifyOutgoingDecision(
                    $letter,
                    $actor,
                    'Approval EO Kepatuhan disetujui. Menunggu approval DD Kepatuhan.'
                );

                $approverIds = $this->getComplianceApproverIds();
                if ($approverIds->isNotEmpty()) {
                    $this->notifyUsers($approverIds, 'outgoing_letter_compliance_approval', [
                        'title' => 'Approval DD Kepatuhan Surat Keluar',
                        'message' => 'Surat keluar menunggu approval DD Kepatuhan.',
                        'outgoing_letter_id' => $letter->id,
                        'registration_no' => $letter->registration_no,
                        'subject' => $letter->subject,
                        'status' => $letter->status,
                        'created_by' => [
                            'id' => $actor->id,
                            'name' => $actor->name,
                        ],
                    ]);
                }

                return 'Approval EO Kepatuhan disetujui. Menunggu approval DD Kepatuhan.';
            }

            if (!$isApprover) {
                abort(403, 'Approval DD Kepatuhan hanya untuk role approver.');
            }

            if ($this->actorAlreadyActed($letter, $actor, ['DD Kepatuhan Approved', 'DD Kepatuhan Returned'])) {
                abort(403, 'Approval DD Kepatuhan sudah diproses oleh user ini.');
            }

            $this->closeOrCreateApproval($letter, $approval, 'approved', 'DD Kepatuhan Approved', $note, $actor);

            $letter->update([
                'status' => OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD,
                'authorized_status' => 'authorized',
                'authorized_at' => now(),
                'authorized_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->notifyOutgoingDecision(
                $letter,
                $actor,
                'Approval DD Kepatuhan disetujui. Menunggu final upload oleh staff direktorat terkait.'
            );

            $this->notifyFinalUploadRequired($letter, $actor);

            return 'Approval DD Kepatuhan disetujui. Menunggu final upload oleh staff direktorat terkait.';
        }

        $fallbackLabel = 'EO+DD Kepatuhan Returned';
        if (!$checkerApproved && $isChecker) {
            $fallbackLabel = 'EO Kepatuhan Returned';
        } elseif ($checkerApproved && $isApprover) {
            $fallbackLabel = 'DD Kepatuhan Returned';
        }

        $this->closeOrCreateApproval($letter, $approval, 'returned', $fallbackLabel, $note, $actor);

        $letter->update([
            'status' => OutgoingLetter::STATUS_RETURNED,
            'authorized_status' => 'returned',
            'authorized_at' => null,
            'authorized_by' => null,
            'updated_by' => $actor->id,
        ]);

        $this->notifyOutgoingDecision(
            $letter,
            $actor,
            'Approval Direktorat Kepatuhan dikembalikan.'
        );

        $this->addOutgoingComment($letter, $actor, 'RETURN APPROVAL KEPATUHAN', $note);

        return (!$checkerApproved && $isChecker)
            ? 'Approval EO Kepatuhan dikembalikan.'
            : 'Approval DD Kepatuhan dikembalikan.';
    }

    private function latestPendingApproval(OutgoingLetter $letter): ?Approval
    {
        return Approval::query()
            ->where('approvable_type', OutgoingLetter::class)
            ->where('approvable_id', $letter->id)
            ->where('status', 'pending')
            ->latest()
            ->first();
    }

    private function closeOrCreateApproval(
        OutgoingLetter $letter,
        ?Approval $pendingApproval,
        string $status,
        string $label,
        ?string $note,
        User $actor
    ): void {
        $payload = [
            'status' => $status,
            'note' => $this->buildApprovalNote($label, $note),
            'acted_by' => $actor->id,
            'acted_at' => now(),
        ];

        if ($pendingApproval) {
            $pendingApproval->update($payload);
            return;
        }

        Approval::create([
            'approvable_type' => OutgoingLetter::class,
            'approvable_id' => $letter->id,
            ...$payload,
        ]);
    }

    private function closePendingApprovalsAfterCancellation(OutgoingLetter $letter, User $actor): void
    {
        Approval::query()
            ->where('approvable_type', OutgoingLetter::class)
            ->where('approvable_id', $letter->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'returned',
                'note' => 'Auto close karena pembatalan surat disetujui EO Direktorat',
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);
    }

    private function approvalExistsByNotePrefix(OutgoingLetter $letter, string $prefix): bool
    {
        return Approval::query()
            ->where('approvable_type', OutgoingLetter::class)
            ->where('approvable_id', $letter->id)
            ->where('status', 'approved')
            ->where('note', 'ilike', $prefix . '%')
            ->exists();
    }

    private function approvalExistsByNotePrefixInRound(OutgoingLetter $letter, string $prefix, $roundStartedAt): bool
    {
        $query = Approval::query()
            ->where('approvable_type', OutgoingLetter::class)
            ->where('approvable_id', $letter->id)
            ->where('status', 'approved')
            ->where('note', 'ilike', $prefix . '%');

        if ($roundStartedAt) {
            $query->where('created_at', '>=', $roundStartedAt);
        }

        return $query->exists();
    }

    private function actorAlreadyActed(OutgoingLetter $letter, User $actor, array $notePrefixes): bool
    {
        $approvals = Approval::query()
            ->where('approvable_type', OutgoingLetter::class)
            ->where('approvable_id', $letter->id)
            ->where('acted_by', $actor->id)
            ->whereIn('status', ['approved', 'returned'])
            ->get(['note']);

        return $approvals->contains(function ($approval) use ($notePrefixes) {
            $note = (string) $approval->note;
            foreach ($notePrefixes as $prefix) {
                if (Str::startsWith($note, $prefix)) {
                    return true;
                }
            }
            return false;
            });
    }

    private function actorAlreadyActedInRound(OutgoingLetter $letter, User $actor, array $notePrefixes, $roundStartedAt): bool
    {
        $query = Approval::query()
            ->where('approvable_type', OutgoingLetter::class)
            ->where('approvable_id', $letter->id)
            ->where('acted_by', $actor->id)
            ->whereIn('status', ['approved', 'returned']);

        if ($roundStartedAt) {
            $query->where('created_at', '>=', $roundStartedAt);
        }

        $approvals = $query->get(['note']);

        return $approvals->contains(function ($approval) use ($notePrefixes) {
            $note = (string) $approval->note;
            foreach ($notePrefixes as $prefix) {
                if (Str::startsWith($note, $prefix)) {
                    return true;
                }
            }

            return false;
        });
    }

    private function requiresCheckerApproval(?Approval $pendingApproval): bool
    {
        return DirectorateApprovalFlow::requiresCheckerApproval($pendingApproval);
    }

    private function submitSuccessMessage(OutgoingLetter $letter, string $approvalFlow): string
    {
        return match ($approvalFlow) {
            DirectorateApprovalFlow::NONE => $letter->need_compliance_review
                ? 'Surat keluar berhasil disubmit. Karena submitter Deputy Director, flow direktorat dilewati dan langsung lanjut ke review Direktorat Kepatuhan.'
                : 'Surat keluar berhasil disubmit. Karena submitter Deputy Director, flow direktorat dilewati dan langsung menunggu final upload.',
            DirectorateApprovalFlow::DD_ONLY => 'Surat keluar berhasil disubmit untuk approval DD Direktorat.',
            default => 'Surat keluar berhasil disubmit untuk approval EO + DD Direktorat.',
        };
    }

    private function completeDirectorateApprovedState(OutgoingLetter $letter, User $actor, string $approvalMessagePrefix): string
    {
        $nextStatus = $letter->need_compliance_review
            ? OutgoingLetter::STATUS_COMPLIANCE_REVIEW
            : OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD;

        $updatePayload = [
            'status' => $nextStatus,
            'updated_by' => $actor->id,
        ];

        if (!$letter->need_compliance_review) {
            $updatePayload['authorized_status'] = 'authorized';
            $updatePayload['authorized_at'] = now();
            $updatePayload['authorized_by'] = $actor->id;
        }

        $letter->update($updatePayload);

        if ($letter->need_compliance_review) {
            $message = trim($approvalMessagePrefix . ' Lanjut review oleh staff Direktorat Kepatuhan.');
            $this->notifyOutgoingDecision($letter, $actor, $message);

            $makerIds = $this->getComplianceMakerStaffIds();
            if ($makerIds->isNotEmpty()) {
                $this->notifyUsers($makerIds, 'outgoing_letter_compliance_review', [
                    'title' => 'Review Kepatuhan Surat Keluar',
                    'message' => 'Surat keluar menunggu review staff Direktorat Kepatuhan.',
                    'outgoing_letter_id' => $letter->id,
                    'registration_no' => $letter->registration_no,
                    'subject' => $letter->subject,
                    'status' => $letter->status,
                    'created_by' => [
                        'id' => $actor->id,
                        'name' => $actor->name,
                    ],
                ]);
            }

            return $message;
        }

        $message = trim($approvalMessagePrefix . ' Menunggu final upload oleh staff direktorat terkait.');
        $this->notifyOutgoingDecision($letter, $actor, $message);
        $this->notifyFinalUploadRequired($letter, $actor);

        return $message;
    }

    private function buildApprovalNote(string $label, ?string $note): string
    {
        $note = trim((string) $note);
        return $note !== '' ? $label . ' - ' . $note : $label;
    }

    private function notifyOutgoingDecision(OutgoingLetter $letter, User $actor, string $message): void
    {
        $targetId = $letter->created_by;
        if (!$targetId) {
            return;
        }

        $this->notifyUsers([$targetId], 'outgoing_letter_action', [
            'title' => 'Surat keluar',
            'message' => $message,
            'outgoing_letter_id' => $letter->id,
            'registration_no' => $letter->registration_no,
            'subject' => $letter->subject,
            'status' => $letter->status,
            'created_by' => [
                'id' => $actor->id,
                'name' => $actor->name,
            ],
        ]);
    }

    private function notifyFinalUploadRequired(OutgoingLetter $letter, User $actor): void
    {
        $staffIds = $this->getRequesterDirectorateMakerStaffIds($letter);
        if ($staffIds->isEmpty() && $letter->created_by) {
            $staffIds = collect([(string) $letter->created_by]);
        }

        if ($staffIds->isNotEmpty()) {
            $this->notifyUsers($staffIds, 'outgoing_letter_final_upload', [
                'title' => 'Final Upload Surat Keluar',
                'message' => 'Surat keluar menunggu final upload oleh staff direktorat terkait.',
                'outgoing_letter_id' => $letter->id,
                'registration_no' => $letter->registration_no,
                'subject' => $letter->subject,
                'status' => $letter->status,
                'created_by' => [
                    'id' => $actor->id,
                    'name' => $actor->name,
                ],
            ]);
        }
    }

    private function notifyUsers(iterable $userIds, string $type, array $data): void
    {
        CorsecFlowNotification::insertForUsers($userIds, $type, $data);
    }

    private function addOutgoingComment(OutgoingLetter $letter, User $actor, string $label, ?string $note): void
    {
        $note = trim((string) $note);
        if ($note === '') {
            return;
        }

        Comment::create([
            'commentable_type' => OutgoingLetter::class,
            'commentable_id' => $letter->id,
            'body' => '[' . $label . '] ' . $note,
            'created_by' => $actor->id,
        ]);
    }

    private function canUploadFinal(OutgoingLetter $letter, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if (!$user->can('letter.maker_action')) {
            return false;
        }

        if ((int) $letter->requester_directorate_id !== (int) $user->directorate_id) {
            return false;
        }

        $user->loadMissing('position');
        $positionName = Str::lower((string) ($user->position?->name ?? ''));

        return $positionName !== '' && Str::contains($positionName, 'staff');
    }

    private function canRequestCancellation(OutgoingLetter $letter, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ((int) $letter->created_by !== (int) $user->id) {
            return false;
        }

        return $this->canUploadFinal($letter, $user);
    }

    private function canApproveCancellation(OutgoingLetter $letter, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ((int) $letter->requester_directorate_id !== (int) $user->directorate_id) {
            return false;
        }

        return $user->can('letter.checker_action');
    }

    private function cancellableRequestStatuses(): array
    {
        return [
            OutgoingLetter::STATUS_DRAFT,
            OutgoingLetter::STATUS_RETURNED,
            OutgoingLetter::STATUS_WAITING_DIR_APPROVAL,
            OutgoingLetter::STATUS_COMPLIANCE_REVIEW,
            OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL,
            OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD,
        ];
    }

    private function resolveCancellationRestoreStatus(?string $status): string
    {
        $allowed = $this->cancellableRequestStatuses();
        if (in_array((string) $status, $allowed, true)) {
            return (string) $status;
        }

        return OutgoingLetter::STATUS_RETURNED;
    }

    private function canSubmitComplianceReview(User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if (!$user->can('letter.maker_action') || !$this->isComplianceDirectorate($user)) {
            return false;
        }

        $user->loadMissing('position');
        $positionName = Str::lower((string) ($user->position?->name ?? ''));

        return $positionName !== '' && Str::contains($positionName, 'staff');
    }

    private function getRequesterDirectorateMakerStaffIds(OutgoingLetter $letter)
    {
        if (!$letter->requester_directorate_id) {
            return collect();
        }

        return User::query()
            ->where('directorate_id', $letter->requester_directorate_id)
            ->where($this->stageActionPermissionQuery('letter.maker_action'))
            ->whereHas('position', function ($query) {
                $query->where('name', 'ilike', '%staff%');
            })
            ->pluck('id');
    }

    private function getDirectorateCheckerIds(int $directorateId)
    {
        if ($directorateId <= 0) {
            return collect();
        }

        return User::query()
            ->where('directorate_id', $directorateId)
            ->where($this->stageActionPermissionQuery('letter.checker_action'))
            ->pluck('id');
    }

    private function getDirectorateApproverIds(int $directorateId)
    {
        if ($directorateId <= 0) {
            return collect();
        }

        return User::query()
            ->where('directorate_id', $directorateId)
            ->where($this->stageActionPermissionQuery('letter.approver_action'))
            ->pluck('id');
    }

    private function getComplianceMakerStaffIds()
    {
        $directorateId = $this->getComplianceDirectorateId();
        if (!$directorateId) {
            return collect();
        }

        return User::query()
            ->where('directorate_id', $directorateId)
            ->where($this->stageActionPermissionQuery('letter.maker_action'))
            ->whereHas('position', function ($query) {
                $query->where('name', 'ilike', '%staff%');
            })
            ->pluck('id');
    }

    private function getComplianceCheckerIds()
    {
        $directorateId = $this->getComplianceDirectorateId();
        if (!$directorateId) {
            return collect();
        }

        return User::query()
            ->where('directorate_id', $directorateId)
            ->where($this->stageActionPermissionQuery('letter.checker_action'))
            ->pluck('id');
    }

    private function getComplianceApproverIds()
    {
        $directorateId = $this->getComplianceDirectorateId();
        if (!$directorateId) {
            return collect();
        }

        return User::query()
            ->where('directorate_id', $directorateId)
            ->where($this->stageActionPermissionQuery('letter.approver_action'))
            ->pluck('id');
    }

    private function stageActionPermissionQuery(string $permissionName): \Closure
    {
        return function ($query) use ($permissionName) {
            $query->whereHas('permissions', function ($permissionQuery) use ($permissionName) {
                $permissionQuery->where('name', $permissionName);
            })->orWhereHas('roles.permissions', function ($permissionQuery) use ($permissionName) {
                $permissionQuery->where('name', $permissionName);
            });
        };
    }

    private function isComplianceDirectorate(User $user): bool
    {
        $complianceCode = (string) config('corsec.compliance_directorate_code', '');
        $user->loadMissing('directorate');

        $directorateCode = $user->directorate?->code;
        $directorateName = $user->directorate?->name;

        if ($directorateCode && $complianceCode !== '' && $directorateCode === $complianceCode) {
            return true;
        }

        if ($directorateName) {
            $normalized = Str::lower($directorateName);
            return Str::contains($normalized, 'kepatuhan') || Str::contains($normalized, 'compliance');
        }

        return false;
    }

    private function getComplianceDirectorateId(): ?int
    {
        $complianceCode = (string) config('corsec.compliance_directorate_code', '');
        if ($complianceCode !== '') {
            $directorateId = Directorate::query()
                ->where('code', $complianceCode)
                ->value('id');
            if ($directorateId) {
                return (int) $directorateId;
            }
        }

        return Directorate::query()
            ->where(function ($query) {
                $query->where('name', 'ilike', '%kepatuhan%')
                    ->orWhere('name', 'ilike', '%compliance%');
            })
            ->value('id');
    }

}
