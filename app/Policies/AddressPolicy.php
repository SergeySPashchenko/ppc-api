<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;
use App\Policies\Concerns\HandlesAccessControl;
use Illuminate\Auth\Access\HandlesAuthorization;

class AddressPolicy
{
    use HandlesAccessControl, HandlesAuthorization;

    protected function getModelClass(): string
    {
        return Address::class;
    }

    public function viewAny(User $user): bool
    {
        return $this->canViewAny($user, 'viewAny');
    }

    public function view(User $user, Address $address): bool
    {
        return $this->hasModelAccess($user, $address, 'view');
    }

    public function create(User $user): bool
    {
        return $this->canViewAny($user, 'create');
    }

    public function update(User $user, Address $address): bool
    {
        return $this->hasModelAccess($user, $address, 'update');
    }

    public function delete(User $user, Address $address): bool
    {
        return $this->hasModelAccess($user, $address, 'delete');
    }

    public function restore(User $user, Address $address): bool
    {
        return $this->hasModelAccess($user, $address, 'restore');
    }

    public function forceDelete(User $user, Address $address): bool
    {
        return $this->hasModelAccess($user, $address, 'forceDelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canViewAny($user, 'forceDeleteAny');
    }

    public function restoreAny(User $user): bool
    {
        return $this->canViewAny($user, 'restoreAny');
    }

    public function replicate(User $user, Address $address): bool
    {
        return $this->hasModelAccess($user, $address, 'replicate');
    }

    public function reorder(User $user): bool
    {
        return $this->canViewAny($user, 'reorder');
    }
}
