<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;
use App\Policies\Concerns\HandlesAccessControl;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderItemPolicy
{
    use HandlesAccessControl, HandlesAuthorization;

    protected function getModelClass(): string
    {
        return OrderItem::class;
    }

    public function viewAny(User $user): bool
    {
        return $this->canViewAny($user, 'viewAny');
    }

    public function view(User $user, OrderItem $orderItem): bool
    {
        return $this->hasModelAccess($user, $orderItem, 'view');
    }

    public function create(User $user): bool
    {
        return $this->canViewAny($user, 'create');
    }

    public function update(User $user, OrderItem $orderItem): bool
    {
        return $this->hasModelAccess($user, $orderItem, 'update');
    }

    public function delete(User $user, OrderItem $orderItem): bool
    {
        return $this->hasModelAccess($user, $orderItem, 'delete');
    }

    public function restore(User $user, OrderItem $orderItem): bool
    {
        return $this->hasModelAccess($user, $orderItem, 'restore');
    }

    public function forceDelete(User $user, OrderItem $orderItem): bool
    {
        return $this->hasModelAccess($user, $orderItem, 'forceDelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canViewAny($user, 'forceDeleteAny');
    }

    public function restoreAny(User $user): bool
    {
        return $this->canViewAny($user, 'restoreAny');
    }

    public function replicate(User $user, OrderItem $orderItem): bool
    {
        return $this->hasModelAccess($user, $orderItem, 'replicate');
    }

    public function reorder(User $user): bool
    {
        return $this->canViewAny($user, 'reorder');
    }
}
