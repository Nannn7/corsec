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
use Modules\Usermanagement\Models\User;

class OutgoingLetterWorkflowService
{
    public function submit(OutgoingLetter $letter, User $actor): void
    {
        DB::transaction(function () use ($letter, $actor) {
            if (!in_array((string) $letter->status, [OutgoingLetter::STATUS_DRAFT, OutgoingLetter::STATUS_RETURNED], true)) {
                abort(403, 'Surat keluar tidak dapat disubmit pada status ini.');
            }

            if (!$actor->hasRole('administrator') && (int) $letter->requester_directorate_id !== (int) $actor->directorate_id) {
                abort(403, 'Submit surat hanya untuk direktorat pemohon.');
            }

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
                'note' => 'Menunggu approval EO dan DD Direktorat',
            ]);

            $checkerIds = $this->getDirectorateCheckerIds((int) $letter->requester_directorate_id);
            if ($checkerIds->isNotEmpty()) {
                $this->notifyUsers($checkerIds, 'outgoing_letter_dir_approval', [
                    'title' => 'Approval Direktorat Surat Keluar',
                    'message' => 'Surat keluar menunggu approval EO Direktorat.',
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
        });
    }

    public function approvalAction(OutgoingLetter $letter, User $actor, string $action, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $action, $note) {
            $normalizedAction = Str::lower(trim($action));
            if (!in_array($normalizedAction, ['approve', 'reject', 'return'], true)) {
                abort(422, 'Aksi approval tidak valid.');
            }

            if ($letter->status === OutgoingLetter::STATUS_WAITING_DIR_APPROVAL) {
                $this->handleDirectorateApproval($letter, $actor, $normalizedAction, $note);
                return;
            }

            if ($letter->status === OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL) {
                $this->handleComplianceApproval($letter, $actor, $normalizedAction, $note);
                return;
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

    public function verifyAction(OutgoingLetter $letter, User $actor, string $action, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $action, $note) {
            if ($letter->status !== OutgoingLetter::STATUS_WAITING_VERIFICATION) {
                abort(403, 'Verifikasi EO Corp Affair tidak sesuai status.');
            }

            $isAdmin = $actor->hasRole('administrator');
            if (!$isAdmin && !$this->isCorpSecretaryDirectorate($actor)) {
                abort(403, 'Verifikasi hanya untuk direktorat Corporate Secretary.');
            }
            if (!$isAdmin && !$actor->hasRole('checker')) {
                abort(403, 'Verifikasi hanya untuk role checker dari Corporate Secretary.');
            }

            $approval = $this->latestPendingApproval($letter);

            if (in_array($action, ['verify', 'approve'], true)) {
                $this->closeOrCreateApproval($letter, $approval, 'approved', 'EO Corp Affair Approved', $note, $actor);

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
                    'Verifikasi EO Corp Affair disetujui. Menunggu final upload oleh staff direktorat terkait.'
                );

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

                return;
            }

            if (in_array($action, ['return', 'reject'], true)) {
                $this->closeOrCreateApproval($letter, $approval, 'returned', 'EO Corp Affair Returned', $note, $actor);

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
                    'Verifikasi EO Corp Affair dikembalikan.'
                );

                $this->addOutgoingComment($letter, $actor, 'RETURN VERIFIKASI EO CORP AFFAIR', $note);
            }
        });
    }

    public function uploadFinal(OutgoingLetter $letter, User $actor, Attachment $attachment): void
    {
        DB::transaction(function () use ($letter, $actor, $attachment) {
            if ($letter->status !== OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD) {
                abort(403, 'Upload final surat hanya untuk status waiting final upload.');
            }

            if (!$this->canUploadFinal($letter, $actor)) {
                abort(403, 'Upload final surat hanya untuk staff maker dari direktorat terkait.');
            }

            $letter->update([
                'final_attachment_id' => $attachment->id,
                'status' => OutgoingLetter::STATUS_VERIFIED,
                'updated_by' => $actor->id,
            ]);

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

            Approval::create([
                'approvable_type' => IncomingLetter::class,
                'approvable_id' => $incomingLetter->id,
                'status' => 'approved',
                'note' => 'Verifikasi via Surat Keluar - ' . ($letter->registration_no ?? ('ID ' . $letter->id)),
                'acted_by' => $actor->id,
                'acted_at' => now(),
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
                    'message' => 'Surat masuk ditandai selesai berdasarkan final Surat Keluar.',
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

    private function handleDirectorateApproval(OutgoingLetter $letter, User $actor, string $action, ?string $note): void
    {
        if (!$actor->hasRole('administrator') && (int) $letter->requester_directorate_id !== (int) $actor->directorate_id) {
            abort(403, 'Approval direktorat hanya untuk direktorat pemohon.');
        }

        $approval = $this->latestPendingApproval($letter);
        $checkerApproved = $this->approvalExistsByNotePrefix($letter, 'EO Direktorat Approved');

        $isChecker = $actor->hasRole('checker') || $actor->hasRole('administrator');
        $isApprover = $actor->hasRole('approver') || $actor->hasRole('administrator');

        if ($action === 'approve') {
            if (!$checkerApproved) {
                if (!$isChecker) {
                    abort(403, 'Approval EO Direktorat hanya untuk role checker.');
                }

                if ($this->actorAlreadyActed($letter, $actor, ['EO Direktorat Approved', 'EO Direktorat Returned'])) {
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

                return;
            }

            if (!$isApprover) {
                abort(403, 'Approval DD Direktorat hanya untuk role approver.');
            }

            if ($this->actorAlreadyActed($letter, $actor, ['DD Direktorat Approved', 'DD Direktorat Returned'])) {
                abort(403, 'Approval DD Direktorat sudah diproses oleh user ini.');
            }

            $this->closeOrCreateApproval($letter, $approval, 'approved', 'DD Direktorat Approved', $note, $actor);

            $nextStatus = $letter->need_compliance_review
                ? OutgoingLetter::STATUS_COMPLIANCE_REVIEW
                : OutgoingLetter::STATUS_WAITING_VERIFICATION;

            $letter->update([
                'status' => $nextStatus,
                'updated_by' => $actor->id,
            ]);

            if ($letter->need_compliance_review) {
                $this->notifyOutgoingDecision(
                    $letter,
                    $actor,
                    'Approval DD Direktorat disetujui. Lanjut review Direktorat Kepatuhan.'
                );

                $complianceStaffIds = $this->getComplianceMakerStaffIds();
                if ($complianceStaffIds->isNotEmpty()) {
                    $this->notifyUsers($complianceStaffIds, 'outgoing_letter_compliance_review', [
                        'title' => 'Review Kepatuhan Surat Keluar',
                        'message' => 'Surat keluar menunggu review Direktorat Kepatuhan.',
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
            } else {
                $this->notifyOutgoingDecision(
                    $letter,
                    $actor,
                    'Approval DD Direktorat disetujui. Lanjut verifikasi EO Corp Affair.'
                );

                $checkerIds = $this->getCorpSecretaryCheckerIds();
                if ($checkerIds->isNotEmpty()) {
                    $this->notifyUsers($checkerIds, 'outgoing_letter_corpsec_approval', [
                        'title' => 'Verifikasi Surat Keluar',
                        'message' => 'Surat keluar menunggu verifikasi EO Corp Affair.',
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

            return;
        }

        $fallbackLabel = 'EO+DD Direktorat Returned';
        if (!$checkerApproved && $actor->hasRole('checker')) {
            $fallbackLabel = 'EO Direktorat Returned';
        } elseif ($checkerApproved && $actor->hasRole('approver')) {
            $fallbackLabel = 'DD Direktorat Returned';
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
    }

    private function handleComplianceApproval(OutgoingLetter $letter, User $actor, string $action, ?string $note): void
    {
        if (!$actor->hasRole('administrator') && !$this->isComplianceDirectorate($actor)) {
            abort(403, 'Approval kepatuhan hanya untuk Direktorat Kepatuhan.');
        }

        $approval = $this->latestPendingApproval($letter);
        $checkerApproved = $this->approvalExistsByNotePrefix($letter, 'EO Kepatuhan Approved');

        $isChecker = $actor->hasRole('checker') || $actor->hasRole('administrator');
        $isApprover = $actor->hasRole('approver') || $actor->hasRole('administrator');

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

                return;
            }

            if (!$isApprover) {
                abort(403, 'Approval DD Kepatuhan hanya untuk role approver.');
            }

            if ($this->actorAlreadyActed($letter, $actor, ['DD Kepatuhan Approved', 'DD Kepatuhan Returned'])) {
                abort(403, 'Approval DD Kepatuhan sudah diproses oleh user ini.');
            }

            $this->closeOrCreateApproval($letter, $approval, 'approved', 'DD Kepatuhan Approved', $note, $actor);

            $letter->update([
                'status' => OutgoingLetter::STATUS_WAITING_VERIFICATION,
                'updated_by' => $actor->id,
            ]);

            $this->notifyOutgoingDecision(
                $letter,
                $actor,
                'Approval DD Kepatuhan disetujui. Lanjut verifikasi EO Corp Affair.'
            );

            $checkerIds = $this->getCorpSecretaryCheckerIds();
            if ($checkerIds->isNotEmpty()) {
                $this->notifyUsers($checkerIds, 'outgoing_letter_corpsec_approval', [
                    'title' => 'Verifikasi Surat Keluar',
                    'message' => 'Surat keluar menunggu verifikasi EO Corp Affair.',
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

            return;
        }

        $fallbackLabel = 'EO+DD Kepatuhan Returned';
        if (!$checkerApproved && $actor->hasRole('checker')) {
            $fallbackLabel = 'EO Kepatuhan Returned';
        } elseif ($checkerApproved && $actor->hasRole('approver')) {
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

    private function approvalExistsByNotePrefix(OutgoingLetter $letter, string $prefix): bool
    {
        return Approval::query()
            ->where('approvable_type', OutgoingLetter::class)
            ->where('approvable_id', $letter->id)
            ->where('status', 'approved')
            ->where('note', 'ilike', $prefix . '%')
            ->exists();
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

        if (!$user->hasRole('maker')) {
            return false;
        }

        if ((int) $letter->requester_directorate_id !== (int) $user->directorate_id) {
            return false;
        }

        $user->loadMissing('position');
        $positionName = Str::lower((string) ($user->position?->name ?? ''));

        return $positionName !== '' && Str::contains($positionName, 'staff');
    }

    private function canSubmitComplianceReview(User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if (!$user->hasRole('maker') || !$this->isComplianceDirectorate($user)) {
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
            ->whereHas('roles', function ($query) {
                $query->where('name', 'maker');
            })
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
            ->whereHas('roles', function ($query) {
                $query->where('name', 'checker');
            })
            ->pluck('id');
    }

    private function getDirectorateApproverIds(int $directorateId)
    {
        if ($directorateId <= 0) {
            return collect();
        }

        return User::query()
            ->where('directorate_id', $directorateId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'approver');
            })
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
            ->whereHas('roles', function ($query) {
                $query->where('name', 'maker');
            })
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
            ->whereHas('roles', function ($query) {
                $query->where('name', 'checker');
            })
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
            ->whereHas('roles', function ($query) {
                $query->where('name', 'approver');
            })
            ->pluck('id');
    }

    private function isCorpSecretaryDirectorate(User $user): bool
    {
        $corpCode = (string) config('corsec.eo_corp_affair_directorate_code', '');
        $user->loadMissing('directorate');

        $directorateCode = $user->directorate?->code;
        $directorateName = $user->directorate?->name;

        if ($directorateCode && $corpCode !== '' && $directorateCode === $corpCode) {
            return true;
        }

        if ($directorateName) {
            $normalized = Str::lower($directorateName);
            return Str::contains($normalized, 'corporate secretary');
        }

        return false;
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

        return Directorate::query()
            ->where('name', 'ilike', '%corporate secretary%')
            ->value('id');
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

    private function getCorpSecretaryCheckerIds()
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
}
