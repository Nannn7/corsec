<<<<<<< HEAD
<?php

namespace Modules\Corsec\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Corsec\Models\ApprovalRequest;
use Modules\Corsec\Models\LetterType;
use Modules\Usermanagement\Models\Permission;
use Modules\Usermanagement\Models\PermissionGroup;
use Modules\Usermanagement\Models\Role;
use Modules\Usermanagement\Models\User;

class ApprovalRequestService
{
    public function createRequest(
        string $modelClass,
        string $action,
        ?string $targetId,
        array $requestNew,
        ?array $requestOld = null,
        ?string $description = null
    ): ApprovalRequest {
        $userId = Auth::id();
        $approval = ApprovalRequest::create([
            'model' => $modelClass,
            'action' => $action,
            'target_id' => $targetId,
            'request_old' => $requestOld,
            'request_new' => $requestNew,
            'status' => ApprovalRequest::STATUS_PENDING,
            'description' => $description,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $approval->update([
            'checksum' => $approval->generateChecksum(),
        ]);

        return $approval;
    }

    public function approve(ApprovalRequest $approvalRequest, User $actor): void
    {
        DB::transaction(function () use ($approvalRequest, $actor) {
            $modelClass = $approvalRequest->model;
            $requestNew = $approvalRequest->request_new ?? [];

            if ($modelClass === PermissionGroup::class) {
                $this->approvePermissionGroupRequest($approvalRequest, $actor, $requestNew);
                return;
            }

            if ($approvalRequest->action === ApprovalRequest::ACTION_CREATE) {
                if (isset($requestNew['items']) && is_array($requestNew['items'])) {
                    foreach ($requestNew['items'] as $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $payload = $this->filterFillable($modelClass, $item);
                        if (!empty($payload)) {
                            $model = $modelClass::create($payload);
                            $this->applyApprovedRelations($model, $item);
                        }
                    }
                } else {
                    $payload = $this->filterFillable($modelClass, $requestNew);
                    $model = $modelClass::create($payload);
                    $this->applyApprovedRelations($model, $requestNew);
                }
            } elseif ($approvalRequest->action === ApprovalRequest::ACTION_UPDATE) {
                $payload = $this->filterFillable($modelClass, $requestNew);
                $model = $modelClass::query()
                    ->where('id', $approvalRequest->target_id)
                    ->firstOrFail();
                $model->update($payload);
                $this->applyApprovedRelations($model, $requestNew);
            } elseif ($approvalRequest->action === ApprovalRequest::ACTION_DELETE) {
                $query = $modelClass::query()->where('id', $approvalRequest->target_id);

                if (Schema::hasColumn((new $modelClass())->getTable(), 'deleted_by')) {
                    $query->update(['deleted_by' => $actor->id]);
                }
                $query->delete();
            }

            if ($modelClass === LetterType::class) {
                Cache::forget('corsec.letter_types.list');
                Cache::forget('corsec.letter_types.in.list');
                Cache::forget('corsec.letter_types.out.list');
            }

            $approvalRequest->update([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'authorized_at' => now(),
                'authorized_by' => $actor->id,
            ]);
        });
    }

    public function reject(ApprovalRequest $approvalRequest, User $actor, ?string $notes = null): void
    {
        $approvalRequest->update([
            'status' => ApprovalRequest::STATUS_REJECTED,
            'authorized_at' => now(),
            'authorized_by' => $actor->id,
            'review_notes' => $notes,
        ]);
    }

    private function filterFillable(string $modelClass, array $payload): array
    {
        $model = new $modelClass();
        $fillable = $model->getFillable();
        if (empty($fillable)) {
            return $payload;
        }

        return array_intersect_key($payload, array_flip($fillable));
    }

    private function applyApprovedRelations(object $model, array $payload): void
    {
        if ($model instanceof User && array_key_exists('_role_names', $payload)) {
            $model->syncRoles($payload['_role_names'] ?? []);
        }

        if ($model instanceof Role && array_key_exists('_permission_names', $payload)) {
            $model->syncPermissions($payload['_permission_names'] ?? []);
        }
    }

    private function approvePermissionGroupRequest(ApprovalRequest $approvalRequest, User $actor, array $requestNew): void
    {
        $payload = $this->filterFillable(PermissionGroup::class, $requestNew);
        $permissionNames = $requestNew['_permission_names'] ?? [];

        if ($approvalRequest->action === ApprovalRequest::ACTION_CREATE) {
            $group = PermissionGroup::create($payload);
            $this->syncPermissionGroupPermissions($group, $permissionNames);
        } elseif ($approvalRequest->action === ApprovalRequest::ACTION_UPDATE) {
            $group = PermissionGroup::query()
                ->where('id', $approvalRequest->target_id)
                ->firstOrFail();
            $group->update($payload);
            $this->syncPermissionGroupPermissions($group, $permissionNames);
        } elseif ($approvalRequest->action === ApprovalRequest::ACTION_DELETE) {
            PermissionGroup::query()
                ->where('id', $approvalRequest->target_id)
                ->delete();
            Permission::query()
                ->where('permission_group_id', $approvalRequest->target_id)
                ->delete();
        }

        $approvalRequest->update([
            'status' => ApprovalRequest::STATUS_APPROVED,
            'authorized_at' => now(),
            'authorized_by' => $actor->id,
        ]);
    }

    private function syncPermissionGroupPermissions(PermissionGroup $group, array $permissionNames): void
    {
        $permissionNames = array_values(array_filter($permissionNames));
        $existingPermissions = Permission::query()
            ->where('permission_group_id', $group->id)
            ->orderBy('id')
            ->get();

        foreach ($permissionNames as $index => $permissionName) {
            $permission = $existingPermissions->get($index);
            if ($permission) {
                $permission->update([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'permission_group_id' => $group->id,
                ]);
                continue;
            }

            Permission::create([
                'name' => $permissionName,
                'guard_name' => 'web',
                'permission_group_id' => $group->id,
            ]);
        }

        $excessPermissionIds = $existingPermissions
            ->slice(count($permissionNames))
            ->pluck('id');

        if ($excessPermissionIds->isNotEmpty()) {
            Permission::query()->whereIn('id', $excessPermissionIds)->delete();
        }
    }
}
=======
<?php

namespace Modules\Corsec\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Corsec\Models\ApprovalRequest;
use Modules\Corsec\Models\LetterType;
use Modules\Usermanagement\Models\Permission;
use Modules\Usermanagement\Models\PermissionGroup;
use Modules\Usermanagement\Models\Role;
use Modules\Usermanagement\Models\User;

class ApprovalRequestService
{
    public function createRequest(
        string $modelClass,
        string $action,
        ?string $targetId,
        array $requestNew,
        ?array $requestOld = null,
        ?string $description = null
    ): ApprovalRequest {
        $userId = Auth::id();
        $approval = ApprovalRequest::create([
            'model' => $modelClass,
            'action' => $action,
            'target_id' => $targetId,
            'request_old' => $requestOld,
            'request_new' => $requestNew,
            'status' => ApprovalRequest::STATUS_PENDING,
            'description' => $description,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $approval->update([
            'checksum' => $approval->generateChecksum(),
        ]);

        return $approval;
    }

    public function approve(ApprovalRequest $approvalRequest, User $actor): void
    {
        DB::transaction(function () use ($approvalRequest, $actor) {
            $modelClass = $approvalRequest->model;
            $requestNew = $approvalRequest->request_new ?? [];

            if ($modelClass === PermissionGroup::class) {
                $this->approvePermissionGroupRequest($approvalRequest, $actor, $requestNew);
                return;
            }

            if ($approvalRequest->action === ApprovalRequest::ACTION_CREATE) {
                if (isset($requestNew['items']) && is_array($requestNew['items'])) {
                    foreach ($requestNew['items'] as $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $payload = $this->filterFillable($modelClass, $item);
                        if (!empty($payload)) {
                            $model = $modelClass::create($payload);
                            $this->applyApprovedRelations($model, $item);
                        }
                    }
                } else {
                    $payload = $this->filterFillable($modelClass, $requestNew);
                    $model = $modelClass::create($payload);
                    $this->applyApprovedRelations($model, $requestNew);
                }
            } elseif ($approvalRequest->action === ApprovalRequest::ACTION_UPDATE) {
                $payload = $this->filterFillable($modelClass, $requestNew);
                $model = $modelClass::query()
                    ->where('id', $approvalRequest->target_id)
                    ->firstOrFail();
                $model->update($payload);
                $this->applyApprovedRelations($model, $requestNew);
            } elseif ($approvalRequest->action === ApprovalRequest::ACTION_DELETE) {
                $query = $modelClass::query()->where('id', $approvalRequest->target_id);

                if (Schema::hasColumn((new $modelClass())->getTable(), 'deleted_by')) {
                    $query->update(['deleted_by' => $actor->id]);
                }
                $query->delete();
            }

            if ($modelClass === LetterType::class) {
                Cache::forget('corsec.letter_types.list');
                Cache::forget('corsec.letter_types.in.list');
                Cache::forget('corsec.letter_types.out.list');
            }

            $approvalRequest->update([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'authorized_at' => now(),
                'authorized_by' => $actor->id,
            ]);
        });
    }

    public function reject(ApprovalRequest $approvalRequest, User $actor, ?string $notes = null): void
    {
        $approvalRequest->update([
            'status' => ApprovalRequest::STATUS_REJECTED,
            'authorized_at' => now(),
            'authorized_by' => $actor->id,
            'review_notes' => $notes,
        ]);
    }

    private function filterFillable(string $modelClass, array $payload): array
    {
        $model = new $modelClass();
        $fillable = $model->getFillable();
        if (empty($fillable)) {
            return $payload;
        }

        return array_intersect_key($payload, array_flip($fillable));
    }

    private function applyApprovedRelations(object $model, array $payload): void
    {
        if ($model instanceof User && array_key_exists('_role_names', $payload)) {
            $model->syncRoles($payload['_role_names'] ?? []);
        }

        if ($model instanceof Role && array_key_exists('_permission_names', $payload)) {
            $model->syncPermissions($payload['_permission_names'] ?? []);
        }
    }

    private function approvePermissionGroupRequest(ApprovalRequest $approvalRequest, User $actor, array $requestNew): void
    {
        $payload = $this->filterFillable(PermissionGroup::class, $requestNew);
        $permissionNames = $requestNew['_permission_names'] ?? [];

        if ($approvalRequest->action === ApprovalRequest::ACTION_CREATE) {
            $group = PermissionGroup::create($payload);
            $this->syncPermissionGroupPermissions($group, $permissionNames);
        } elseif ($approvalRequest->action === ApprovalRequest::ACTION_UPDATE) {
            $group = PermissionGroup::query()
                ->where('id', $approvalRequest->target_id)
                ->firstOrFail();
            $group->update($payload);
            $this->syncPermissionGroupPermissions($group, $permissionNames);
        } elseif ($approvalRequest->action === ApprovalRequest::ACTION_DELETE) {
            PermissionGroup::query()
                ->where('id', $approvalRequest->target_id)
                ->delete();
            Permission::query()
                ->where('permission_group_id', $approvalRequest->target_id)
                ->delete();
        }

        $approvalRequest->update([
            'status' => ApprovalRequest::STATUS_APPROVED,
            'authorized_at' => now(),
            'authorized_by' => $actor->id,
        ]);
    }

    private function syncPermissionGroupPermissions(PermissionGroup $group, array $permissionNames): void
    {
        $permissionNames = array_values(array_filter($permissionNames));
        $existingPermissions = Permission::query()
            ->where('permission_group_id', $group->id)
            ->orderBy('id')
            ->get();

        foreach ($permissionNames as $index => $permissionName) {
            $permission = $existingPermissions->get($index);
            if ($permission) {
                $permission->update([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'permission_group_id' => $group->id,
                ]);
                continue;
            }

            Permission::create([
                'name' => $permissionName,
                'guard_name' => 'web',
                'permission_group_id' => $group->id,
            ]);
        }

        $excessPermissionIds = $existingPermissions
            ->slice(count($permissionNames))
            ->pluck('id');

        if ($excessPermissionIds->isNotEmpty()) {
            Permission::query()->whereIn('id', $excessPermissionIds)->delete();
        }
    }
}
>>>>>>> a9ffbcb4303082f03373542873e743757c99707a
