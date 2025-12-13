<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Policies\Concerns\HandlesAccessControl;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAccessControl, HandlesAuthorization;

    protected function getModelClass(): string
    {
        return Order::class;
    }

    public function viewAny(User $user): bool
    {
        return $this->canViewAny($user, 'viewAny');
    }

    public function view(User $user, Order $order): bool
    {
        return $this->hasModelAccess($user, $order, 'view');
    }

    public function create(User $user): bool
    {
        return $this->canViewAny($user, 'create');
    }

    public function update(User $user, Order $order): bool
    {
        return $this->hasModelAccess($user, $order, 'update');
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->hasModelAccess($user, $order, 'delete');
    }

    public function restore(User $user, Order $order): bool
    {
        return $this->hasModelAccess($user, $order, 'restore');
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return $this->hasModelAccess($user, $order, 'forceDelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canViewAny($user, 'forceDeleteAny');
    }

    public function restoreAny(User $user): bool
    {
        return $this->canViewAny($user, 'restoreAny');
    }

    public function replicate(User $user, Order $order): bool
    {
        return $this->hasModelAccess($user, $order, 'replicate');
    }

    public function reorder(User $user): bool
    {
        return $this->canViewAny($user, 'reorder');
    }
}
