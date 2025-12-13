<?php

namespace App\Policies;

use App\Models\ProductItem;
use App\Models\User;
use App\Policies\Concerns\HandlesAccessControl;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductItemPolicy
{
    use HandlesAccessControl, HandlesAuthorization;

    protected function getModelClass(): string
    {
        return ProductItem::class;
    }

    public function viewAny(User $user): bool
    {
        return $this->canViewAny($user, 'viewAny');
    }

    public function view(User $user, ProductItem $productItem): bool
    {
        return $this->hasModelAccess($user, $productItem, 'view');
    }

    public function create(User $user): bool
    {
        return $this->canViewAny($user, 'create');
    }

    public function update(User $user, ProductItem $productItem): bool
    {
        return $this->hasModelAccess($user, $productItem, 'update');
    }

    public function delete(User $user, ProductItem $productItem): bool
    {
        return $this->hasModelAccess($user, $productItem, 'delete');
    }

    public function restore(User $user, ProductItem $productItem): bool
    {
        return $this->hasModelAccess($user, $productItem, 'restore');
    }

    public function forceDelete(User $user, ProductItem $productItem): bool
    {
        return $this->hasModelAccess($user, $productItem, 'forceDelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canViewAny($user, 'forceDeleteAny');
    }

    public function restoreAny(User $user): bool
    {
        return $this->canViewAny($user, 'restoreAny');
    }

    public function replicate(User $user, ProductItem $productItem): bool
    {
        return $this->hasModelAccess($user, $productItem, 'replicate');
    }

    public function reorder(User $user): bool
    {
        return $this->canViewAny($user, 'reorder');
    }
}
