<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        // Only global admins can view all users
        return $user->isGlobalAdmin();
    }

    public function view(User $user, User $model): bool
    {
        // Global admins can view any user
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Users can view their own profile
        return $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        // Only global admins can create users
        return $user->isGlobalAdmin();
    }

    public function update(User $user, User $model): bool
    {
        // Global admins can update any user
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Users can update their own profile
        return $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        // Only global admins can delete users
        // Users cannot delete themselves
        return $user->isGlobalAdmin() && $user->id !== $model->id;
    }

    public function restore(User $user, User $model): bool
    {
        // Only global admins can restore users
        return $user->isGlobalAdmin();
    }

    public function forceDelete(User $user, User $model): bool
    {
        // Only global admins can force delete users
        // Users cannot delete themselves
        return $user->isGlobalAdmin() && $user->id !== $model->id;
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function replicate(User $user, User $model): bool
    {
        return $user->isGlobalAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->isGlobalAdmin();
    }
}
