<?php

namespace Modules\Corsec\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Corsec\Models\Approval;
use Modules\Usermanagement\Models\User;

final class DirectorateApprovalFlow
{
    public const NONE = 'none';
    public const DD_ONLY = 'dd_only';
    public const EO_DD = 'eo_dd';

    public static function forActor(?User $actor): string
    {
        if (!$actor) {
            return self::EO_DD;
        }

        if (self::isDeputyDirector($actor)) {
            return self::NONE;
        }

        if (self::isExecutiveOfficer($actor)) {
            return self::DD_ONLY;
        }

        return self::EO_DD;
    }

    public static function forUsers(Collection $users, ?User $fallbackActor = null): string
    {
        $users = $users
            ->filter(fn($user) => $user instanceof User)
            ->unique('id')
            ->values();

        if ($users->isEmpty()) {
            return self::forActor($fallbackActor);
        }

        if ($users->every(fn(User $user) => self::isDeputyDirector($user))) {
            return self::NONE;
        }

        $allExecutiveOfficerOrDeputyDirector = $users->every(function (User $user) {
            return self::isExecutiveOfficer($user) || self::isDeputyDirector($user);
        });

        return $allExecutiveOfficerOrDeputyDirector
            ? self::DD_ONLY
            : self::EO_DD;
    }

    public static function requiresCheckerApproval(?Approval $pendingApproval, string $ddOnlyPrefix = 'menunggu approval dd direktorat'): bool
    {
        if (!$pendingApproval) {
            return true;
        }

        $pendingNote = Str::lower((string) $pendingApproval->note);

        return !Str::startsWith($pendingNote, Str::lower($ddOnlyPrefix));
    }

    public static function isExecutiveOfficer(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $user->loadMissing('position');
        $positionName = Str::lower(trim((string) ($user->position?->name ?? '')));

        return $positionName !== '' && Str::contains($positionName, 'executive officer');
    }

    public static function isDeputyDirector(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $user->loadMissing('position');
        $positionName = Str::lower(trim((string) ($user->position?->name ?? '')));

        return $positionName !== '' && Str::contains($positionName, 'deputy director');
    }
}
