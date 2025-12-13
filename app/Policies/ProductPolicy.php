<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\HandlesAccessControl;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAccessControl, HandlesAuthorization;

    protected function getModelClass(): string
    {
        return Product::class;
    }

    public function viewAny(User $user): bool
    {
        return $this->canViewAny($user, 'viewAny');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->hasModelAccess($user, $product, 'view');
    }

    public function create(User $user): bool
    {
        return $this->canViewAny($user, 'create');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->hasModelAccess($user, $product, 'update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->hasModelAccess($user, $product, 'delete');
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->hasModelAccess($user, $product, 'restore');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $this->hasModelAccess($user, $product, 'forceDelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canViewAny($user, 'forceDeleteAny');
    }

    public function restoreAny(User $user): bool
    {
        return $this->canViewAny($user, 'restoreAny');
    }

    public function replicate(User $user, Product $product): bool
    {
        return $this->hasModelAccess($user, $product, 'replicate');
    }

    public function reorder(User $user): bool
    {
        return $this->canViewAny($user, 'reorder');
    }
}
