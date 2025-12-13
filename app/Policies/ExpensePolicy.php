<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\HandlesAccessControl;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpensePolicy
{
    use HandlesAccessControl, HandlesAuthorization;

    protected function getModelClass(): string
    {
        return Expense::class;
    }

    public function viewAny(User $user): bool
    {
        return $this->canViewAny($user, 'viewAny');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->hasModelAccess($user, $expense, 'view');
    }

    public function create(User $user): bool
    {
        return $this->canViewAny($user, 'create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->hasModelAccess($user, $expense, 'update');
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->hasModelAccess($user, $expense, 'delete');
    }

    public function restore(User $user, Expense $expense): bool
    {
        return $this->hasModelAccess($user, $expense, 'restore');
    }

    public function forceDelete(User $user, Expense $expense): bool
    {
        return $this->hasModelAccess($user, $expense, 'forceDelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canViewAny($user, 'forceDeleteAny');
    }

    public function restoreAny(User $user): bool
    {
        return $this->canViewAny($user, 'restoreAny');
    }

    public function replicate(User $user, Expense $expense): bool
    {
        return $this->hasModelAccess($user, $expense, 'replicate');
    }

    public function reorder(User $user): bool
    {
        return $this->canViewAny($user, 'reorder');
    }
}
