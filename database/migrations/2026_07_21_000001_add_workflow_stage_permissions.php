<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Usermanagement\Models\Permission;
use Modules\Usermanagement\Models\PermissionGroup;
use Modules\Usermanagement\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Background: CorsecPermissionService, the *WorkflowService classes, and a
 * couple of controllers gate "who can create as Maker", "who can approve as
 * Checker" and "who can approve as Approver" using literal $user->hasRole('maker'
 * /'checker'/'approver') checks. That worked fine while those 3 names were the
 * only roles in the system, but the Role management screen has always allowed
 * creating roles with ANY name + granular per-menu permissions (see the
 * 2026_07_16 migration). The moment staging added a role that isn't literally
 * named "maker"/"checker"/"approver", every hasRole() gate silently returned
 * false for that role's users — e.g. the "Tambah Surat/Meeting" button
 * disappearing for Corporate Secretary directorate users on a new role.
 *
 * This migration adds 3 new granular actions per corsec group (letter,
 * meeting, workplan) that represent a WORKFLOW STAGE capability rather than a
 * role identity: maker_action / checker_action / approver_action. The
 * accompanying code change replaces hasRole('maker'/'checker'/'approver')
 * with $user->can("{group}.{stage}_action") in every place that was gating a
 * maker/checker/approver-only capability (create/originate, final upload,
 * compliance submission, approve/reject cancellation, etc.) so any role -
 * old or new - can be assigned that capability explicitly from the Role edit
 * page, exactly like the other granular permissions.
 *
 * Non-destructive: mirrors the capability onto whichever roles currently
 * hold the literal maker/checker/approver/administrator role, so nothing
 * changes for existing users on deploy. New/renamed roles going forward must
 * have the relevant *_action permission ticked explicitly - same UX as the
 * 2026_07_16 split already trained admins to expect.
 */
return new class extends Migration
{
    private const STAGE_GROUPS = ['letter', 'meeting', 'workplan'];

    private const STAGE_ACTIONS = ['maker_action', 'checker_action', 'approver_action'];

    // Which existing literal role currently plays which stage, per group.
    // Used only to mirror today's behavior onto the new permissions.
    private const ROLE_STAGE_MAP = [
        'maker' => 'maker_action',
        'checker' => 'checker_action',
        'approver' => 'approver_action',
    ];

    public function up(): void
    {
        foreach (self::STAGE_GROUPS as $groupName) {
            $group = PermissionGroup::withTrashed()->firstOrCreate(
                ['name' => $groupName],
                ['slug' => $groupName]
            );

            if ($group->trashed()) {
                $group->restore();
            }

            foreach (self::STAGE_ACTIONS as $action) {
                Permission::withTrashed()->updateOrCreate(
                    ['name' => "{$groupName}.{$action}", 'guard_name' => 'web'],
                    ['permission_group_id' => $group->id]
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::withTrashed()->get() as $role) {
            $roleName = strtolower((string) $role->name);

            if ($roleName === 'administrator') {
                // Administrator already bypasses these checks via hasRole('administrator')
                // in every refactored method, but granting the permissions too keeps
                // `$user->can(...)` truthful for anything that only checks can() directly.
                foreach (self::STAGE_GROUPS as $groupName) {
                    foreach (self::STAGE_ACTIONS as $action) {
                        $permissionName = "{$groupName}.{$action}";
                        if (!$role->hasPermissionTo($permissionName)) {
                            $role->givePermissionTo($permissionName);
                        }
                    }
                }
                continue;
            }

            if (!isset(self::ROLE_STAGE_MAP[$roleName])) {
                continue;
            }

            $action = self::ROLE_STAGE_MAP[$roleName];
            foreach (self::STAGE_GROUPS as $groupName) {
                $permissionName = "{$groupName}.{$action}";
                if (!$role->hasPermissionTo($permissionName)) {
                    $role->givePermissionTo($permissionName);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally non-destructive - see 2026_07_16_000001 for the same
        // rationale. Roll back manually if genuinely needed.
    }
};
