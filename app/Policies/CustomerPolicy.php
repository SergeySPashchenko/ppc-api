<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\HandlesAccessControl;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAccessControl, HandlesAuthorization;

    protected function getModelClass(): string
    {
        return Customer::class;
    }

    public function viewAny(User $user): bool
    {
        return $this->canViewAny($user, 'viewAny');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->hasModelAccess($user, $customer, 'view');
    }

    public function create(User $user): bool
    {
        return $this->canViewAny($user, 'create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->hasModelAccess($user, $customer, 'update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->hasModelAccess($user, $customer, 'delete');
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $this->hasModelAccess($user, $customer, 'restore');
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return $this->hasModelAccess($user, $customer, 'forceDelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canViewAny($user, 'forceDeleteAny');
    }

    public function restoreAny(User $user): bool
    {
        return $this->canViewAny($user, 'restoreAny');
    }

    public function replicate(User $user, Customer $customer): bool
    {
        return $this->hasModelAccess($user, $customer, 'replicate');
    }

    public function reorder(User $user): bool
    {
        return $this->canViewAny($user, 'reorder');
    }
}
