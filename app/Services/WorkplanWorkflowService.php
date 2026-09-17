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
use Modules\Corsec\Notifications\CorsecFlowNotification;
use Modules\Corsec\Support\DirectorateApprovalFlow;
use Modules\Usermanagement\Models\Position;
use Modules\Usermanagement\Models\User;

class WorkplanWorkflowService
{
    public function submitProgram(WorkProgram $program, User $actor, ?string $note = null): array
    {
        return DB::transaction(function () use ($program, $actor, $note) {
            $pendingApproval = $this->latestPendingApproval($program);
            if ($pendingApproval) {
                abort(422, 'Masih ada approval Workplan yang belum diproses.');
            }

            $approvalFlow = DirectorateApprovalFlow::forActor($actor);
            $waitingApprovalLabel = $approvalFlow === DirectorateApprovalFlow::DD_ONLY
                ? 'Menunggu approval DD Direktorat'
                : 'Menunggu approval EO dan DD Direktorat';

            if ($approvalFlow === DirectorateApprovalFlow::NONE) {
                $program->loadMissing('items');
                $program->update([
                    'status' => $this->resolveProgramStatus($program),
                    'authorized_status' => 'authorized',
                    'authorized_at' => now(),
                    'authorized_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                $this->notifyProgramOwner($program, $actor, 'Program kerja langsung aktif tanpa approval direktorat karena submitter berposisi Deputy Director.');

                return [
                    'flow' => $approvalFlow,
                    'success_message' => 'Program kerja berhasil disubmit. Karena submitter Deputy Director, data langsung aktif tanpa approval direktorat.',
                ];
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
                'note' => $this->buildApprovalNote($waitingApprovalLabel, $note),
            ]);

            if ($approvalFlow === DirectorateApprovalFlow::DD_ONLY) {
                $this->notifyDirectorateApprover($program, $actor, 'workplan_dd_approval', 'Workplan menunggu approval DD direktorat.');
                return [
                    'flow' => $approvalFlow,
                    'success_message' => 'Program kerja berhasil disubmit untuk approval DD Direktorat.',
                ];
            }

            $this->notifyDirectorateChecker($program, $actor, 'workplan_dir_approval', 'Workplan menunggu approval direktorat.');
            return [
                'flow' => $approvalFlow,
                'success_message' => 'Program kerja berhasil disubmit untuk approval EO + DD Direktorat.',
            ];
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
    ): array {
        return DB::transaction(function () use ($item, $actor, $action, $progressPercent, $note, $revisedTargetDate, $files) {
            $program = $item->program()->lockForUpdate()->firstOrFail();
            if (!$program) {
                abort(404, 'Program kerja tidak ditemukan.');
            }

            $approvalFlow = DirectorateApprovalFlow::forActor($actor);
            $waitingApprovalLabel = $approvalFlow === DirectorateApprovalFlow::DD_ONLY
                ? 'Menunggu approval DD Direktorat (Update Program Kerja)'
                : 'Menunggu approval EO dan DD Direktorat (Update Program Kerja)';

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

            if ($approvalFlow === DirectorateApprovalFlow::NONE) {
                $this->applyUpdateAction($update, $item);

                $update->update([
                    'status' => WorkProgramUpdate::STATUS_APPROVED,
                    'authorized_status' => 'authorized',
                    'authorized_at' => now(),
                    'authorized_by' => $actor->id,
                ]);

                $program->refresh();
                $program->loadMissing('items');
                $program->update([
                    'status' => $this->resolveProgramStatus($program),
                    'authorized_status' => 'authorized',
                    'authorized_at' => now(),
                    'authorized_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                $this->notifyProgramOwner($program, $actor, 'Update program kerja langsung diterapkan tanpa approval direktorat karena submitter berposisi Deputy Director.');

                return [
                    'flow' => $approvalFlow,
                    'success_message' => 'Update program kerja berhasil disubmit. Karena submitter Deputy Director, update langsung diterapkan tanpa approval direktorat.',
                ];
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
                'note' => $waitingApprovalLabel,
            ]);

            if ($approvalFlow === DirectorateApprovalFlow::DD_ONLY) {
                $this->notifyDirectorateApprover($program, $actor, 'workplan_update_dd_approval', 'Update workplan menunggu approval DD direktorat.');
                return [
                    'flow' => $approvalFlow,
                    'success_message' => 'Update program kerja berhasil disubmit untuk approval DD Direktorat.',
                ];
            }

            $this->notifyDirectorateChecker($program, $actor, 'workplan_update_dir_approval', 'Update workplan menunggu approval direktorat.');
            return [
                'flow' => $approvalFlow,
                'success_message' => 'Update program kerja berhasil disubmit untuk approval EO + DD Direktorat.',
            ];
        });
    }

    public function handleDirectorateApproval(WorkProgram $program, User $actor, string $action, ?string $note = null): string
    {
        return DB::transaction(function () use ($program, $actor, $action, $note) {
            $program = WorkProgram::query()->lockForUpdate()->findOrFail($program->id);

            if ($program->status !== WorkProgram::STATUS_WAITING_DIR_APPROVAL) {
                abort(403, 'Status program kerja tidak sesuai untuk approval.');
            }

            $pendingApproval = $this->latestPendingApproval($program);
            if (!$pendingApproval) {
                abort(422, 'Tidak ada approval pending untuk diproses.');
            }

            $isAdmin = $actor->hasRole('administrator');
            $isChecker = $actor->can('workplan.checker_action');
            $isApprover = $actor->can('workplan.approver_action');
            $isApproverDeputyDirector = $isApprover && $this->isDeputyDirector($actor);
            $isSameDirectorate = (int) ($actor->directorate_id ?? 0) === (int) ($program->directorate_id ?? 0);

            if (!$isAdmin && !$isSameDirectorate) {
                abort(403, 'Approval hanya untuk user pada direktorat pemilik program kerja.');
            }

            $requiresCheckerApproval = $this->requiresCheckerApproval($pendingApproval);
            $checkerApproved = $requiresCheckerApproval
                ? $this->isCheckerApprovedInCurrentRound($program, $pendingApproval->created_at)
                : false;

            if ($action === 'approve') {
                if ($requiresCheckerApproval && !$checkerApproved && ($isChecker || $isAdmin)) {
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
                    return 'Approval EO Direktorat disetujui. Menunggu approval DD Direktorat.';
                }

                if ((!$requiresCheckerApproval || $checkerApproved) && ($isApproverDeputyDirector || $isAdmin)) {
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
                    return 'Approval DD Direktorat disetujui.';
                }

                abort(403, 'Tahap approval tidak sesuai role user.');
            }

            if ($requiresCheckerApproval && !$checkerApproved && !$isChecker && !$isAdmin) {
                abort(403, 'Tahap approval tidak sesuai role user.');
            }
            if ((!$requiresCheckerApproval || $checkerApproved) && !$isAdmin && !$isApproverDeputyDirector) {
                abort(403, 'Approval DD Direktorat hanya untuk approver dengan posisi Deputy Director.');
            }

            $fallbackLabel = 'DD Direktorat Returned';
            if ($requiresCheckerApproval && !$checkerApproved) {
                $fallbackLabel = 'EO Direktorat Returned';
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

            return ($requiresCheckerApproval && !$checkerApproved)
                ? 'Approval EO Direktorat dikembalikan.'
                : 'Approval DD Direktorat dikembalikan.';
        });
    }

    public function canViewAllPrograms(User $user): bool
    {
        return $user->hasRole('administrator')
            || $this->isComplianceDirectorate($user)
            || $this->isSkaiDirectorate($user)
            || $this->isCorpSecretaryDirectorate($user);
    }

    public function scopedProgramsQuery(User $user)
    {
        $query = WorkProgram::query();
        if ($this->canViewAllPrograms($user)) {
            return $query;
        }

        $directorateId = $user->directorate_id ?? null;
        return $query->where(function ($builder) use ($user, $directorateId) {
            $builder->where('created_by', $user->id);
            if ($directorateId) {
                $builder->orWhere('directorate_id', $directorateId);
            }
        });
    }

    public function scopedItemsQuery(User $user)
    {
        return WorkProgramItem::query()->whereHas('program', function ($query) use ($user) {
            if ($this->canViewAllPrograms($user)) {
                return;
            }

            $directorateId = $user->directorate_id ?? null;
            $query->where(function ($builder) use ($user, $directorateId) {
                $builder->where('created_by', $user->id);
                if ($directorateId) {
                    $builder->orWhere('directorate_id', $directorateId);
                }
            });
        });
    }

    public function canSeeProgram(WorkProgram $program, User $user): bool
    {
        if ($this->canViewAllPrograms($user)) {
            return true;
        }

        return (int) $program->created_by === (int) $user->id ||
            ((int) ($program->directorate_id ?? 0) === (int) ($user->directorate_id ?? 0));
    }

    public function canEditProgram(WorkProgram $program, User $user): bool
    {
        if ($this->isViewerRole($user)) {
            return false;
        }

        if (!in_array((string) $program->status, [WorkProgram::STATUS_DRAFT, WorkProgram::STATUS_RETURNED], true)) {
            return false;
        }

        if ($user->hasRole('administrator')) {
            return true;
        }

        return (int) $program->created_by === (int) $user->id;
    }

    public function canDeleteProgram(WorkProgram $program, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if (!in_array((string) $program->status, [WorkProgram::STATUS_DRAFT, WorkProgram::STATUS_RETURNED], true)) {
            return false;
        }

        return (int) $program->created_by === (int) $user->id;
    }

    public function canSubmitProgram(WorkProgram $program, User $user): bool
    {
        return $this->canEditProgram($program, $user) &&
            in_array((string) $program->status, [WorkProgram::STATUS_DRAFT, WorkProgram::STATUS_RETURNED], true);
    }

    public function canSubmitUpdate(WorkProgram $program, User $user): bool
    {
        if ($this->isViewerRole($user)) {
            return false;
        }

        if ((string) $program->status !== WorkProgram::STATUS_ACTIVE) {
            return false;
        }

        if ($user->hasRole('administrator')) {
            return true;
        }

        return (int) ($program->directorate_id ?? 0) === (int) ($user->directorate_id ?? 0);
    }

    public function canCheckerApprove(WorkProgram $program, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        return $user->can('workplan.checker_action') &&
            (int) ($program->directorate_id ?? 0) === (int) ($user->directorate_id ?? 0);
    }

    public function canApproverApprove(WorkProgram $program, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        return $user->can('workplan.approver_action') &&
            $this->isDeputyDirector($user) &&
            (int) ($program->directorate_id ?? 0) === (int) ($user->directorate_id ?? 0);
    }

    public function isViewerRole(User $user): bool
    {
        if (!$user->hasRole('viewer')) {
            return false;
        }

        if ($user->hasRole('administrator')) {
            return false;
        }

        $hasWorkplanStageAction = $user->can('workplan.maker_action')
            || $user->can('workplan.checker_action')
            || $user->can('workplan.approver_action');

        return !$hasWorkplanStageAction;
    }

    public function latestPendingProgramApproval(WorkProgram $program): ?Approval
    {
        return $this->latestPendingApproval($program);
    }

    public function resolveApprovalPermissionFlags(WorkProgram $program, User $user, ?Approval $pendingApproval): array
    {
        $requiresCheckerApproval = true;
        $checkerApproved = false;

        if ($pendingApproval) {
            $requiresCheckerApproval = $this->requiresCheckerApproval($pendingApproval);
            if ($requiresCheckerApproval) {
                $checkerApproved = $this->isCheckerApprovedInCurrentRound($program, $pendingApproval->created_at);
            }
        }

        return [
            'requires_checker_approval' => $requiresCheckerApproval,
            'checker_approved' => $checkerApproved,
            'can_checker_approval' => (bool) (
                $pendingApproval &&
                $requiresCheckerApproval &&
                !$checkerApproved &&
                $this->canCheckerApprove($program, $user)
            ),
            'can_approver_approval' => (bool) (
                $pendingApproval &&
                ((!$requiresCheckerApproval) || $checkerApproved) &&
                $this->canApproverApprove($program, $user)
            ),
        ];
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

            $this->applyUpdateAction($update, $item);

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

    private function shouldSkipCheckerForSubmission(User $actor): bool
    {
        return DirectorateApprovalFlow::forActor($actor) === DirectorateApprovalFlow::DD_ONLY;
    }

    private function requiresCheckerApproval(Approval $pendingApproval): bool
    {
        return DirectorateApprovalFlow::requiresCheckerApproval($pendingApproval);
    }

    private function isExecutiveOfficer(User $user): bool
    {
        return DirectorateApprovalFlow::isExecutiveOfficer($user);
    }

    public function isDeputyDirector(User $user): bool
    {
        return DirectorateApprovalFlow::isDeputyDirector($user);
    }

    private function applyUpdateAction(WorkProgramUpdate $update, WorkProgramItem $item): void
    {
        if ($update->action === WorkProgramUpdate::ACTION_PROGRESS) {
            $item->update([
                'status' => $this->resolveProgressStatus($item->target_date),
                'completed_at' => null,
            ]);
            return;
        }

        if ($update->action === WorkProgramUpdate::ACTION_DONE_ON_TARGET) {
            $item->update([
                'status' => WorkProgramItem::STATUS_DONE_ON_TARGET,
                'completed_at' => now(),
            ]);
            return;
        }

        if ($update->action === WorkProgramUpdate::ACTION_DONE_OVER_TARGET) {
            $item->update([
                'status' => WorkProgramItem::STATUS_DONE_OVER_TARGET,
                'completed_at' => now(),
            ]);
            return;
        }

        if ($update->action === WorkProgramUpdate::ACTION_REVISION) {
            $targetDate = $update->revised_target_date ?: $item->target_date;
            $item->update([
                'initial_target_date' => $item->initial_target_date ?: $item->target_date,
                'target_date' => $targetDate,
                'status' => $this->resolveProgressStatus($targetDate),
                'completed_at' => null,
            ]);
        }
    }

    private function isAssistantDirectorOrAbove(User $user): bool
    {
        return $this->resolvedPositionLevel($user) >= 4;
    }

    private function isCorpSecretaryDirectorate(User $user): bool
    {
        $corpCode = (string) config('corsec.eo_corp_affair_directorate_code', '006');
        $user->loadMissing('directorate');

        $directorateCode = (string) ($user->directorate?->code ?? '');
        $directorateName = Str::lower(trim((string) ($user->directorate?->name ?? '')));

        if ($directorateCode !== '' && $corpCode !== '' && $directorateCode === $corpCode) {
            return true;
        }

        return $directorateName !== '' && Str::contains($directorateName, 'corporate secretary');
    }

    private function isComplianceDirectorate(User $user): bool
    {
        $complianceCode = (string) config('corsec.compliance_directorate_code', '035');
        $user->loadMissing('directorate');

        $directorateCode = (string) ($user->directorate?->code ?? '');
        $directorateName = Str::lower(trim((string) ($user->directorate?->name ?? '')));

        if ($directorateCode !== '' && $complianceCode !== '' && $directorateCode === $complianceCode) {
            return true;
        }

        return $directorateName !== ''
            && (
                Str::contains($directorateName, 'kepatuhan')
                || Str::contains($directorateName, 'compliance')
                || Str::contains($directorateName, 'complience')
            );
    }

    private function isSkaiDirectorate(User $user): bool
    {
        $user->loadMissing('directorate');
        $directorateName = Str::lower(trim((string) ($user->directorate?->name ?? '')));

        return $directorateName !== '' && Str::contains($directorateName, 'skai');
    }

    private function resolvedPositionLevel(User $user): int
    {
        $user->loadMissing('position', 'roles');
        if ($user->position?->level) {
            return (int) $user->position->level;
        }

        $positionIds = $user->roles
            ->pluck('position_id')
            ->filter()
            ->unique()
            ->values();
        if ($positionIds->isEmpty()) {
            return 0;
        }

        return (int) Position::query()
            ->whereIn('id', $positionIds)
            ->max('level');
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

            $path = $file->store('corsec/workplan/update', 'private');

            $attachment = Attachment::create([
                'disk' => 'private',
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
            ->whereHas('position', function ($query) {
                $query->where('name', 'ilike', '%deputy director%');
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
        CorsecFlowNotification::insertForUsers($userIds, $type, $data);
    }
}
