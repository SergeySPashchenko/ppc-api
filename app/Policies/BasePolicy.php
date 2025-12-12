<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * Hierarchical permission check: Main Company first, then current level.
     * Prevents lower level restrictions if higher level has edit access (unless admin).
     */
    protected function checkHierarchicalPermission(
        AuthUser $authUser,
        string $permission,
        ?int $teamId = null,
        bool $isEditPermission = false
    ): bool {
        if (! $authUser instanceof User) {
            return false;
        }

        return $authUser->checkHierarchicalPermission($permission, $teamId, $isEditPermission);
    }
}
