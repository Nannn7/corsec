<?php

namespace Modules\Corsec\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\MeetingDecision;
use Modules\Corsec\Models\OutgoingLetter;
use Modules\Corsec\Models\WorkProgramItem;
use Modules\Corsec\Support\DirectorateApprovalFlow;
use Modules\Usermanagement\Models\Position;
use Modules\Usermanagement\Models\User;

class CorsecPermissionService
{
    public function hasOperationalRole(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole('administrator') || $user->hasRole('maker') || $user->hasRole('checker') || $user->hasRole('approver');
    }

    public function isViewerRole(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole('viewer') && !$this->hasOperationalRole($user);
    }

    public function isExecutiveOfficer(?User $user): bool
    {
        $positionName = $this->normalizedPositionName($user);

        return $positionName !== '' && Str::contains($positionName, 'executive officer');
    }

    public function isDeputyDirector(?User $user): bool
    {
        $positionName = $this->normalizedPositionName($user);

        return $positionName !== '' && Str::contains($positionName, 'deputy director');
    }

    public function isAssistantDirectorOrAbove(?User $user): bool
    {
        return $this->resolvedPositionLevel($user) >= 4;
    }

    public function isStaffPosition(?User $user): bool
    {
        $positionName = $this->normalizedPositionName($user);

        return $positionName !== '' && Str::contains($positionName, 'staff');
    }

    public function isSekretariatDireksi(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $sekretarisDireksiCode = (string) config('corsec.sekretaris_direksi_directorate_code', '');
        if ($sekretarisDireksiCode === '') {
            return false;
        }

        $user->loadMissing('directorate');

        return (string) ($user->directorate?->code ?? '') === $sekretarisDireksiCode;
    }

    public function isCorpSecretaryDirectorate(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $eoDirectorateCode = (string) config('corsec.eo_corp_affair_directorate_code', '');

        $user->loadMissing('directorate');
        $directorateCode = (string) ($user->directorate?->code ?? '');
        $directorateName = Str::lower((string) ($user->directorate?->name ?? ''));

        if ($directorateCode !== '' && $eoDirectorateCode !== '' && $directorateCode === $eoDirectorateCode) {
            return true;
        }

        return $directorateName !== '' && Str::contains($directorateName, 'corporate secretary');
    }

    public function isComplianceDirectorate(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $complianceCode = (string) config('corsec.compliance_directorate_code', '');

        $user->loadMissing('directorate');
        $directorateCode = (string) ($user->directorate?->code ?? '');
        $directorateName = Str::lower((string) ($user->directorate?->name ?? ''));

        if ($directorateCode !== '' && $complianceCode !== '' && $directorateCode === $complianceCode) {
            return true;
        }

        return $directorateName !== '' && (
            Str::contains($directorateName, 'kepatuhan')
            || Str::contains($directorateName, 'compliance')
            || Str::contains($directorateName, 'complience')
        );
    }

    public function isSkaiDirectorate(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $user->loadMissing('directorate');
        $directorateName = Str::lower((string) ($user->directorate?->name ?? ''));

        return $directorateName !== '' && Str::contains($directorateName, 'skai');
    }

    public function isAllCorsecDataDirectorate(?User $user): bool
    {
        return $this->isComplianceDirectorate($user)
            || $this->isSkaiDirectorate($user)
            || $this->isCorpSecretaryDirectorate($user);
    }

    public function isEoCorpAffairActor(?User $user): bool
    {
        if (!$user || !$user->hasRole(['checker', 'approver'])) {
            return false;
        }

        return $this->isCorpSecretaryDirectorate($user);
    }

    public function isCorpSecretaryValidationActor(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->isCorpSecretaryDirectorate($user) && $this->isExecutiveOfficer($user);
    }

    public function canViewAllCorsec(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole('administrator')
            || $this->isAllCorsecDataDirectorate($user);
    }

    public function canAddDirectorNote(?User $user): bool
    {
        if (!$user || !(bool) ($user?->can('corsec.read') ?? false)) {
            return false;
        }

        return $user->hasRole('administrator')
            || $this->isAssistantDirectorOrAbove($user)
            || $this->isSekretariatDireksi($user)
            || $this->isCorpSecretaryDirectorate($user);
    }

    public function canCorsecUpdateAction(?User $user): bool
    {
        return (bool) ($user?->can('corsec.update') ?? false) && !$this->isViewerRole($user);
    }

    public function canCorsecCreateOrUpdateAction(?User $user): bool
    {
        return ((bool) ($user?->can('corsec.create') ?? false) || (bool) ($user?->can('corsec.update') ?? false))
            && !$this->isViewerRole($user);
    }

    public function canCreateOutgoing(?User $user): bool
    {
        if (!$user || !$user->can('corsec.create')) {
            return false;
        }

        if ($this->isCorpSecretaryDirectorate($user)) {
            return $user->hasRole('maker');
        }

        return true;
    }

    public function canCreateIncoming(?User $user): bool
    {
        if (!$user || !$user->can('corsec.create')) {
            return false;
        }

        if ($user->hasRole('administrator')) {
            return true;
        }

        return $user->hasRole('maker')
            && $this->isCorpSecretaryDirectorate($user)
            && $this->isStaffPosition($user);
    }

    public function canCreateMeeting(?User $user): bool
    {
        if (!$user || !$user->can('corsec.create')) {
            return false;
        }

        return $user->hasRole('maker')
            && $this->isCorpSecretaryDirectorate($user)
            && $this->isStaffPosition($user);
    }

    public function isRequesterDirectorateMakerStaff(OutgoingLetter $outgoingLetter, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if (!$user->hasRole('maker')) {
            return false;
        }

        if ((int) $outgoingLetter->requester_directorate_id !== (int) $user->directorate_id) {
            return false;
        }

        return $this->isStaffPosition($user);
    }

    public function incomingIndexFlags(?User $user): array
    {
        $canCreateIncoming = $this->canCreateIncoming($user);

        return [
            'is_admin' => (bool) ($user?->hasRole('administrator') ?? false),
            'can_read' => (bool) ($user?->can('corsec.read') ?? false),
            'can_create' => $canCreateIncoming,
            'can_export' => (bool) ($user?->can('corsec.export') ?? false),
            'can_delete' => (bool) ($user?->can('corsec.delete') ?? false),
            'can_edit_action' => (bool) (($user?->hasRole('administrator') ?? false) || $canCreateIncoming),
            'can_comment' => $this->canAddDirectorNote($user),
        ];
    }

    public function incomingDetailFlags(
        IncomingLetter $incomingLetter,
        Collection $approvals,
        ?User $user,
        ?OutgoingLetter $responseOutgoingLetter = null
    ): array {
        $status = (string) ($incomingLetter->status ?? '');

        $isAdmin = (bool) ($user?->hasRole('administrator') ?? false);
        $isChecker = (bool) ($user?->hasRole('checker') ?? false);
        $isApprover = (bool) ($user?->hasRole('approver') ?? false);
        $isEoCorpAffairDirectorate = $this->isCorpSecretaryDirectorate($user);
        $isEoCorpAffairActor = $this->isEoCorpAffairActor($user);
        $isCorpSecretaryValidationActor = $this->isCorpSecretaryValidationActor($user);
        $isExecutiveOfficer = $this->isExecutiveOfficer($user);
        $isSekretariatDireksi = $this->isSekretariatDireksi($user);
        $isEoCorpSecretaryChecker = $isChecker && $isEoCorpAffairDirectorate && $isExecutiveOfficer;

        $actorDirectorateId = (int) ($user?->directorate_id ?? $user?->directorateid ?? 0);
        $isTargetDirectorate = $actorDirectorateId > 0
            && (int) ($incomingLetter->target_directorate_id ?? 0) === $actorDirectorateId;

        $latestPendingDirectorateApproval = $approvals
            ->where('status', 'pending')
            ->filter(function ($approval) {
                return Str::contains(Str::lower((string) ($approval->note ?? '')), 'direktorat');
            })
            ->sortByDesc('id')
            ->first();
        $currentRoundStartedAt = $latestPendingDirectorateApproval?->created_at;
        $requiresCheckerApproval = $latestPendingDirectorateApproval
            ? DirectorateApprovalFlow::requiresCheckerApproval($latestPendingDirectorateApproval)
            : true;

        $checkerApproved = $currentRoundStartedAt && $requiresCheckerApproval
            ? $approvals
                ->where('status', 'approved')
                ->contains(function ($approval) use ($currentRoundStartedAt) {
                    return $this->approvalInRound($approval, $currentRoundStartedAt)
                        && Str::startsWith((string) $approval->note, 'EO Direktorat Approved');
                })
            : false;

        $userHasEoDirApproval = $this->userHasApprovalPrefix($approvals, $user, [
            'EO Direktorat Approved',
            'EO Direktorat Returned',
        ], $currentRoundStartedAt);
        $userHasDdDirApproval = $this->userHasApprovalPrefix($approvals, $user, [
            'DD Direktorat Approved',
            'DD Direktorat Returned',
        ], $currentRoundStartedAt);
        $validationRequested = (bool) $incomingLetter->corp_secretary_validation_requested_at;
        $validationPending = $validationRequested && !$incomingLetter->corp_secretary_validated_at;

        $canDirectorateUpdate = in_array($status, [
            IncomingLetter::STATUS_DISPATCHED,
            IncomingLetter::STATUS_IN_PROGRESS,
            IncomingLetter::STATUS_RETURNED,
        ], true)
            && !$isEoCorpAffairActor
            && ($isAdmin || ($isTargetDirectorate && (string) $incomingLetter->authorized_status === 'authorized'));

        $canCheckerDirApproval = $status === IncomingLetter::STATUS_WAITING_DIR_APPROVAL
            && $requiresCheckerApproval
            && !$checkerApproved
            && ($isAdmin || $isChecker)
            && !$userHasEoDirApproval;

        $canApproverApproval = $status === IncomingLetter::STATUS_WAITING_DIR_APPROVAL
            && ((!$requiresCheckerApproval) || $checkerApproved)
            && ($isAdmin || $isApprover)
            && !$userHasDdDirApproval;

        $canCheckerApproval = $validationPending
            && !in_array($status, [
                IncomingLetter::STATUS_DRAFT,
                IncomingLetter::STATUS_RETURNED,
                IncomingLetter::STATUS_REJECTED,
            ], true)
            && ($isAdmin || $isCorpSecretaryValidationActor);

        $canCreateOutgoingFromIncoming =
            (string) ($incomingLetter->followup_action ?? '') === 'response_letter'
            && $status === IncomingLetter::STATUS_WAITING_RESPONSE_LETTER
            && !$responseOutgoingLetter
            && $this->canCreateOutgoing($user);

        return [
            'can_viewer_note' => $this->canAddDirectorNote($user),
            'can_corsec_update_action' => $this->canCorsecUpdateAction($user),
            'can_directorate_update' => $canDirectorateUpdate,
            'can_checker_dir_approval' => $canCheckerDirApproval,
            'can_approver_approval' => $canApproverApproval,
            'can_checker_approval' => $canCheckerApproval,
            'can_corsec_validation' => $canCheckerApproval,
            'can_add_monitoring' => (bool) ($isAdmin || $isTargetDirectorate || $isEoCorpSecretaryChecker || $isSekretariatDireksi),
            'can_create_outgoing_from_incoming' => $canCreateOutgoingFromIncoming,
        ];
    }

    public function outgoingIndexFlags(?User $user): array
    {
        $isAdmin = (bool) ($user?->hasRole('administrator') ?? false);
        $hasMakerRole = (bool) ($user?->hasRole('maker') ?? false);

        return [
            'is_admin' => $isAdmin,
            'has_operational_role' => $this->hasOperationalRole($user),
            'is_viewer_role' => $this->isViewerRole($user),
            'has_maker_role' => $hasMakerRole,
            'is_staff_position' => $this->isStaffPosition($user),
            'current_user_id' => (int) ($user?->id ?? 0),
            'current_user_directorate_id' => (int) ($user?->directorate_id ?? 0),
            'can_read' => (bool) ($user?->can('corsec.read') ?? false),
            'can_create' => (bool) ($user?->can('corsec.create') ?? false),
            'can_update' => (bool) ($user?->can('corsec.update') ?? false),
            'can_delete' => (bool) ($user?->can('corsec.delete') ?? false),
            'can_export' => (bool) ($user?->can('corsec.export') ?? false),
            'can_create_outgoing' => $this->canCreateOutgoing($user),
            'can_create_or_update' => $this->canCorsecCreateOrUpdateAction($user),
            'can_edit_action' => (bool) ($isAdmin || $this->canCorsecUpdateAction($user)),
            'can_comment' => $this->canAddDirectorNote($user),
        ];
    }

    public function outgoingDetailFlags(OutgoingLetter $outgoingLetter, Collection $approvals, ?User $user): array
    {
        $status = (string) ($outgoingLetter->status ?? '');

        $isAdmin = (bool) ($user?->hasRole('administrator') ?? false);
        $isChecker = (bool) ($user?->hasRole('checker') ?? false);
        $isApprover = (bool) ($user?->hasRole('approver') ?? false);

        $isRequesterDirectorate = $user
            && (int) ($outgoingLetter->requester_directorate_id ?? 0) === (int) ($user->directorate_id ?? 0);

        $isComplianceDirectorate = $this->isComplianceDirectorate($user);

        $roleNames = $this->normalizedRoleNames($user);
        $positionName = $this->normalizedPositionName($user);

        $latestPendingDirectorateApproval = $approvals
            ->where('status', 'pending')
            ->filter(function ($approval) {
                return Str::contains(Str::lower((string) ($approval->note ?? '')), 'direktorat')
                    && !Str::contains(Str::lower((string) ($approval->note ?? '')), 'kepatuhan');
            })
            ->sortByDesc('id')
            ->first();
        $currentDirectorateRoundStartedAt = $latestPendingDirectorateApproval?->created_at;
        $requiresCheckerApprovalDir = $latestPendingDirectorateApproval
            ? DirectorateApprovalFlow::requiresCheckerApproval($latestPendingDirectorateApproval)
            : true;

        $checkerApprovedDir = $currentDirectorateRoundStartedAt && $requiresCheckerApprovalDir
            ? $approvals
                ->where('status', 'approved')
                ->contains(function ($approval) use ($currentDirectorateRoundStartedAt) {
                    return $this->approvalInRound($approval, $currentDirectorateRoundStartedAt)
                        && Str::startsWith((string) $approval->note, 'EO Direktorat Approved');
                })
            : false;
        $checkerApprovedCompliance = $approvals
            ->where('status', 'approved')
            ->contains(function ($approval) {
                return Str::startsWith((string) $approval->note, 'EO Kepatuhan Approved');
            });

        $userHasDirCheckerAction = $this->userHasApprovalPrefix($approvals, $user, [
            'EO Direktorat Approved',
            'EO Direktorat Returned',
        ], $currentDirectorateRoundStartedAt);
        $userHasDirApproverAction = $this->userHasApprovalPrefix($approvals, $user, [
            'DD Direktorat Approved',
            'DD Direktorat Returned',
        ], $currentDirectorateRoundStartedAt);
        $userHasComplianceCheckerAction = $this->userHasApprovalPrefix($approvals, $user, [
            'EO Kepatuhan Approved',
            'EO Kepatuhan Returned',
        ]);
        $userHasComplianceApproverAction = $this->userHasApprovalPrefix($approvals, $user, [
            'DD Kepatuhan Approved',
            'DD Kepatuhan Returned',
        ]);

        $isComplianceMakerStaff = $isComplianceDirectorate
            && $roleNames->contains(function (string $name) {
                return Str::contains($name, 'maker');
            })
            && $positionName !== ''
            && Str::contains($positionName, 'staff');

        $isRequesterDirectorateMakerStaff = $user
            && (int) ($outgoingLetter->requester_directorate_id ?? 0) === (int) ($user->directorate_id ?? 0)
            && $roleNames->contains(function (string $name) {
                return Str::contains($name, 'maker');
            })
            && $positionName !== ''
            && Str::contains($positionName, 'staff');

        $isRequesterCreator = $user && (int) ($outgoingLetter->created_by ?? 0) === (int) $user->id;

        return [
            'can_corsec_update_action' => $this->canCorsecUpdateAction($user),
            'can_corsec_create_or_update_action' => $this->canCorsecCreateOrUpdateAction($user),
            'can_director_note' => $this->canAddDirectorNote($user),
            'can_edit' => in_array($status, [OutgoingLetter::STATUS_DRAFT, OutgoingLetter::STATUS_RETURNED], true)
                && ($isAdmin || $isRequesterDirectorate),
            'can_dir_checker_approval' => $status === OutgoingLetter::STATUS_WAITING_DIR_APPROVAL
                && $requiresCheckerApprovalDir
                && !$checkerApprovedDir
                && ($isAdmin || ($isRequesterDirectorate && $isChecker))
                && !$userHasDirCheckerAction,
            'can_dir_approver_approval' => $status === OutgoingLetter::STATUS_WAITING_DIR_APPROVAL
                && ((!$requiresCheckerApprovalDir) || $checkerApprovedDir)
                && ($isAdmin || ($isRequesterDirectorate && $isApprover))
                && !$userHasDirApproverAction,
            'can_compliance_review' => $status === OutgoingLetter::STATUS_COMPLIANCE_REVIEW
                && ($isAdmin || $isComplianceMakerStaff),
            'can_compliance_checker_approval' => $status === OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL
                && !$checkerApprovedCompliance
                && ($isAdmin || ($isComplianceDirectorate && $isChecker))
                && !$userHasComplianceCheckerAction,
            'can_compliance_approver_approval' => $status === OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL
                && $checkerApprovedCompliance
                && ($isAdmin || ($isComplianceDirectorate && $isApprover))
                && !$userHasComplianceApproverAction,
            'can_final_upload' => $status === OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD
                && ($isAdmin || $isRequesterDirectorateMakerStaff),
            'can_cancel_request' => in_array($status, [
                OutgoingLetter::STATUS_DRAFT,
                OutgoingLetter::STATUS_RETURNED,
                OutgoingLetter::STATUS_WAITING_DIR_APPROVAL,
                OutgoingLetter::STATUS_COMPLIANCE_REVIEW,
                OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL,
                OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD,
            ], true)
                && ($isAdmin || ($isRequesterDirectorateMakerStaff && $isRequesterCreator)),
            'can_cancel_approval' => $status === OutgoingLetter::STATUS_WAITING_CANCEL_APPROVAL
                && ($isAdmin || ($isRequesterDirectorate && $isChecker)),
            'status_steps' => $this->outgoingStatusSteps($outgoingLetter),
        ];
    }

    public function meetingIndexFlags(?User $user): array
    {
        return [
            'is_admin' => (bool) ($user?->hasRole('administrator') ?? false),
            'actor_id' => (int) ($user?->id ?? 0),
            'can_read' => (bool) ($user?->can('corsec.read') ?? false),
            'can_create' => $this->canCreateMeeting($user),
            'can_export' => (bool) ($user?->can('corsec.export') ?? false),
            'can_delete' => (bool) ($user?->can('corsec.delete') ?? false),
            'can_edit_action' => $this->canCorsecUpdateAction($user),
            'can_comment' => $this->canAddDirectorNote($user),
        ];
    }

    public function meetingDetailFlags(Meeting $meeting, Collection $approvals, ?User $user): array
    {
        $meetingRelations = [
            'participants',
            'agendas',
            'decisions',
        ];
        $meeting->loadMissing($meetingRelations);

        $status = (string) ($meeting->status ?? '');
        $isDirektoratMeeting = Meeting::isDirektoratTypeCode((string) ($meeting->meeting_type ?? ''));
        $responseStatus = (string) ($meeting->directorate_response_status ?? '');
        $hasOnScheduleResponse = $responseStatus === Meeting::RESPONSE_ON_SCHEDULE;
        $isCancelledByDirektorat = $responseStatus === Meeting::RESPONSE_CANCEL
            || $status === Meeting::STATUS_CANCELLED_DIREKTORAT;
        $isClosedNotConducted = $status === Meeting::STATUS_CLOSED_NOT_CONDUCTED;

        $isAdmin = (bool) ($user?->hasRole('administrator') ?? false);
        $isChecker = (bool) ($user?->hasRole('checker') ?? false);
        $isApprover = (bool) ($user?->hasRole('approver') ?? false);

        $actorUserId = (int) ($user?->id ?? 0);
        $actorDirectorateId = (int) ($user?->directorate_id ?? 0);

        $targetDirectorateIds = $meeting->participants
            ->pluck('directorate_id')
            ->merge($meeting->agendas->pluck('owner_directorate_id'))
            ->merge($meeting->decisions->pluck('owner_directorate_id'))
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $isAssignedUser = $meeting->participants->contains(function ($participant) use ($actorUserId) {
            return (int) ($participant->user_id ?? 0) === $actorUserId;
        }) || $meeting->agendas->contains(function ($agenda) use ($actorUserId) {
            return (int) ($agenda->pic_user_id ?? 0) === $actorUserId;
        }) || $meeting->decisions->contains(function ($decision) use ($actorUserId) {
            return (int) ($decision->pic_user_id ?? 0) === $actorUserId;
        });

        $canDirectorateActor = $isAdmin
            || $isAssignedUser
            || ($actorDirectorateId > 0 && $targetDirectorateIds->contains($actorDirectorateId));

        if ($isDirektoratMeeting) {
            $canDirectorateActor = $isAdmin || $isAssignedUser;
        }

        $isCorsecDirectorate = $this->isCorpSecretaryDirectorate($user);
        $isExecutiveOfficer = $this->isExecutiveOfficer($user);
        $isDeputyDirector = $this->isDeputyDirector($user);

        // Approval EO/DD direktorat mengikuti lingkup direktorat target rapat,
        // tidak harus user PIC langsung yang assigned.
        $canDirectorateApprovalActor = $isAdmin
            || $isAssignedUser
            || ($actorDirectorateId > 0 && $targetDirectorateIds->contains($actorDirectorateId));
        $canPreparationCoordinationActor = $isDirektoratMeeting
            ? $canDirectorateActor
            : (
                $actorDirectorateId > 0
                && !$isCorsecDirectorate
                && $targetDirectorateIds->contains($actorDirectorateId)
            );

        $canEdit = ($isAdmin || (int) ($meeting->created_by ?? 0) === $actorUserId)
            && in_array($status, [
                Meeting::STATUS_DRAFT,
                Meeting::STATUS_RETURNED_BY_CORSEC,
                Meeting::STATUS_RETURNED_BY_DIREKTORAT,
            ], true);

        $userHasCorsecAction = $this->userHasApprovalPrefix($approvals, $user, [
            'Corporate Secretary Approved',
            'Corporate Secretary Returned',
            'EO Corp Affair Approved',
            'EO Corp Affair Returned',
        ]);

        $latestPendingDirectorateApproval = $approvals
            ->where('status', 'pending')
            ->filter(function ($approval) {
                return Str::contains(Str::lower((string) $approval->note), 'direktorat');
            })
            ->sortByDesc('id')
            ->first();

        $currentRoundStartedAt = $latestPendingDirectorateApproval?->created_at;
        $requiresCheckerApproval = true;
        $checkerApprovedInRound = false;
        $hasActedCheckerInRound = false;
        $hasActedApproverInRound = false;

        if ($currentRoundStartedAt && $latestPendingDirectorateApproval) {
            $requiresCheckerApproval = $this->meetingApprovalRequiresChecker($latestPendingDirectorateApproval);
            $checkerApprovedInRound = $requiresCheckerApproval
                ? $approvals
                    ->where('status', 'approved')
                    ->contains(function ($approval) use ($currentRoundStartedAt) {
                        return $this->approvalInRound($approval, $currentRoundStartedAt)
                            && Str::startsWith((string) $approval->note, 'EO Direktorat Approved');
                    })
                : false;

            if ($actorUserId > 0) {
                $hasActedCheckerInRound = $approvals
                    ->where('acted_by', $actorUserId)
                    ->whereIn('status', ['approved', 'returned'])
                    ->contains(function ($approval) use ($currentRoundStartedAt) {
                        return $this->approvalInRound($approval, $currentRoundStartedAt)
                            && (Str::startsWith((string) $approval->note, 'EO Direktorat Approved')
                                || Str::startsWith((string) $approval->note, 'EO Direktorat Returned'));
                    });

                $hasActedApproverInRound = $approvals
                    ->where('acted_by', $actorUserId)
                    ->whereIn('status', ['approved', 'returned'])
                    ->contains(function ($approval) use ($currentRoundStartedAt) {
                        return $this->approvalInRound($approval, $currentRoundStartedAt)
                            && (Str::startsWith((string) $approval->note, 'DD Direktorat Approved')
                                || Str::startsWith((string) $approval->note, 'DD Direktorat Returned'));
                    });
            }
        }

        $canManageMinutes = $isDirektoratMeeting
            ? ($isAdmin || $isAssignedUser)
            : ($isAdmin || $isCorsecDirectorate || (int) ($meeting->created_by ?? 0) === $actorUserId);

        $updatableDecisionIds = $meeting->decisions
            ->filter(function (MeetingDecision $decision) use ($isAdmin, $actorUserId, $actorDirectorateId, $canDirectorateActor) {
                if ($isAdmin) {
                    return true;
                }

                $picUserId = (int) ($decision->pic_user_id ?? 0);
                if ($picUserId > 0) {
                    return $picUserId === $actorUserId;
                }

                $ownerDirectorateId = (int) ($decision->owner_directorate_id ?? 0);
                if ($ownerDirectorateId > 0 && $actorDirectorateId > 0) {
                    return $ownerDirectorateId === $actorDirectorateId;
                }

                return $canDirectorateActor;
            })
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
        $hasUpdatableDecision = !empty($updatableDecisionIds);

        $allDecisionsDone = $meeting->decisions->every(function ($decision) {
                return in_array((string) ($decision->status ?? ''), [
                    MeetingDecision::STATUS_CONTINUOUS,
                    MeetingDecision::STATUS_DONE,
                    MeetingDecision::STATUS_DROPPED,
                ], true);
            });

        return [
            'can_corsec_update_action' => $this->canCorsecUpdateAction($user),
            'can_director_note' => $this->canAddDirectorNote($user),
            'can_edit' => $canEdit,
            'can_submit_plan' => $canEdit,
            'can_corsec_approval' => $status === Meeting::STATUS_WAITING_CORSEC_APPROVAL
                && ($isAdmin || ($isChecker && $isCorsecDirectorate))
                && !$userHasCorsecAction,
            'can_mark_pending_direktorat' => !$isDirektoratMeeting
                && ($isAdmin || $canPreparationCoordinationActor)
                && in_array($status, [Meeting::STATUS_JADWAL_TERKIRIM, Meeting::STATUS_RETURNED_BY_DIREKTORAT], true),
            'can_directorate_response' => $isDirektoratMeeting
                && $meeting->isAwaitingDirectorateResponse()
                && $canDirectorateActor
                && !$isClosedNotConducted,
            'can_directorate_submit' => ($isAdmin || $canPreparationCoordinationActor)
                && in_array($status, [
                    Meeting::STATUS_JADWAL_TERKIRIM,
                    Meeting::STATUS_PENDING_DIREKTORAT,
                    Meeting::STATUS_RETURNED_BY_DIREKTORAT,
                ], true)
                && (!$isDirektoratMeeting || ($hasOnScheduleResponse && !$isCancelledByDirektorat && !$isClosedNotConducted)),
            'can_directorate_checker_approval' => $status === Meeting::STATUS_WAITING_DIREKTORAT_APPROVAL
                && $requiresCheckerApproval
                && !$checkerApprovedInRound
                && ($isAdmin || ($isChecker && $isExecutiveOfficer && $canDirectorateApprovalActor))
                && !$hasActedCheckerInRound,
            'can_directorate_approver_approval' => $status === Meeting::STATUS_WAITING_DIREKTORAT_APPROVAL
                && (!$requiresCheckerApproval || $checkerApprovedInRound)
                && ($isAdmin || ($isApprover && $isDeputyDirector && $canDirectorateApprovalActor))
                && !$hasActedApproverInRound,
            'can_save_minutes' => $canManageMinutes
                && in_array($status, [Meeting::STATUS_DATA_TERKIRIM, Meeting::STATUS_PROSES_PEMBUATAN_NOTULEN], true),
            'can_finalize_minutes' => $canManageMinutes
                && in_array($status, [Meeting::STATUS_PROSES_PEMBUATAN_NOTULEN, Meeting::STATUS_PROSES_SIRKULASI_TANDATANGAN], true),
            'can_input_followup' => in_array($status, [Meeting::STATUS_NOTULEN_FINAL, Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT], true)
                && ($canManageMinutes || $hasUpdatableDecision),
            'can_complete_followup' => $canManageMinutes
                && in_array($status, [Meeting::STATUS_NOTULEN_FINAL, Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT], true)
                && $allDecisionsDone,
            'updatable_decision_ids' => $updatableDecisionIds,
        ];
    }

    public function masterDataFlags(?User $user, string $abilityPrefix): array
    {
        return [
            'can_read' => (bool) ($user?->can($abilityPrefix . '.read') ?? false),
            'can_create' => (bool) ($user?->can($abilityPrefix . '.create') ?? false),
            'can_update' => (bool) ($user?->can($abilityPrefix . '.update') ?? false),
            'can_delete' => (bool) ($user?->can($abilityPrefix . '.delete') ?? false),
            'can_export' => (bool) ($user?->can($abilityPrefix . '.export') ?? false),
        ];
    }

    public function canAccessDashboard(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole(['maker', 'checker', 'approver', 'administrator', 'viewer'])
            || $user->can('corsec.read')
            || $user->can('usermanagement.read');
    }

    public function dashboardCounts(User $user): array
    {
        return Cache::remember($this->dashboardCountsCacheKey($user), now()->addSeconds(30), function () use ($user) {
            $incomingOpen = IncomingLetter::query()
                ->whereNotIn('status', [
                    IncomingLetter::STATUS_VERIFIED,
                    IncomingLetter::STATUS_REJECTED,
                    IncomingLetter::STATUS_RETURNED,
                ]);
            $this->scopeIncomingDashboardVisibility($incomingOpen, $user);
            $incomingOpen = $incomingOpen->count();

            $outgoingOpen = OutgoingLetter::query()
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhereNotIn('status', ['done', 'completed', 'sent', 'verified', OutgoingLetter::STATUS_CANCELLED]);
                })
                ->where(function ($query) {
                    $query->whereNull('authorized_status')
                        ->orWhere('authorized_status', '!=', 'cancelled');
                })
                ->whereNull('cancelled_at');
            $this->scopeOutgoingDashboardVisibility($outgoingOpen, $user);
            $outgoingOpen = $outgoingOpen->count();

            $meetingOpenQuery = Meeting::query()
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhereNotIn('status', [
                            'done',
                            'completed',
                            'closed',
                            'verified',
                            Meeting::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT,
                            Meeting::STATUS_CANCELLED_DIREKTORAT,
                        ]);
                });

            $this->scopeMeetingDashboardVisibility($meetingOpenQuery, $user);

            $meetingOpen = $meetingOpenQuery->count();

            $workplanOpen = WorkProgramItem::query()
                ->whereHas('program', function ($query) {
                    $query->whereNull('deleted_at');
                })
                ->whereNotIn('status', [
                    WorkProgramItem::STATUS_DONE_ON_TARGET,
                    WorkProgramItem::STATUS_DONE_OVER_TARGET,
                ]);
            $this->scopeWorkplanDashboardVisibility($workplanOpen, $user);
            $workplanOpen = $workplanOpen->count();

            return [
                'incomingOpen' => $incomingOpen,
                'outgoingOpen' => $outgoingOpen,
                'meetingOpen' => $meetingOpen,
                'workplanOpen' => $workplanOpen,
            ];
        });
    }

    public function dashboardOverviewData(array $counts): array
    {
        $cards = collect([
            [
                'key' => 'incoming',
                'title' => 'Incoming Letter',
                'description' => 'Surat masuk yang belum mencapai status final.',
                'count' => (int) ($counts['incomingOpen'] ?? 0),
                'route' => route('letter.incoming.index'),
                'accent' => '#0ea5e9',
                'action' => 'Buka Incoming Letter',
            ],
            [
                'key' => 'outgoing',
                'title' => 'Outgoing Letter',
                'description' => 'Surat keluar yang masih butuh tindak lanjut.',
                'count' => (int) ($counts['outgoingOpen'] ?? 0),
                'route' => route('letter.outgoing.index'),
                'accent' => '#f97316',
                'action' => 'Buka Outgoing Letter',
            ],
            [
                'key' => 'meeting',
                'title' => 'Meeting',
                'description' => 'Meeting aktif yang belum selesai.',
                'count' => (int) ($counts['meetingOpen'] ?? 0),
                'route' => route('meeting.index'),
                'accent' => '#6366f1',
                'action' => 'Buka Meeting',
            ],
            [
                'key' => 'workplan',
                'title' => 'Work Plan',
                'description' => 'Item work plan yang belum closed.',
                'count' => (int) ($counts['workplanOpen'] ?? 0),
                'route' => route('workplan.index'),
                'accent' => '#14b8a6',
                'action' => 'Buka Work Plan',
            ],
        ]);

        $totalOpen = (int) $cards->sum('count');
        $attentionServices = (int) $cards->where('count', '>', 0)->count();
        $clearServices = (int) $cards->where('count', 0)->count();
        $serviceTotal = max($cards->count(), 1);
        $healthScore = (int) round(($clearServices / $serviceTotal) * 100);
        $maxCount = max((int) $cards->max('count'), 1);
        $dominant = $cards->sortByDesc('count')->first() ?? [
            'title' => '-',
            'count' => 0,
        ];

        return [
            'cards' => $cards,
            'totalOpen' => $totalOpen,
            'attentionServices' => $attentionServices,
            'clearServices' => $clearServices,
            'serviceTotal' => $serviceTotal,
            'healthScore' => $healthScore,
            'maxCount' => $maxCount,
            'dominant' => $dominant,
        ];
    }

    private function normalizedPositionName(?User $user): string
    {
        if (!$user) {
            return '';
        }

        $user->loadMissing('position', 'roles');
        $positionName = (string) ($user->position?->name ?? '');
        if ($positionName !== '') {
            return Str::lower(trim($positionName));
        }

        $positionIds = $user->roles
            ->pluck('position_id')
            ->filter()
            ->unique()
            ->values();
        if ($positionIds->isEmpty()) {
            return '';
        }

        $fallbackPositionName = (string) Position::query()
            ->whereIn('id', $positionIds)
            ->orderByDesc('level')
            ->value('name');

        return Str::lower(trim($fallbackPositionName));
    }

    private function resolvedPositionLevel(?User $user): int
    {
        if (!$user) {
            return 0;
        }

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

    private function normalizedRoleNames(?User $user): Collection
    {
        if (!$user) {
            return collect();
        }

        $user->loadMissing('roles');

        return $user->roles
            ->pluck('name')
            ->map(fn($name) => Str::lower((string) $name));
    }

    private function dashboardCountsCacheKey(User $user): string
    {
        $roleSignature = md5($this->normalizedRoleNames($user)->sort()->implode('|'));

        return sprintf(
            'corsec.dashboard.counts.%d.%d.%s',
            (int) $user->id,
            (int) ($user->directorate_id ?? 0),
            $roleSignature
        );
    }

    private function scopeIncomingDashboardVisibility($query, User $user): void
    {
        if ($this->canViewAllCorsec($user)) {
            return;
        }

        $directorateId = (int) ($user->directorate_id ?? $user->directorateid ?? 0);
        $query->where(function ($builder) use ($user, $directorateId) {
            $builder->where('created_by', (int) $user->id);
            if ($directorateId > 0) {
                $builder->orWhere('target_directorate_id', $directorateId)
                    ->orWhereHas('circulationDirectorates', function ($circulationQuery) use ($directorateId) {
                        $circulationQuery->where('directorate_id', $directorateId);
                    });
            }
        });
    }

    private function scopeOutgoingDashboardVisibility($query, User $user): void
    {
        if ($this->canViewAllCorsec($user)) {
            return;
        }

        $directorateId = (int) ($user->directorate_id ?? $user->directorateid ?? 0);
        $query->where(function ($builder) use ($user, $directorateId) {
            $builder->where('created_by', (int) $user->id);
            if ($directorateId > 0) {
                $builder->orWhere('requester_directorate_id', $directorateId);
            }
        });
    }

    private function scopeMeetingDashboardVisibility($query, User $user): void
    {
        if ($this->canViewAllCorsec($user)) {
            return;
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        $query->where(function ($builder) use ($user, $directorateId) {
            $builder->where('created_by', (int) $user->id)
                ->orWhereHas('participants', function ($participantQuery) use ($user) {
                    $participantQuery->where('user_id', (int) $user->id);
                })
                ->orWhereHas('agendas', function ($agendaQuery) use ($user) {
                    $agendaQuery->where('pic_user_id', (int) $user->id);
                })
                ->orWhereHas('decisions', function ($decisionQuery) use ($user) {
                    $decisionQuery->where('pic_user_id', (int) $user->id);
                });

            if ($directorateId > 0) {
                $builder->orWhereHas('participants', function ($participantQuery) use ($directorateId) {
                    $participantQuery->where('directorate_id', $directorateId);
                })->orWhereHas('agendas', function ($agendaQuery) use ($directorateId) {
                    $agendaQuery->where('owner_directorate_id', $directorateId);
                })->orWhereHas('decisions', function ($decisionQuery) use ($directorateId) {
                    $decisionQuery->where('owner_directorate_id', $directorateId);
                });
            }
        });
    }

    private function scopeWorkplanDashboardVisibility($query, User $user): void
    {
        if ($this->canViewAllCorsec($user)) {
            return;
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        $query->whereHas('program', function ($programQuery) use ($user, $directorateId) {
            $programQuery->where(function ($builder) use ($user, $directorateId) {
                $builder->where('created_by', (int) $user->id);
                if ($directorateId > 0) {
                    $builder->orWhere('directorate_id', $directorateId);
                }
            });
        });
    }

    private function userHasApprovalPrefix(Collection $approvals, ?User $user, array $prefixes, $roundStartedAt = null): bool
    {
        if (!$user) {
            return false;
        }

        return $approvals
            ->where('acted_by', $user->id)
            ->contains(function ($approval) use ($prefixes, $roundStartedAt) {
                if ($roundStartedAt && !$this->approvalInRound($approval, $roundStartedAt)) {
                    return false;
                }
                $note = (string) ($approval->note ?? '');
                foreach ($prefixes as $prefix) {
                    if (Str::startsWith($note, $prefix)) {
                        return true;
                    }
                }

                return false;
            });
    }

    private function outgoingStatusSteps(OutgoingLetter $outgoingLetter): array
    {
        $status = (string) ($outgoingLetter->status ?? '');
        $isResponseLetterFlow = (string) ($outgoingLetter->perihal_type ?? '') === 'tanggapan_surat_masuk';

        if ($isResponseLetterFlow) {
            $statusSteps = [
                OutgoingLetter::STATUS_DRAFT => 'Draft',
                OutgoingLetter::STATUS_WAITING_DIR_APPROVAL => 'Approval Direktorat',
            ];

            if ((bool) ($outgoingLetter->need_compliance_review ?? false)) {
                $statusSteps[OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL] = 'Approval EO dan DD Kepatuhan';
            }

            $statusSteps[OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD] = 'Final Upload';
            $statusSteps[OutgoingLetter::STATUS_WAITING_CANCEL_APPROVAL] = 'Approval Pembatalan EO Direktorat';
            $statusSteps[OutgoingLetter::STATUS_VERIFIED] = 'Done';
            $statusSteps[OutgoingLetter::STATUS_RETURNED] = 'Revisi';
            $statusSteps[OutgoingLetter::STATUS_CANCELLED] = 'Cancelled';

            if ($status === OutgoingLetter::STATUS_COMPLIANCE_REVIEW) {
                $statusSteps[OutgoingLetter::STATUS_COMPLIANCE_REVIEW] = 'Review Kepatuhan (Legacy)';
            }

            return $statusSteps;
        }

        return [
            OutgoingLetter::STATUS_DRAFT => 'Draft',
            OutgoingLetter::STATUS_WAITING_DIR_APPROVAL => 'Approval Direktorat',
            OutgoingLetter::STATUS_COMPLIANCE_REVIEW => 'Review Kepatuhan',
            OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL => 'Approval EO dan DD Kepatuhan',
            OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD => 'Final Upload',
            OutgoingLetter::STATUS_WAITING_CANCEL_APPROVAL => 'Approval Pembatalan EO Direktorat',
            OutgoingLetter::STATUS_VERIFIED => 'Done',
            OutgoingLetter::STATUS_RETURNED => 'Revisi',
            OutgoingLetter::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    private function approvalInRound(object $approval, $roundStartedAt): bool
    {
        return isset($approval->created_at)
            && $approval->created_at
            && $approval->created_at->greaterThanOrEqualTo($roundStartedAt);
    }

    private function meetingApprovalRequiresChecker(object $approval): bool
    {
        return DirectorateApprovalFlow::requiresCheckerApproval($approval);
    }
}
