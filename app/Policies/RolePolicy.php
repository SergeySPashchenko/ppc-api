<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Role') || $authUser->isGlobalAdmin();
    }

    public function view(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('View:Role') || $authUser->isGlobalAdmin();
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Role') || $authUser->isGlobalAdmin();
    }

    public function update(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Update:Role') || $authUser->isGlobalAdmin();
    }

    public function delete(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Delete:Role') || $authUser->isGlobalAdmin();
    }

    public function restore(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Restore:Role') || $authUser->isGlobalAdmin();
    }

    public function forceDelete(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('ForceDelete:Role') || $authUser->isGlobalAdmin();
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Role') || $authUser->isGlobalAdmin();
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Role') || $authUser->isGlobalAdmin();
    }

    public function replicate(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Replicate:Role') || $authUser->isGlobalAdmin();
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Role') || $authUser->isGlobalAdmin() ;
    }
}
