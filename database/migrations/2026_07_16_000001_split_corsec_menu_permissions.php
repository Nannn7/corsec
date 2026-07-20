<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Usermanagement\Models\Permission;
use Modules\Usermanagement\Models\PermissionGroup;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The old "corsec" permission group covered 5 different menus at once
 * (Letter, Meeting, Work Plan, Reporting, Library), so a role could not be
 * given Read on Letter without also getting Read on all the others.
 *
 * This splits those 5 menus into their own permission groups/permissions,
 * and mirrors whatever a role could already do under "corsec.*" onto all 5
 * new groups — so nobody loses access the moment this deploys. Admins can
 * then go uncheck individual menus per role from the Role edit page.
 *
 * "corsec.authorize" is left untouched: it's a shared "can authorize"
 * ability used by Meeting, Work Plan and the Approval Requests menu itself,
 * not something tied to a single menu.
 */
return new class extends Migration
{
    private const NEW_GROUPS = ['letter', 'meeting', 'workplan', 'report', 'library'];

    private const ACTIONS = ['create', 'read', 'update', 'delete', 'export', 'authorize', 'report', 'restore'];

    // Actions that used to live only under "corsec.*" and get mirrored onto
    // the new groups. "authorize" is excluded on purpose (see class docblock).
    private const MIRRORED_ACTIONS = ['create', 'read', 'update', 'delete', 'export', 'report', 'restore'];

    public function up(): void
    {
        foreach (self::NEW_GROUPS as $groupName) {
            $group = PermissionGroup::withTrashed()->firstOrCreate(
                ['name' => $groupName],
                ['slug' => $groupName]
            );

            if ($group->trashed()) {
                $group->restore();
            }

            foreach (self::ACTIONS as $action) {
                Permission::withTrashed()->updateOrCreate(
                    ['name' => "{$groupName}.{$action}", 'guard_name' => 'web'],
                    ['permission_group_id' => $group->id]
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::all() as $role) {
            foreach (self::MIRRORED_ACTIONS as $action) {
                if (!$role->hasPermissionTo("corsec.{$action}")) {
                    continue;
                }

                foreach (self::NEW_GROUPS as $groupName) {
                    $permissionName = "{$groupName}.{$action}";
                    if (!$role->hasPermissionTo($permissionName)) {
                        $role->givePermissionTo($permissionName);
                    }
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally left non-destructive: dropping these permissions
        // would silently strip access from any role now depending on them.
        // Reverse manually (delete the 5 groups + their permissions) if a
        // rollback is genuinely needed.
    }
};
