<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use Illuminate\Foundation\Auth\User as AuthUser;

class RolePolicy extends BasePolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return ($this->checkHierarchicalPermission($authUser, 'ViewAny:Role') || $authUser->isMainCompanyAdmin());
    }

    public function view(AuthUser $authUser, Role $role): bool
    {
        return $this->checkHierarchicalPermission($authUser, 'View:Role', $role->team_id) || $authUser->isTeamAdmin($role->team_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->checkHierarchicalPermission($authUser, 'Create:Role') || $authUser->isMainCompanyAdmin();
    }

    public function update(AuthUser $authUser, Role $role): bool
    {
        return $this->checkHierarchicalPermission($authUser, 'Update:Role', $role->team_id, true) || $authUser->isTeamAdmin($role->team_id);
    }

    public function delete(AuthUser $authUser, Role $role): bool
    {
        return $this->checkHierarchicalPermission($authUser, 'Delete:Role', $role->team_id, true) || $authUser->isTeamAdmin($role->team_id);
    }

    public function restore(AuthUser $authUser, Role $role): bool
    {
        return $this->checkHierarchicalPermission($authUser, 'Restore:Role', $role->team_id, true) || $authUser->isTeamAdmin($role->team_id);
    }

    public function forceDelete(AuthUser $authUser, Role $role): bool
    {
        return $this->checkHierarchicalPermission($authUser, 'ForceDelete:Role', $role->team_id, true) || $authUser->isTeamAdmin($role->team_id);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->checkHierarchicalPermission($authUser, 'ForceDeleteAny:Role') || $authUser->isMainCompanyAdmin();
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->checkHierarchicalPermission($authUser, 'RestoreAny:Role') || $authUser->isMainCompanyAdmin();
    }

    public function replicate(AuthUser $authUser, Role $role): bool
    {
            return $this->checkHierarchicalPermission($authUser, 'Replicate:Role', $role->team_id, true) || $authUser->isTeamAdmin($role->team_id);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->checkHierarchicalPermission($authUser, 'Reorder:Role') || $authUser->isMainCompanyAdmin() || $authUser->isTeamAdmin(null);
    }
}
