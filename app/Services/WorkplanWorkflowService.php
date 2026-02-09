<?php

namespace Modules\Corsec\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Attachable;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Models\WorkProgram;
use Modules\Corsec\Models\WorkProgramItem;
use Modules\Corsec\Models\WorkProgramUpdate;
use Modules\Usermanagement\Models\User;

class WorkplanWorkflowService
{
    public function submitProgram(WorkProgram $program, User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($program, $actor, $note) {
            $pendingApproval = $this->latestPendingApproval($program);
            if ($pendingApproval) {
                abort(422, 'Masih ada approval Workplan yang belum diproses.');
            }

            $program->update([
                'status' => WorkProgram::STATUS_WAITING_DIR_APPROVAL,
                'authorized_status' => 'pending',
                'authorized_at' => null,
                'authorized_by' => null,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => WorkProgram::class,
                'approvable_id' => $program->id,
                'status' => WorkProgramUpdate::STATUS_PENDING,
                'note' => $this->buildApprovalNote('Menunggu approval EO dan DD Direktorat', $note),
            ]);

            $this->notifyDirectorateChecker($program, $actor, 'workplan_dir_approval', 'Workplan menunggu approval direktorat.');
        });
    }

    public function submitProgressUpdate(
        WorkProgramItem $item,
        User $actor,
        string $action,
        ?int $progressPercent,
        ?string $note,
        ?string $revisedTargetDate,
        array $files
    ): void {
        DB::transaction(function () use ($item, $actor, $action, $progressPercent, $note, $revisedTargetDate, $files) {
            $program = $item->program()->lockForUpdate()->firstOrFail();
            if (!$program) {
                abort(404, 'Program kerja tidak ditemukan.');
            }

            if ($program->status === WorkProgram::STATUS_WAITING_DIR_APPROVAL) {
                abort(422, 'Program kerja masih menunggu approval sebelumnya.');
            }

            if (in_array((string) $item->status, [
                WorkProgramItem::STATUS_DONE_ON_TARGET,
                WorkProgramItem::STATUS_DONE_OVER_TARGET,
            ], true)) {
                abort(422, 'Item program kerja sudah selesai.');
            }

            $update = WorkProgramUpdate::create([
                'work_program_item_id' => $item->id,
                'progress_percent' => $progressPercent ?? 0,
                'status' => WorkProgramUpdate::STATUS_PENDING,
                'action' => $action,
                'note' => $note,
                'revised_target_date' => $revisedTargetDate,
                'updated_by' => $actor->id,
                'authorized_status' => 'pending',
                'authorized_at' => null,
                'authorized_by' => null,
            ]);

            $this->attachUpdateFiles($update, $files, $actor);

            $program->update([
                'status' => WorkProgram::STATUS_WAITING_DIR_APPROVAL,
                'authorized_status' => 'pending',
                'authorized_at' => null,
                'authorized_by' => null,
                'updated_by' => $actor->id,
            ]);

            Approval::create([
                'approvable_type' => WorkProgram::class,
                'approvable_id' => $program->id,
                'status' => WorkProgramUpdate::STATUS_PENDING,
                'note' => 'Menunggu approval EO dan DD Direktorat (Update Program Kerja)',
            ]);

            $this->notifyDirectorateChecker($program, $actor, 'workplan_update_dir_approval', 'Update workplan menunggu approval direktorat.');
        });
    }

    public function handleDirectorateApproval(WorkProgram $program, User $actor, string $action, ?string $note = null): void
    {
        DB::transaction(function () use ($program, $actor, $action, $note) {
            $program = WorkProgram::query()->lockForUpdate()->findOrFail($program->id);

            if ($program->status !== WorkProgram::STATUS_WAITING_DIR_APPROVAL) {
                abort(403, 'Status program kerja tidak sesuai untuk approval.');
            }

            $pendingApproval = $this->latestPendingApproval($program);
            if (!$pendingApproval) {
                abort(422, 'Tidak ada approval pending untuk diproses.');
            }

            $isAdmin = $actor->hasRole('administrator');
            $isChecker = $actor->hasRole('checker');
            $isApprover = $actor->hasRole('approver');
            $isSameDirectorate = (int) ($actor->directorate_id ?? 0) === (int) ($program->directorate_id ?? 0);

            if (!$isAdmin && !$isSameDirectorate) {
                abort(403, 'Approval hanya untuk user pada direktorat pemilik program kerja.');
            }

            $checkerApproved = $this->isCheckerApprovedInCurrentRound($program, $pendingApproval->created_at);

            if ($action === 'approve') {
                if (!$checkerApproved && ($isChecker || $isAdmin)) {
                    if ($this->actorAlreadyActedInRound($program, $actor, 'EO Direktorat', $pendingApproval->created_at)) {
                        abort(403, 'Approval EO Direktorat sudah diproses oleh user ini.');
                    }

                    Approval::create([
                        'approvable_type' => WorkProgram::class,
                        'approvable_id' => $program->id,
                        'status' => WorkProgramUpdate::STATUS_APPROVED,
                        'note' => $this->buildApprovalNote('EO Direktorat Approved', $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);

                    $this->notifyProgramOwner($program, $actor, 'Approval EO direktorat untuk program kerja sudah disetujui.');
                    $this->notifyDirectorateApprover($program, $actor, 'workplan_dd_approval', 'Workplan menunggu approval DD direktorat.');
                    return;
                }

                if ($checkerApproved && ($isApprover || $isAdmin)) {
                    if ($this->actorAlreadyActedInRound($program, $actor, 'DD Direktorat', $pendingApproval->created_at)) {
                        abort(403, 'Approval DD Direktorat sudah diproses oleh user ini.');
                    }

                    $pendingApproval->update([
                        'status' => WorkProgramUpdate::STATUS_APPROVED,
                        'note' => $this->buildApprovalNote('DD Direktorat Approved', $note),
                        'acted_by' => $actor->id,
                        'acted_at' => now(),
                    ]);

                    $this->applyPendingUpdates($program, $actor);

                    $program->refresh();
                    $program->loadMissing('items');
                    $program->update([
                        'status' => $this->resolveProgramStatus($program),
                        'authorized_status' => 'authorized',
                        'authorized_at' => now(),
                        'authorized_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);

                    $this->notifyProgramOwner($program, $actor, 'Approval DD direktorat untuk program kerja sudah disetujui.');
                    return;
                }

                abort(403, 'Tahap approval tidak sesuai role user.');
            }

            $fallbackLabel = 'EO+DD Direktorat Returned';
            if (!$checkerApproved && $isChecker) {
                $fallbackLabel = 'EO Direktorat Returned';
            } elseif ($checkerApproved && $isApprover) {
                $fallbackLabel = 'DD Direktorat Returned';
            }

            $pendingApproval->update([
                'status' => WorkProgramUpdate::STATUS_RETURNED,
                'note' => $this->buildApprovalNote($fallbackLabel, $note),
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            WorkProgramUpdate::query()
                ->where('status', WorkProgramUpdate::STATUS_PENDING)
                ->whereHas('item', function ($query) use ($program) {
                    $query->where('work_program_id', $program->id);
                })
                ->update([
                    'status' => WorkProgramUpdate::STATUS_RETURNED,
                    'authorized_status' => 'returned',
                    'authorized_at' => now(),
                    'authorized_by' => $actor->id,
                ]);

            $program->update([
                'status' => WorkProgram::STATUS_RETURNED,
                'authorized_status' => 'returned',
                'authorized_at' => null,
                'authorized_by' => null,
                'updated_by' => $actor->id,
            ]);

            $noteText = trim((string) $note);
            if ($noteText !== '') {
                Comment::create([
                    'commentable_type' => WorkProgram::class,
                    'commentable_id' => $program->id,
                    'body' => '[RETURN WORKPLAN] ' . $noteText,
                    'created_by' => $actor->id,
                ]);
            }

            $this->notifyProgramOwner($program, $actor, 'Program kerja dikembalikan untuk diperbaiki.');
        });
    }

    private function applyPendingUpdates(WorkProgram $program, User $actor): void
    {
        $updates = WorkProgramUpdate::query()
            ->with('item')
            ->where('status', WorkProgramUpdate::STATUS_PENDING)
            ->whereHas('item', function ($query) use ($program) {
                $query->where('work_program_id', $program->id);
            })
            ->lockForUpdate()
            ->get();

        foreach ($updates as $update) {
            $item = $update->item;
            if (!$item) {
                continue;
            }

            if ($update->action === WorkProgramUpdate::ACTION_PROGRESS) {
                $item->update([
                    'status' => $this->resolveProgressStatus($item->target_date),
                    'completed_at' => null,
                ]);
            } elseif ($update->action === WorkProgramUpdate::ACTION_DONE_ON_TARGET) {
                $item->update([
                    'status' => WorkProgramItem::STATUS_DONE_ON_TARGET,
                    'completed_at' => now(),
                ]);
            } elseif ($update->action === WorkProgramUpdate::ACTION_DONE_OVER_TARGET) {
                $item->update([
                    'status' => WorkProgramItem::STATUS_DONE_OVER_TARGET,
                    'completed_at' => now(),
                ]);
            } elseif ($update->action === WorkProgramUpdate::ACTION_REVISION) {
                $targetDate = $update->revised_target_date ?: $item->target_date;
                $item->update([
                    'target_date' => $targetDate,
                    'status' => $this->resolveProgressStatus($targetDate),
                    'completed_at' => null,
                ]);
            }

            $update->update([
                'status' => WorkProgramUpdate::STATUS_APPROVED,
                'authorized_status' => 'authorized',
                'authorized_at' => now(),
                'authorized_by' => $actor->id,
            ]);
        }
    }

    private function resolveProgramStatus(WorkProgram $program): string
    {
        $total = $program->items->count();
        $done = $program->items
            ->whereIn('status', [WorkProgramItem::STATUS_DONE_ON_TARGET, WorkProgramItem::STATUS_DONE_OVER_TARGET])
            ->count();

        if ($total > 0 && $done === $total) {
            return WorkProgram::STATUS_DONE;
        }

        return WorkProgram::STATUS_ACTIVE;
    }

    private function resolveProgressStatus($targetDate): string
    {
        if ($targetDate && now()->greaterThan(\Illuminate\Support\Carbon::parse($targetDate)->endOfDay())) {
            return WorkProgramItem::STATUS_UNDONE;
        }

        return WorkProgramItem::STATUS_PROCESS_ON_TARGET;
    }

    private function latestPendingApproval(WorkProgram $program): ?Approval
    {
        return Approval::query()
            ->where('approvable_type', WorkProgram::class)
            ->where('approvable_id', $program->id)
            ->where('status', WorkProgramUpdate::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    private function isCheckerApprovedInCurrentRound(WorkProgram $program, $roundStartedAt): bool
    {
        return Approval::query()
            ->where('approvable_type', WorkProgram::class)
            ->where('approvable_id', $program->id)
            ->where('status', WorkProgramUpdate::STATUS_APPROVED)
            ->where('created_at', '>=', $roundStartedAt)
            ->where('note', 'ilike', 'EO Direktorat Approved%')
            ->exists();
    }

    private function actorAlreadyActedInRound(WorkProgram $program, User $actor, string $labelPrefix, $roundStartedAt): bool
    {
        return Approval::query()
            ->where('approvable_type', WorkProgram::class)
            ->where('approvable_id', $program->id)
            ->where('acted_by', $actor->id)
            ->where('created_at', '>=', $roundStartedAt)
            ->whereIn('status', [WorkProgramUpdate::STATUS_APPROVED, WorkProgramUpdate::STATUS_RETURNED])
            ->get()
            ->contains(function ($approval) use ($labelPrefix) {
                return Str::startsWith((string) $approval->note, $labelPrefix);
            });
    }

    private function buildApprovalNote(string $label, ?string $note): string
    {
        $note = trim((string) $note);
        return $note !== '' ? $label . ' - ' . $note : $label;
    }

    private function attachUpdateFiles(WorkProgramUpdate $update, array $files, User $actor): void
    {
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('corsec/workplan/update', 'public');

            $attachment = Attachment::create([
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_name' => basename($path),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'created_by' => $actor->id,
            ]);

            Attachable::create([
                'attachment_id' => $attachment->id,
                'attachable_type' => WorkProgramUpdate::class,
                'attachable_id' => $update->id,
                'category' => 'progress_evidence',
                'created_by' => $actor->id,
            ]);
        }
    }

    private function notifyDirectorateChecker(WorkProgram $program, User $actor, string $type, string $message): void
    {
        $directorateId = $program->directorate_id;
        if (!$directorateId) {
            return;
        }

        $checkerIds = User::query()
            ->where('directorate_id', $directorateId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'checker');
            })
            ->pluck('id');

        if ($checkerIds->isEmpty()) {
            return;
        }

        $this->notifyUsers($checkerIds, $type, [
            'title' => 'Approval Program Kerja',
            'message' => $message,
            'work_program_id' => $program->id,
            'work_program_title' => $program->title,
            'status' => $program->status,
            'created_by' => [
                'id' => $actor->id,
                'name' => $actor->name,
            ],
        ]);
    }

    private function notifyDirectorateApprover(WorkProgram $program, User $actor, string $type, string $message): void
    {
        $directorateId = $program->directorate_id;
        if (!$directorateId) {
            return;
        }

        $approverIds = User::query()
            ->where('directorate_id', $directorateId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'approver');
            })
            ->pluck('id');

        if ($approverIds->isEmpty()) {
            return;
        }

        $this->notifyUsers($approverIds, $type, [
            'title' => 'Approval Program Kerja',
            'message' => $message,
            'work_program_id' => $program->id,
            'work_program_title' => $program->title,
            'status' => $program->status,
            'created_by' => [
                'id' => $actor->id,
                'name' => $actor->name,
            ],
        ]);
    }

    private function notifyProgramOwner(WorkProgram $program, User $actor, string $message): void
    {
        if (!$program->created_by) {
            return;
        }

        $this->notifyUsers([$program->created_by], 'workplan_action', [
            'title' => 'Program Kerja',
            'message' => $message,
            'work_program_id' => $program->id,
            'work_program_title' => $program->title,
            'status' => $program->status,
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
        $jsonData = json_encode($data);

        $payload = $ids->map(function ($userId) use ($type, $jsonData, $now) {
            return [
                'id' => (string) Str::uuid(),
                'type' => $type,
                'notifiable_type' => User::class,
                'notifiable_id' => $userId,
                'data' => $jsonData,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::table('notifications')->insert($payload);
    }
}
