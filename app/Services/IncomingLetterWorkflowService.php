<?php

namespace Modules\Corsec\Services;

use Modules\Usermanagement\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\IncomingLetterRoute;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\Attachable;

class IncomingLetterWorkflowService
{
    public function submitToEoCorpAffair(IncomingLetter $incomingLetter, User $actor): void
    {
        DB::transaction(function () use ($incomingLetter, $actor) {
            $incomingLetter->update([
                'status' => IncomingLetter::STATUS_ON_APPROVAL,
                'authorized_status' => 'pending',
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => IncomingLetter::class,
                'approvable_id' => $incomingLetter->id,
                'status' => 'pending',
                'note' => 'Menunggu approval EO Corp Affair',
            ]);

            // TODO: notif ke checker (EO corp affair) -> nanti gue siapin kelas Notification kalau lo mau
        });
    }

    public function circulateToDirectorate(IncomingLetter $incomingLetter, User $actor, int $toDirectorateId, ?string $note): void
    {
        DB::transaction(function () use ($incomingLetter, $actor, $toDirectorateId, $note) {
            $incomingLetter->update([
                'target_directorate_id' => $toDirectorateId,
                'status' => IncomingLetter::STATUS_DISPATCHED,
                'updated_by' => $actor->id,
            ]);

            IncomingLetterRoute::create([
                'incoming_letter_id' => $incomingLetter->id,
                'from_directorate_id' => $actor->directorate_id,
                'to_directorate_id' => $toDirectorateId,
                'from_user_id' => $actor->id,
                'to_user_id' => null,
                'note' => $note,
                'sent_at' => now(),
                'created_by' => $actor->id,
            ]);

            // notif ke direktorat target -> TODO optional
        });
    }

    public function handleApprovalAction(IncomingLetter $incomingLetter, User $actor, string $action, ?string $note): void
    {
        DB::transaction(function () use ($incomingLetter, $actor, $action, $note) {
            // close latest pending approval
            $approval = Approval::query()
                ->where('approvable_type', IncomingLetter::class)
                ->where('approvable_id', $incomingLetter->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($approval) {
                $approval->update([
                    'status' => $action === 'approve' ? 'approved' : 'returned',
                    'note' => $note,
                    'acted_by' => $actor->id,
                    'acted_at' => now(),
                ]);
            }

            if ($incomingLetter->status === IncomingLetter::STATUS_ON_APPROVAL) {
                if ($action === 'approve') {
                    $incomingLetter->update([
                        'authorized_status' => 'authorized',
                        'authorized_at' => now(),
                        'authorized_by' => $actor->id,
                        'status' => $incomingLetter->target_directorate_id ? IncomingLetter::STATUS_DISPATCHED : IncomingLetter::STATUS_DRAFT,
                        'updated_by' => $actor->id,
                    ]);
                } else {
                    $incomingLetter->update([
                        'status' => IncomingLetter::STATUS_RETURNED,
                        'authorized_status' => 'returned',
                        'updated_by' => $actor->id,
                    ]);
                }
            } elseif ($incomingLetter->status === IncomingLetter::STATUS_WAITING_DIR_APPROVAL) {
                if ($action === 'approve') {
                    $incomingLetter->update([
                        'status' => IncomingLetter::STATUS_WAITING_VERIFICATION,
                        'updated_by' => $actor->id,
                    ]);
                } else {
                    $incomingLetter->update([
                        'status' => IncomingLetter::STATUS_RETURNED,
                        'updated_by' => $actor->id,
                    ]);
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
        bool $submitForApproval
    ): void
    {
        DB::transaction(function () use ($incomingLetter, $actor, $targetDate, $followupAction, $followupDetail, $followupNote, $evidenceFiles, $submitForApproval) {
            // pastiin yang update itu direktorat yang sama
            if ($incomingLetter->target_directorate_id && $actor->directorate_id !== $incomingLetter->target_directorate_id) {
                abort(403, 'Bukan direktorat tujuan surat ini.');
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
                    'note' => 'Menunggu approval EO+DD Direktorat',
                ]);
            }
        });
    }

    public function verifyAction(IncomingLetter $incomingLetter, User $actor, string $action, ?string $note): void
    {
        DB::transaction(function () use ($incomingLetter, $actor, $action, $note) {
            if (in_array($action, ['verify', 'approve'], true)) {
                $incomingLetter->update([
                    'status' => IncomingLetter::STATUS_VERIFIED,
                    'updated_by' => $actor->id,
                ]);

                Approval::create([
                    'approvable_type' => IncomingLetter::class,
                    'approvable_id' => $incomingLetter->id,
                    'status' => 'approved',
                    'note' => 'Verifikasi EO Corp Affair: selesai',
                    'acted_by' => $actor->id,
                    'acted_at' => now(),
                ]);
            }

            if (in_array($action, ['return', 'reject'], true)) {
                $incomingLetter->update([
                    'status' => IncomingLetter::STATUS_RETURNED,
                    'updated_by' => $actor->id,
                ]);

                if ($note) {
                    Comment::create([
                        'commentable_type' => IncomingLetter::class,
                        'commentable_id' => $incomingLetter->id,
                        'body' => '[RETURN VERIF] ' . $note,
                        'created_by' => $actor->id,
                    ]);
                }
            }
        });
    }
}
