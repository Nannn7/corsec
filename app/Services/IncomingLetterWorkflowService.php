<?php

namespace Modules\Corsec\Services;

use Modules\Usermanagement\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Corsec\Models\Directorate;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\Attachable;
use Modules\Corsec\Notifications\CorsecFlowNotification;

class IncomingLetterWorkflowService
{
    public function submitToEoCorpAffair(IncomingLetter $incomingLetter, User $actor): void
    {
        DB::transaction(function () use ($incomingLetter, $actor) {
            $incomingLetter->update([
                'status' => IncomingLetter::STATUS_DISPATCHED,
                'authorized_status' => 'authorized',
                'authorized_at' => now(),
                'authorized_by' => $actor->id,
                'corp_secretary_validation_requested_at' => now(),
                'corp_secretary_validated_at' => null,
                'corp_secretary_validated_by' => null,
                'corp_secretary_validation_comment' => null,
                'updated_by' => $actor->id,
            ]);

            $this->notifyCorpSecretaryValidationRequired($incomingLetter, $actor);
        });
    }

    public function circulateToDirectorate(IncomingLetter $incomingLetter, User $actor, int $toDirectorateId, ?string $note): void
    {
        DB::transaction(function () use ($incomingLetter, $actor, $toDirectorateId, $note) {
            $incomingLetter->update([
                'target_directorate_id' => $toDirectorateId,
                'status' => IncomingLetter::STATUS_DISPATCHED,
                'updated_by' => $actor->id,
                'last_routed_from_directorate_id' => $actor->directorate_id,
                'last_routed_to_directorate_id' => $toDirectorateId,
                'last_routed_from_user_id' => $actor->id,
                'last_routed_to_user_id' => null,
                'last_route_note' => $note,
                'last_routed_at' => now(),
            ]);

            $targetUserIds = User::query()
                ->where('directorate_id', $toDirectorateId)
                ->pluck('id');

            if ($targetUserIds->isNotEmpty()) {
                CorsecFlowNotification::insertForUsers($targetUserIds, 'incoming_letter_dir_circulation', [
                    'title' => 'Surat masuk baru',
                    'message' => 'Surat masuk perlu tindak lanjut direktorat.',
                    'incoming_letter_id' => $incomingLetter->id,
                    'registration_no' => $incomingLetter->registration_no,
                    'subject' => $incomingLetter->subject,
                    'sender' => $incomingLetter->sender,
                    'status' => $incomingLetter->status,
                    'target_directorate_id' => $toDirectorateId,
                    'created_by' => [
                        'id' => $actor->id,
                        'name' => $actor->name,
                    ],
                ]);
            }
        });
    }

    public function handleApprovalAction(IncomingLetter $incomingLetter, User $actor, string $action, ?string $note): string
    {
        return DB::transaction(function () use ($incomingLetter, $actor, $action, $note) {
            // close latest pending approval
            $approval = Approval::query()
                ->where('approvable_type', IncomingLetter::class)
                ->where('approvable_id', $incomingLetter->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            $isAdmin = $actor->hasRole('administrator');
            $isChecker = $actor->hasRole('checker');
            $isApprover = $actor->hasRole('approver');
            $isEoCorpAffairActor = $this->isEoCorpAffairActor($actor);

            if ($incomingLetter->authorized_status === 'pending' || $incomingLetter->status === IncomingLetter::STATUS_ON_APPROVAL) {
                if (!$isAdmin && !$isEoCorpAffairActor) {
                    abort(403, 'Approval EO Corp Affair hanya untuk checker/approver dari direktorat Corporate Secretary.');
                }

                if ($this->actorAlreadyActed($incomingLetter, $actor, 'EO Corp Affair')) {
                    abort(403, 'Approval EO Corp Affair sudah diproses.');
                }
                $label = $action === 'approve' ? 'EO Corp Affair Approved' : 'EO Corp Affair Returned';
                if ($approval) {
                    $approval->update([
                        'status' => $action === 'approve' ? 'approved' : 'returned',
                        'note' => $this->buildApprovalNote($label, $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);
                }

                if ($action === 'approve') {
                    $incomingLetter->update([
                        'authorized_status' => 'authorized',
                        'authorized_at' => now(),
                        'authorized_by' => $actor->id,
                        'status' => IncomingLetter::STATUS_IN_PROGRESS,
                        'updated_by' => $actor->id,
                    ]);
                } else {
                    $incomingLetter->update([
                        'status' => IncomingLetter::STATUS_RETURNED,
                        'authorized_status' => 'returned',
                        'updated_by' => $actor->id,
                    ]);
                }

                $this->notifyIncomingDecision(
                    $incomingLetter,
                    $actor,
                    [$incomingLetter->created_by],
                    'Approval EO Corp Affair',
                    $action === 'approve'
                        ? 'Surat masuk disetujui EO Corp Affair.'
                        : 'Surat masuk dikembalikan EO Corp Affair.'
                );

                return $action === 'approve'
                    ? 'Approval EO Corp Affair disetujui.'
                    : 'Approval EO Corp Affair dikembalikan.';
            } elseif ($incomingLetter->status === IncomingLetter::STATUS_WAITING_DIR_APPROVAL) {
                $decisionTargetId = $incomingLetter->followup_submitted_by
                    ?? $incomingLetter->updated_by
                    ?? $incomingLetter->created_by;

                $checkerApproved = Approval::query()
                    ->where('approvable_type', IncomingLetter::class)
                    ->where('approvable_id', $incomingLetter->id)
                    ->where('status', 'approved')
                    ->where('note', 'ilike', 'EO Direktorat Approved%')
                    ->exists();

                if ($action === 'approve') {
                    if (!$checkerApproved && ($isChecker || $isAdmin)) {
                        if ($this->actorAlreadyActed($incomingLetter, $actor, 'EO Direktorat Approved')) {
                            abort(403, 'Approval EO Direktorat sudah diproses.');
                        }
                        Approval::create([
                            'approvable_type' => IncomingLetter::class,
                            'approvable_id' => $incomingLetter->id,
                            'status' => 'approved',
                            'note' => $this->buildApprovalNote('EO Direktorat Approved', $note),
                            'acted_by' => $actor->id,
                            'acted_at' => now(),
                        ]);
                        $this->notifyIncomingDecision(
                            $incomingLetter,
                            $actor,
                            [$decisionTargetId],
                            'Approval Direktorat',
                            'Approval direktorat disetujui (EO Direktorat).'
                        );
                        return 'Approval EO Direktorat disetujui. Menunggu approval DD Direktorat.';
                    }

                    if ($checkerApproved && ($isApprover || $isAdmin)) {
                        if ($this->actorAlreadyActed($incomingLetter, $actor, 'DD Direktorat Approved')) {
                            abort(403, 'Approval DD Direktorat sudah diproses.');
                        }
                        if ($approval) {
                            $approval->update([
                                'status' => 'approved',
                                'note' => $this->buildApprovalNote('DD Direktorat Approved', $note),
                                'acted_by' => $actor->id,
                                'acted_at' => now(),
                            ]);
                        } else {
                            Approval::create([
                                'approvable_type' => IncomingLetter::class,
                                'approvable_id' => $incomingLetter->id,
                                'status' => 'approved',
                                'note' => $this->buildApprovalNote('DD Direktorat Approved', $note),
                                'acted_by' => $actor->id,
                                'acted_at' => now(),
                            ]);
                        }

                        $incomingLetter->update([
                            'status' => $incomingLetter->followup_action === 'response_letter'
                                ? IncomingLetter::STATUS_WAITING_RESPONSE_LETTER
                                : IncomingLetter::STATUS_VERIFIED,
                            'updated_by' => $actor->id,
                        ]);

                        $this->notifyIncomingDecision(
                            $incomingLetter,
                            $actor,
                            [$decisionTargetId],
                            'Approval Direktorat',
                            $incomingLetter->followup_action === 'response_letter'
                                ? 'Approval direktorat disetujui (DD Direktorat). Lanjut proses melalui Surat Keluar.'
                                : 'Approval direktorat disetujui (DD Direktorat).'
                        );

                        return $incomingLetter->followup_action === 'response_letter'
                            ? 'Approval DD Direktorat disetujui. Lanjut proses melalui Surat Keluar.'
                            : 'Approval DD Direktorat disetujui.';
                    }
                }

                if ($action !== 'approve') {
                    $fallbackNote = 'EO+DD Direktorat - Returned';
                    if ($isChecker && !$checkerApproved) {
                        $fallbackNote = 'EO Direktorat Returned';
                    } elseif ($isApprover) {
                        $fallbackNote = 'DD Direktorat Returned';
                    }
                    if ($approval) {
                        $approval->update([
                            'status' => 'returned',
                            'note' => $this->buildApprovalNote($fallbackNote, $note),
                            'acted_by' => $actor->id,
                            'acted_at' => now(),
                        ]);
                    } else {
                        Approval::create([
                            'approvable_type' => IncomingLetter::class,
                            'approvable_id' => $incomingLetter->id,
                            'status' => 'returned',
                            'note' => $this->buildApprovalNote($fallbackNote, $note),
                            'acted_by' => $actor->id,
                            'acted_at' => now(),
                        ]);
                    }

                    $incomingLetter->update([
                        'status' => IncomingLetter::STATUS_RETURNED,
                        'updated_by' => $actor->id,
                    ]);

                    $this->notifyIncomingDecision(
                        $incomingLetter,
                        $actor,
                        [$decisionTargetId],
                        'Approval Direktorat',
                        'Approval direktorat dikembalikan.'
                    );

                    return ($isChecker && !$checkerApproved)
                        ? 'Approval EO Direktorat dikembalikan.'
                        : 'Approval DD Direktorat dikembalikan.';
                }
            }

            if ($action !== 'approve' && $note) {
                Comment::create([
                    'commentable_type' => IncomingLetter::class,
                    'commentable_id' => $incomingLetter->id,
                    'body' => '[RETURN] ' . $note,
                    'created_by' => $actor->id,
                ]);
            }

            return 'Action approval berhasil diproses.';
        });
    }

    public function directorateUpdate(
        IncomingLetter $incomingLetter,
        User $actor,
        $targetDate,
        ?string $followupAction,
        array $followupDetail,
        ?string $followupNote,
        array $evidenceFiles,
        $socialMaterialFile,
        bool $submitForApproval
    ): void {
        DB::transaction(function () use ($incomingLetter, $actor, $targetDate, $followupAction, $followupDetail, $followupNote, $evidenceFiles, $socialMaterialFile, $submitForApproval) {
            // pastiin yang update itu direktorat yang sama
            if ($incomingLetter->target_directorate_id && $actor->directorate_id !== $incomingLetter->target_directorate_id) {
                abort(403, 'Bukan direktorat tujuan surat ini.');
            }
            if ($incomingLetter->authorized_status !== 'authorized') {
                abort(403, 'Surat belum disetujui EO Corp Affair.');
            }

            $incomingLetter->update([
                'target_date' => $targetDate ?? $incomingLetter->target_date,
                'followup_action' => $followupAction,
                'followup_detail' => $followupDetail,
                'followup_note' => $followupNote,
                'status' => IncomingLetter::STATUS_IN_PROGRESS,
                'updated_by' => $actor->id,
            ]);

            // upload bukti penyelesaian
            foreach ($evidenceFiles as $file) {
                $path = $file->store('corsec/incoming/evidence', 'public');

                $att = Attachment::create([
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_name' => basename($path),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'created_by' => $actor->id,
                ]);

                Attachable::create([
                    'attachment_id' => $att->id,
                    'attachable_type' => IncomingLetter::class,
                    'attachable_id' => $incomingLetter->id,
                    'category' => 'evidence',
                    'created_by' => $actor->id,
                ]);
            }

            if ($socialMaterialFile) {
                $path = $socialMaterialFile->store('corsec/incoming/social_material', 'public');

                $att = Attachment::create([
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => $socialMaterialFile->getClientOriginalName(),
                    'file_name' => basename($path),
                    'mime' => $socialMaterialFile->getClientMimeType(),
                    'size' => $socialMaterialFile->getSize(),
                    'created_by' => $actor->id,
                ]);

                Attachable::create([
                    'attachment_id' => $att->id,
                    'attachable_type' => IncomingLetter::class,
                    'attachable_id' => $incomingLetter->id,
                    'category' => 'social_material',
                    'created_by' => $actor->id,
                ]);
            }

            if ($submitForApproval) {
                $incomingLetter->update([
                    'status' => IncomingLetter::STATUS_WAITING_DIR_APPROVAL,
                    'followup_submitted_at' => now(),
                    'followup_submitted_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                Approval::create([
                    'approvable_type' => IncomingLetter::class,
                    'approvable_id' => $incomingLetter->id,
                    'status' => 'pending',
                    'note' => 'Menunggu approval EO dan DD Direktorat',
                ]);

                $directorateId = $incomingLetter->target_directorate_id ?? $actor->directorate_id;
                if ($directorateId) {
                    $checkerIds = User::query()
                        ->where('directorate_id', $directorateId)
                        ->whereHas('roles', function ($query) {
                            $query->where('name', 'checker');
                        })
                        ->pluck('id');

                    if ($checkerIds->isNotEmpty()) {
                        $this->notifyUsers($checkerIds, 'incoming_letter_dir_approval', [
                            'title' => 'Approval Direktorat',
                            'message' => 'Surat masuk menunggu approval direktorat.',
                            'incoming_letter_id' => $incomingLetter->id,
                            'registration_no' => $incomingLetter->registration_no,
                            'subject' => $incomingLetter->subject,
                            'sender' => $incomingLetter->sender,
                            'status' => $incomingLetter->status,
                            'target_directorate_id' => $directorateId,
                            'created_by' => [
                                'id' => $actor->id,
                                'name' => $actor->name,
                            ],
                        ]);
                    }
                }
            }
        });
    }

    public function verifyAction(IncomingLetter $incomingLetter, User $actor, string $action, ?string $note): void
    {
        DB::transaction(function () use ($incomingLetter, $actor, $action, $note) {
            $isAdmin = $actor->hasRole('administrator');
            $isCorpSecretaryValidationActor = $this->isCorpSecretaryValidationActor($actor);
            $normalizedAction = Str::lower(trim($action));
            $validationComment = trim((string) $note);

            if (!$incomingLetter->corp_secretary_validation_requested_at) {
                abort(403, 'Validasi EO Corporate Secretary belum diminta untuk surat masuk ini.');
            }
            if (in_array((string) $incomingLetter->status, [
                IncomingLetter::STATUS_DRAFT,
                IncomingLetter::STATUS_RETURNED,
                IncomingLetter::STATUS_REJECTED,
            ], true)) {
                abort(403, 'Validasi EO Corporate Secretary tidak tersedia pada status ini.');
            }
            if ($incomingLetter->corp_secretary_validated_at) {
                abort(403, 'Validasi EO Corporate Secretary sudah diproses.');
            }

            if (!$isAdmin && !$isCorpSecretaryValidationActor) {
                abort(403, 'Validasi EO Corporate Secretary hanya untuk Executive Officer dari direktorat Corporate Secretary.');
            }

            if (!in_array($normalizedAction, ['validate', 'verify'], true)) {
                abort(422, 'Aksi validasi tidak valid.');
            }

            if ($validationComment === '') {
                abort(422, 'Komentar validasi wajib diisi.');
            }

            $incomingLetter->update([
                'corp_secretary_validated_at' => now(),
                'corp_secretary_validated_by' => $actor->id,
                'corp_secretary_validation_comment' => $validationComment,
                'updated_by' => $actor->id,
            ]);

            Comment::create([
                'commentable_type' => IncomingLetter::class,
                'commentable_id' => $incomingLetter->id,
                'body' => '[VALIDASI EO CORPORATE SECRETARY] ' . $validationComment,
                'created_by' => $actor->id,
            ]);

            $this->markIncomingNotificationsAsRead($incomingLetter->id);

            $this->notifyIncomingDecision(
                $incomingLetter,
                $actor,
                [
                    $incomingLetter->created_by,
                    $incomingLetter->followup_submitted_by,
                ],
                'Validasi EO Corporate Secretary',
                'Surat masuk telah divalidasi oleh EO Corporate Secretary.'
            );
        });
    }

    private function isEoCorpAffairActor(User $actor): bool
    {
        $isChecker = $actor->hasRole('checker');
        $isApprover = $actor->hasRole('approver');
        if (!$isChecker && !$isApprover) {
            return false;
        }

        $directorateCode = (string) config('corsec.eo_corp_affair_directorate_code', '');
        if ($directorateCode === '') {
            return false;
        }

        $actor->loadMissing('directorate');

        return (string) ($actor->directorate?->code ?? '') === $directorateCode;
    }

    private function isCorpSecretaryValidationActor(User $actor): bool
    {
        $actor->loadMissing('directorate', 'position');

        $directorateCode = (string) config('corsec.eo_corp_affair_directorate_code', '');
        $isCorpSecretaryDirectorate = $directorateCode !== ''
            && (string) ($actor->directorate?->code ?? '') === $directorateCode;
        $positionName = Str::lower(trim((string) ($actor->position?->name ?? '')));

        return $isCorpSecretaryDirectorate && $positionName !== '' && Str::contains($positionName, 'executive officer');
    }

    private function buildApprovalNote(string $label, ?string $note): string
    {
        $note = trim((string) $note);
        return $note !== '' ? $label . ' - ' . $note : $label;
    }

    private function actorAlreadyActed(IncomingLetter $incomingLetter, User $actor, string $labelPrefix): bool
    {
        return Approval::query()
            ->where('approvable_type', IncomingLetter::class)
            ->where('approvable_id', $incomingLetter->id)
            ->where('acted_by', $actor->id)
            ->whereIn('status', ['approved', 'returned'])
            ->get()
            ->contains(function ($approval) use ($labelPrefix) {
                return Str::startsWith((string) $approval->note, $labelPrefix);
            });
    }

    private function markIncomingNotificationsAsRead(int|string $incomingLetterId): void
    {
        $query = DB::table('notifications')->whereNull('read_at');
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $query->whereRaw("(data::jsonb ->> 'incoming_letter_id') = ?", [(string) $incomingLetterId]);
        } else {
            $query->where('data->incoming_letter_id', (string) $incomingLetterId);
        }

        $query->update([
            'read_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function notifyIncomingDecision(
        IncomingLetter $incomingLetter,
        User $actor,
        array $userIds,
        string $title,
        string $message
    ): void {
        $targetUserIds = collect($userIds)->filter()->unique()->values();
        if ($targetUserIds->isEmpty()) {
            return;
        }

        $this->notifyUsers($targetUserIds->all(), 'incoming_letter_action', [
            'title' => $title,
            'message' => $message,
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
        ]);
    }

    private function notifyUsers(iterable $userIds, string $type, array $data): void
    {
        CorsecFlowNotification::insertForUsers($userIds, $type, $data);
    }

    private function notifyCorpSecretaryValidationRequired(IncomingLetter $incomingLetter, User $actor): void
    {
        $validatorIds = $this->corpSecretaryValidationUserIds();
        if ($validatorIds->isEmpty()) {
            return;
        }

        $this->notifyUsers($validatorIds, 'incoming_letter_validation', [
            'title' => 'Validasi EO Corporate Secretary',
            'message' => 'Surat masuk menunggu validasi EO Corporate Secretary pada hari yang sama.',
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
        ]);
    }

    private function corpSecretaryValidationUserIds()
    {
        $directorateCode = (string) config('corsec.eo_corp_affair_directorate_code', '');
        if ($directorateCode === '') {
            return collect();
        }

        $directorateId = Directorate::query()
            ->where('code', $directorateCode)
            ->value('id');
        if (!$directorateId) {
            return collect();
        }

        return User::query()
            ->where('directorate_id', $directorateId)
            ->whereHas('position', function ($query) {
                $query->where('name', 'ilike', '%executive officer%');
            })
            ->pluck('id');
    }
}
