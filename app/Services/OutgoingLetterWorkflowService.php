<?php

namespace Modules\Corsec\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Attachment;
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
                    } else {
                        $letter->update([
                            'status' => OutgoingLetter::STATUS_NUMBERING,
                            'updated_by' => $actor->id,
                        ]);
                    }
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
            }
        });
    }

    public function submitComplianceReview(OutgoingLetter $letter, User $actor, ?Attachment $attachment, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $attachment, $note) {
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
        });
    }

    public function handleComplianceApproval(OutgoingLetter $letter, User $actor, string $action, ?string $note): void
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
        });
    }

    public function verifyAction(OutgoingLetter $letter, User $actor, string $action, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $action, $note) {
            if (in_array($action, ['verify', 'approve'], true)) {
                $letter->update([
                    'status' => OutgoingLetter::STATUS_VERIFIED,
                    'updated_by' => $actor->id,
                ]);

                Approval::create([
                    'approvable_type' => OutgoingLetter::class,
                    'approvable_id' => $letter->id,
                    'status' => 'approved',
                    'note' => $this->buildApprovalNote('Verifikasi EO Corp Affair', $note),
                    'acted_by' => $actor->id,
                    'acted_at' => now(),
                ]);
            }

            if (in_array($action, ['return', 'reject'], true)) {
                $letter->update([
                    'status' => OutgoingLetter::STATUS_RETURNED,
                    'updated_by' => $actor->id,
                ]);

                if ($note) {
                    Approval::create([
                        'approvable_type' => OutgoingLetter::class,
                        'approvable_id' => $letter->id,
                        'status' => 'returned',
                        'note' => $this->buildApprovalNote('Verifikasi EO Corp Affair Returned', $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);
                }
            }
        });
    }

    public function uploadFinal(OutgoingLetter $letter, User $actor, Attachment $attachment): void
    {
        $letter->update([
            'final_attachment_id' => $attachment->id,
            'status' => OutgoingLetter::STATUS_FINAL_UPLOADED,
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
}
