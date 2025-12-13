<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait HandlesAccessControl
{
    /**
     * Check if user has access to the model instance.
     * Combines Spatie permission check with AccessibleByUserUniversalTrait access control.
     */
    protected function hasModelAccess(User $user, Model $model, string $permission): bool
    {
        // Global admins bypass all checks
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Check Spatie permission first
        $permissionName = $this->getPermissionName($permission);
        if (! $user->can($permissionName)) {
            return false;
        }

        // Check if model uses AccessibleByUserUniversalTrait and verify access
        if (method_exists($model, 'isAccessibleBy')) {
            return $model->isAccessibleBy($user);
        }

        // If model doesn't have access control trait, permission check is sufficient
        return true;
    }

    /**
     * Check if user can view any models (for viewAny methods).
     * Combines Spatie permission check with access control.
     */
    protected function canViewAny(User $user, string $permission): bool
    {
        // Global admins bypass all checks
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Check Spatie permission
        $permissionName = $this->getPermissionName($permission);
        if (! $user->can($permissionName)) {
            return false;
        }

        // For models with access control, check if user has any access
        // This is handled by getEloquentQuery in Filament resources
        return true;
    }

    /**
     * Get permission name in Shield format.
     */
    protected function getPermissionName(string $action): string
    {
        $modelName = class_basename($this->getModelClass());
        $permissionMap = [
            'viewAny' => "ViewAny:{$modelName}",
            'view' => "View:{$modelName}",
            'create' => "Create:{$modelName}",
            'update' => "Update:{$modelName}",
            'delete' => "Delete:{$modelName}",
            'restore' => "Restore:{$modelName}",
            'forceDelete' => "ForceDelete:{$modelName}",
            'forceDeleteAny' => "ForceDeleteAny:{$modelName}",
            'restoreAny' => "RestoreAny:{$modelName}",
            'replicate' => "Replicate:{$modelName}",
            'reorder' => "Reorder:{$modelName}",
        ];

        return $permissionMap[$action] ?? "{$action}:{$modelName}";
    }

    /**
     * Get the model class name for this policy.
     */
    abstract protected function getModelClass(): string;
}
