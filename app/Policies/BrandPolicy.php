<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;
use App\Policies\Concerns\HandlesAccessControl;
use Illuminate\Auth\Access\HandlesAuthorization;

class BrandPolicy
{
    use HandlesAccessControl, HandlesAuthorization;

    protected function getModelClass(): string
    {
        return Brand::class;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canViewAny($user, 'viewAny');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Brand $brand): bool
    {
        return $this->hasModelAccess($user, $brand, 'view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canViewAny($user, 'create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Brand $brand): bool
    {
        return $this->hasModelAccess($user, $brand, 'update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Brand $brand): bool
    {
        return $this->hasModelAccess($user, $brand, 'delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Brand $brand): bool
    {
        return $this->hasModelAccess($user, $brand, 'restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Brand $brand): bool
    {
        return $this->hasModelAccess($user, $brand, 'forceDelete');
    }

    /**
     * Determine whether the user can bulk delete models.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $this->canViewAny($user, 'forceDeleteAny');
    }

    /**
     * Determine whether the user can bulk restore models.
     */
    public function restoreAny(User $user): bool
    {
        return $this->canViewAny($user, 'restoreAny');
    }

    /**
     * Determine whether the user can replicate the model.
     */
    public function replicate(User $user, Brand $brand): bool
    {
        return $this->hasModelAccess($user, $brand, 'replicate');
    }

    /**
     * Determine whether the user can reorder models.
     */
    public function reorder(User $user): bool
    {
        return $this->canViewAny($user, 'reorder');
    }
}
