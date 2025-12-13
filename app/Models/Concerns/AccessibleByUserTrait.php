<?php

namespace App\Models\Concerns;

use App\Models\Access;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait AccessibleByUserTrait
{
    /**
     * Get the morph type for this model.
     */
    public static function getMorphType(): string
    {
        return static::class;
    }

    /**
     * Get all accesses for this model.
     */
    public function accesses(): MorphMany
    {
        return $this->morphMany(Access::class, 'accessible');
    }

    /**
     * Check if a user has access to this model.
     */
    public function isAccessibleBy(?User $user = null): bool
    {
        if (! $user) {
            $user = auth()->user();
        }

        if (! $user) {
            return false;
        }

        // Global admins have access to everything
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Check direct access
        if ($this->hasDirectAccess($user)) {
            return true;
        }

        // Check inherited access through parent relations
        return $this->hasInheritedAccess($user);
    }

    /**
     * Check if user has direct access to this model.
     */
    protected function hasDirectAccess(User $user): bool
    {
        return Access::query()
            ->where('user_id', $user->id)
            ->where('accessible_type', static::getMorphType())
            ->where('accessible_id', $this->getKey())
            ->exists();
    }

    /**
     * Check if user has inherited access through parent relations.
     * Override this method in models that have parent relations.
     */
    protected function hasInheritedAccess(User $user): bool
    {
        // Override in child models to check parent access
        return false;
    }

    /**
     * Scope to filter models accessible by a user.
     */
    public function scopeAccessibleBy(Builder $query, ?User $user = null): Builder
    {
        if (! $user) {
            $user = auth()->user();
        }

        if (! $user) {
            return $query->whereRaw('1 = 0'); // No access for guests
        }

        // Global admins see everything
        if ($user->isGlobalAdmin()) {
            return $query;
        }

        $modelClass = static::class;
        $accessibleIds = app(\App\Services\AccessService::class)->getAccessibleIds($user, $modelClass);

        if ($accessibleIds->isEmpty()) {
            return $query->whereRaw('1 = 0'); // No accessible IDs
        }

        // Get the model instance to determine the key name
        $model = new $modelClass;
        $keyName = $model->getKeyName();
        $tableName = $model->getTable();

        return $query->whereIn($tableName.'.'.$keyName, $accessibleIds);
    }

    /**
     * Get all accessible IDs for a user, including inherited access.
     * Override this method in models with parent relations.
     */
    public static function getAccessibleIdsForUser(User $user): Collection
    {
        $morphType = static::getMorphType();

        // Direct access
        $directIds = Access::query()
            ->where('user_id', $user->id)
            ->where('accessible_type', $morphType)
            ->pluck('accessible_id');

        return $directIds;
    }
}
