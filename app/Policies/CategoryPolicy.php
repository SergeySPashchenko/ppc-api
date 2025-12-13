<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        // Categories are accessible if user has any brand or product access
        return $user->isGlobalAdmin() || $user->hasAnyBrandOrProductAccess();
    }

    public function view(User $user, Category $category): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isGlobalAdmin();
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isGlobalAdmin();
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->isGlobalAdmin();
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return $user->isGlobalAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function replicate(User $user, Category $category): bool
    {
        return $user->isGlobalAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->isGlobalAdmin();
    }
}
