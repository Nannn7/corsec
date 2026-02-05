<?php

namespace Modules\Corsec\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\Directorate;
use Modules\Corsec\Models\OutgoingLetter;
use Modules\Usermanagement\Models\User;

class OutgoingLetterWorkflowService
{
    public function submitForDirectorateApproval(OutgoingLetter $letter, User $actor): void
    {
        DB::transaction(function () use ($letter, $actor) {
            $letter->update([
                'status' => OutgoingLetter::STATUS_WAITING_DIR_APPROVAL,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => OutgoingLetter::class,
                'approvable_id' => $letter->id,
                'status' => 'pending',
                'note' => 'Menunggu approval EO dan DD Direktorat',
            ]);

            $directorateId = $letter->requester_directorate_id;
            if ($directorateId) {
                $checkerIds = User::query()
                    ->where('directorate_id', $directorateId)
                    ->whereHas('roles', function ($query) {
                        $query->where('name', 'checker');
                    })
                    ->pluck('id');

                if ($checkerIds->isNotEmpty()) {
                    $now = now();
                    $data = json_encode([
                        'title' => 'Approval Surat Keluar',
                        'message' => 'Surat keluar menunggu approval direktorat.',
                        'outgoing_letter_id' => $letter->id,
                        'registration_no' => $letter->registration_no,
                        'subject' => $letter->subject,
                        'status' => $letter->status,
                        'requester_directorate_id' => $directorateId,
                        'created_by' => [
                            'id' => $actor->id,
                            'name' => $actor->name,
                        ],
                    ]);

                    $payload = $checkerIds->map(function ($checkerId) use ($data, $now) {
                        return [
                            'id' => (string) Str::uuid(),
                            'type' => 'outgoing_letter_dir_approval',
                            'notifiable_type' => User::class,
                            'notifiable_id' => $checkerId,
                            'data' => $data,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->all();

                    DB::table('notifications')->insert($payload);
                }
            }
        });
    }

    public function handleDirectorateApproval(OutgoingLetter $letter, User $actor, string $action, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $action, $note) {
            $approval = $this->latestPendingApproval($letter);

            $isAdmin = $actor->hasRole('administrator');
            $isChecker = $actor->hasRole('checker');
            $isApprover = $actor->hasRole('approver');

            $checkerApproved = Approval::query()
                ->where('approvable_type', OutgoingLetter::class)
                ->where('approvable_id', $letter->id)
                ->where('status', 'approved')
                ->where('note', 'ilike', 'EO Direktorat Approved%')
                ->exists();

            if ($action === 'approve') {
                if (!$checkerApproved && ($isChecker || $isAdmin)) {
                    $this->preventDuplicateAct($letter, $actor, 'EO Direktorat');
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
                        'Approval surat keluar disetujui (EO Direktorat).'
                    );
                    return;
                }

                if ($checkerApproved && ($isApprover || $isAdmin)) {
                    $this->preventDuplicateAct($letter, $actor, 'DD Direktorat');
                    if ($approval) {
                        $approval->update([
                            'status' => 'approved',
                            'note' => $this->buildApprovalNote('DD Direktorat Approved', $note),
                            'acted_by' => $actor->id,
                            'acted_at' => now(),
                        ]);
                    } else {
                        Approval::create([
                            'approvable_type' => OutgoingLetter::class,
                            'approvable_id' => $letter->id,
                            'status' => 'approved',
                            'note' => $this->buildApprovalNote('DD Direktorat Approved', $note),
                            'acted_by' => $actor->id,
                            'acted_at' => now(),
                        ]);
                    }

                    if ($letter->need_compliance_review) {
                        $letter->update([
                            'status' => OutgoingLetter::STATUS_COMPLIANCE_REVIEW,
                            'updated_by' => $actor->id,
                        ]);
                        $this->notifyComplianceReview($letter, $actor);
                    } else {
                        $letter->update([
                            'status' => OutgoingLetter::STATUS_NUMBERING,
                            'updated_by' => $actor->id,
                        ]);
                    }

                    $this->notifyOutgoingDecision(
                        $letter,
                        $actor,
                        'Approval surat keluar disetujui (DD Direktorat).'
                    );
                    return;
                }
            }

            if ($action !== 'approve') {
                $fallbackLabel = 'EO+DD Direktorat Returned';
                if ($isChecker && !$checkerApproved) {
                    $fallbackLabel = 'EO Direktorat Returned';
                } elseif ($isApprover) {
                    $fallbackLabel = 'DD Direktorat Returned';
                }

                if ($approval) {
                    $approval->update([
                        'status' => 'returned',
                        'note' => $this->buildApprovalNote($fallbackLabel, $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);
                } else {
                    Approval::create([
                        'approvable_type' => OutgoingLetter::class,
                        'approvable_id' => $letter->id,
                        'status' => 'returned',
                        'note' => $this->buildApprovalNote($fallbackLabel, $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);
                }

                $letter->update([
                    'status' => OutgoingLetter::STATUS_RETURNED,
                    'updated_by' => $actor->id,
                ]);

                $this->notifyOutgoingDecision(
                    $letter,
                    $actor,
                    'Approval surat keluar dikembalikan.'
                );
            }
        });
    }

    public function submitComplianceReview(OutgoingLetter $letter, User $actor, ?Attachment $attachment, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $attachment, $note) {
            if (!$actor->hasRole('administrator') && !$this->isComplianceStaff($actor)) {
                abort(403, 'Review kepatuhan hanya untuk staff direktorat Kepatuhan.');
            }

            if ($attachment) {
                $letter->update([
                    'compliance_attachment_id' => $attachment->id,
                    'updated_by' => $actor->id,
                ]);
            }

            $letter->update([
                'status' => OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => OutgoingLetter::class,
                'approvable_id' => $letter->id,
                'status' => 'pending',
                'note' => $this->buildApprovalNote('Menunggu approval EO dan DD Kepatuhan', $note),
            ]);

            $checkerIds = $this->getComplianceCheckerIds();
            if ($checkerIds->isNotEmpty()) {
                $this->notifyUsers($checkerIds, 'outgoing_letter_compliance_approval', [
                    'title' => 'Approval Kepatuhan',
                    'message' => 'Surat keluar menunggu approval kepatuhan.',
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
        });
    }

    public function rejectComplianceReview(OutgoingLetter $letter, User $actor, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $note) {
            if (!$actor->hasRole('administrator') && !$this->isComplianceStaff($actor)) {
                abort(403, 'Review kepatuhan hanya untuk staff direktorat Kepatuhan.');
            }

            $letter->update([
                'status' => OutgoingLetter::STATUS_RETURNED,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => OutgoingLetter::class,
                'approvable_id' => $letter->id,
                'status' => 'returned',
                'note' => $this->buildApprovalNote('Review Kepatuhan Returned', $note),
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            $this->notifyOutgoingDecision(
                $letter,
                $actor,
                'Review kepatuhan dikembalikan.'
            );
        });
    }

    public function handleComplianceApproval(OutgoingLetter $letter, User $actor, string $action, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $action, $note) {
            $approval = $this->latestPendingApproval($letter);

            $isAdmin = $actor->hasRole('administrator');
            $isChecker = $actor->hasRole('checker');
            $isApprover = $actor->hasRole('approver');

            if (!$isAdmin && !$this->isComplianceDirectorate($actor)) {
                abort(403, 'Approval kepatuhan hanya untuk direktorat Kepatuhan.');
            }
            if (!$isAdmin && !$isChecker && !$isApprover) {
                abort(403, 'Approval kepatuhan hanya untuk EO/DD direktorat Kepatuhan.');
            }

            $checkerApproved = Approval::query()
                ->where('approvable_type', OutgoingLetter::class)
                ->where('approvable_id', $letter->id)
                ->where('status', 'approved')
                ->where('note', 'ilike', 'EO Kepatuhan Approved%')
                ->exists();

            if ($action === 'approve') {
                if (!$checkerApproved && ($isChecker || $isAdmin)) {
                    $this->preventDuplicateAct($letter, $actor, 'EO Kepatuhan');
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
                        'Approval kepatuhan disetujui (EO Kepatuhan).'
                    );
                    return;
                }

                if ($checkerApproved && ($isApprover || $isAdmin)) {
                    $this->preventDuplicateAct($letter, $actor, 'DD Kepatuhan');
                    if ($approval) {
                        $approval->update([
                            'status' => 'approved',
                            'note' => $this->buildApprovalNote('DD Kepatuhan Approved', $note),
                            'acted_by' => $actor->id,
                            'acted_at' => now(),
                        ]);
                    } else {
                        Approval::create([
                            'approvable_type' => OutgoingLetter::class,
                            'approvable_id' => $letter->id,
                            'status' => 'approved',
                            'note' => $this->buildApprovalNote('DD Kepatuhan Approved', $note),
                            'acted_by' => $actor->id,
                            'acted_at' => now(),
                        ]);
                    }

                    $letter->update([
                        'status' => OutgoingLetter::STATUS_NUMBERING,
                        'updated_by' => $actor->id,
                    ]);

                    $this->notifyOutgoingDecision(
                        $letter,
                        $actor,
                        'Approval kepatuhan disetujui (DD Kepatuhan).'
                    );
                    return;
                }
            }

            if ($action !== 'approve') {
                $fallbackLabel = 'EO+DD Kepatuhan Returned';
                if ($isChecker && !$checkerApproved) {
                    $fallbackLabel = 'EO Kepatuhan Returned';
                } elseif ($isApprover) {
                    $fallbackLabel = 'DD Kepatuhan Returned';
                }

                if ($approval) {
                    $approval->update([
                        'status' => 'returned',
                        'note' => $this->buildApprovalNote($fallbackLabel, $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);
                } else {
                    Approval::create([
                        'approvable_type' => OutgoingLetter::class,
                        'approvable_id' => $letter->id,
                        'status' => 'returned',
                        'note' => $this->buildApprovalNote($fallbackLabel, $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);
                }

                $letter->update([
                    'status' => OutgoingLetter::STATUS_RETURNED,
                    'updated_by' => $actor->id,
                ]);

                $this->notifyOutgoingDecision(
                    $letter,
                    $actor,
                    'Approval kepatuhan dikembalikan.'
                );
            }
        });
    }

    public function setNumberAndSend(OutgoingLetter $letter, User $actor, string $letterNo, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $letterNo, $note) {
            $letter->update([
                'letter_no' => $letterNo,
                'status' => OutgoingLetter::STATUS_WAITING_VERIFICATION,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => OutgoingLetter::class,
                'approvable_id' => $letter->id,
                'status' => 'pending',
                'note' => 'Menunggu approval EO Corporate Secretary',
            ]);

            if ($note) {
                Approval::create([
                    'approvable_type' => OutgoingLetter::class,
                    'approvable_id' => $letter->id,
                    'status' => 'approved',
                    'note' => $this->buildApprovalNote('Corsec Numbered', $note),
                    'acted_by' => $actor->id,
                    'acted_at' => now(),
                ]);
            }

            $checkerIds = $this->getCorpSecretaryCheckerIds();
            if ($checkerIds->isNotEmpty()) {
                $this->notifyUsers($checkerIds, 'outgoing_letter_corpsec_approval', [
                    'title' => 'Approval Corporate Secretary',
                    'message' => 'Surat keluar menunggu approval EO Corporate Secretary.',
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
        });
    }

    public function verifyAction(OutgoingLetter $letter, User $actor, string $action, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $action, $note) {
            if ($letter->status !== OutgoingLetter::STATUS_WAITING_VERIFICATION) {
                abort(403, 'Approval Corporate Secretary tidak sesuai status.');
            }

            if (!$this->isCorpSecretaryDirectorate($actor)) {
                abort(403, 'Approval hanya untuk direktorat Corporate Secretary.');
            }

            $isChecker = $actor->hasRole('checker');

            if (!$isChecker) {
                abort(403, 'Approval hanya untuk role checker dari Corporate Secretary.');
            }

            $checkerApproved = Approval::query()
                ->where('approvable_type', OutgoingLetter::class)
                ->where('approvable_id', $letter->id)
                ->where('status', 'approved')
                ->where('note', 'ilike', 'EO Corp Affair Approved%')
                ->exists();

            $approval = $this->latestPendingApproval($letter);

            if (in_array($action, ['verify', 'approve'], true)) {
                if ($checkerApproved) {
                    abort(403, 'Approval sudah diproses.');
                }

                $this->preventDuplicateAct($letter, $actor, 'EO Corp Affair');
                if ($approval) {
                    $approval->update([
                        'status' => 'approved',
                        'note' => $this->buildApprovalNote('EO Corp Affair Approved', $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);
                } else {
                    Approval::create([
                        'approvable_type' => OutgoingLetter::class,
                        'approvable_id' => $letter->id,
                        'status' => 'approved',
                        'note' => $this->buildApprovalNote('EO Corp Affair Approved', $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);
                }

                $letter->update([
                    'status' => OutgoingLetter::STATUS_FINAL_UPLOADED,
                    'updated_by' => $actor->id,
                ]);

                $this->notifyOutgoingDecision(
                    $letter,
                    $actor,
                    'Approval EO Corporate Secretary disetujui.'
                );

                $staffIds = $this->getCorpSecretaryMakerStaffIds();
                if ($staffIds->isNotEmpty()) {
                    $this->notifyUsers($staffIds, 'outgoing_letter_final_upload', [
                        'title' => 'Upload Final Surat',
                        'message' => 'Surat keluar menunggu upload final.',
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
                $fallbackLabel = 'EO Corp Affair Returned';

                if ($approval) {
                    $approval->update([
                        'status' => 'returned',
                        'note' => $this->buildApprovalNote($fallbackLabel, $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);
                } else {
                    Approval::create([
                        'approvable_type' => OutgoingLetter::class,
                        'approvable_id' => $letter->id,
                        'status' => 'returned',
                        'note' => $this->buildApprovalNote($fallbackLabel, $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);
                }

                $letter->update([
                    'status' => OutgoingLetter::STATUS_RETURNED,
                    'updated_by' => $actor->id,
                ]);

                $this->notifyOutgoingDecision(
                    $letter,
                    $actor,
                    'Approval Corporate Secretary dikembalikan.'
                );
            }
        });
    }

    public function uploadFinal(OutgoingLetter $letter, User $actor, Attachment $attachment): void
    {
        $letter->update([
            'final_attachment_id' => $attachment->id,
            'status' => OutgoingLetter::STATUS_VERIFIED,
            'updated_by' => $actor->id,
        ]);
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

    private function buildApprovalNote(string $label, ?string $note): string
    {
        $note = trim((string) $note);
        return $note !== '' ? $label . ' - ' . $note : $label;
    }

    private function preventDuplicateAct(OutgoingLetter $letter, User $actor, string $labelPrefix): void
    {
        $already = Approval::query()
            ->where('approvable_type', OutgoingLetter::class)
            ->where('approvable_id', $letter->id)
            ->where('acted_by', $actor->id)
            ->get()
            ->contains(function ($approval) use ($labelPrefix) {
                return Str::startsWith((string) $approval->note, $labelPrefix);
            });

        if ($already) {
            abort(403, 'Approval sudah diproses.');
        }
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

    private function notifyComplianceReview(OutgoingLetter $letter, User $actor): void
    {
        $staffIds = $this->getComplianceStaffIds();
        if ($staffIds->isEmpty()) {
            return;
        }

        $this->notifyUsers($staffIds, 'outgoing_letter_compliance_review', [
            'title' => 'Review Kepatuhan',
            'message' => 'Surat keluar membutuhkan review kepatuhan.',
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
        $ids = collect($userIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $now = now();
        $payloadData = json_encode($data);

        $payload = $ids->map(function ($userId) use ($type, $payloadData, $now) {
            return [
                'id' => (string) Str::uuid(),
                'type' => $type,
                'notifiable_type' => User::class,
                'notifiable_id' => $userId,
                'data' => $payloadData,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::table('notifications')->insert($payload);
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
            return Str::contains($normalized, 'compliance') || Str::contains($normalized, 'kepatuhan');
        }

        return false;
    }

    private function isComplianceStaff(User $user): bool
    {
        if (!$this->isComplianceDirectorate($user)) {
            return false;
        }

        $user->loadMissing('position');
        $positionName = Str::lower((string) ($user->position?->name ?? ''));

        return $positionName !== '' && Str::contains($positionName, 'staff');
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
            ->where('name', 'ilike', '%compliance%')
            ->orWhere('name', 'ilike', '%kepatuhan%')
            ->value('id');
    }

    private function getComplianceStaffIds()
    {
        $directorateId = $this->getComplianceDirectorateId();
        if (!$directorateId) {
            return collect();
        }

        return User::query()
            ->where('directorate_id', $directorateId)
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

    private function getCorpSecretaryMakerStaffIds()
    {
        $directorateId = $this->getCorpSecretaryDirectorateId();
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
}
