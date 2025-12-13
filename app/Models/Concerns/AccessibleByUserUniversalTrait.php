<?php

namespace App\Models\Concerns;

use App\Models\Access;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait AccessibleByUserUniversalTrait
{
    /**
     * Тип доступу для моделі ('brand', 'product', 'product_item', ...)
     * Якщо не задано, використовується getMorphType()
     */
    protected static ?string $accessType = null;

    /**
     * Чи використовувати кешування доступу
     */
    protected static bool $cacheAccess = false;

    /**
     * Отримати morph relation на таблицю accesses
     */
    public function accesses(): MorphMany
    {
        return $this->morphMany(Access::class, 'accessible');
    }

    /**
     * Повертає морф-тип для Access
     */
    public static function getMorphType(): string
    {
        if (static::$accessType !== null) {
            return static::$accessType;
        }

        return match (static::class) {
            \App\Models\Brand::class => 'brand',
            \App\Models\Product::class => 'product',
            \App\Models\ProductItem::class => 'product_item',
            \App\Models\Order::class => 'order',
            \App\Models\OrderItem::class => 'order_item',
            \App\Models\Expense::class => 'expense',
            \App\Models\Customer::class => 'customer',
            \App\Models\Address::class => 'address',
            default => strtolower(class_basename(static::class)),
        };
    }

    /**
     * Назва батьківського відношення для рекурсії доступу
     * Повертає null, якщо немає батька
     * Override this method in child models to specify parent relation
     */
    protected function parentRelation(): ?string
    {
        return null;
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
     * Uses parentRelation() for recursive access checking.
     */
    protected function hasInheritedAccess(User $user): bool
    {
        $parentRelation = $this->parentRelation();

        if (! $parentRelation || ! method_exists($this, $parentRelation)) {
            return false;
        }

        $parent = $this->$parentRelation;

        if (! $parent) {
            return false;
        }

        // Recursively check parent access
        if (method_exists($parent, 'isAccessibleBy')) {
            return $parent->isAccessibleBy($user);
        }

        return false;
    }

    /**
     * Scope для фільтрації записів доступних користувачу
     */
    public function scopeAccessibleBy(Builder $query, ?User $user = null, ?int $filterId = null): Builder
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

        // Фільтр по конкретному ID
        if ($filterId !== null) {
            $table = $this->getTable();
            $key = $this->getKeyName();

            return $query->where($table.'.'.$key, $filterId);
        }

        $accessibleIds = static::getAccessibleIdsForUser($user);

        if ($accessibleIds->isEmpty()) {
            return $query->whereRaw('1 = 0'); // Немає доступних записів
        }

        $table = $this->getTable();
        $key = $this->getKeyName();

        return $query->whereIn($table.'.'.$key, $accessibleIds);
    }

    /**
     * Повертає всі доступні ID для користувача з рекурсією через parentRelation()
     */
    public static function getAccessibleIdsForUser(User $user): Collection
    {
        // Глобальні адміни бачать все
        if ($user->isGlobalAdmin()) {
            return static::query()->pluck((new static)->getKeyName());
        }

        $cacheKey = 'accessible_ids:'.static::getMorphType().':user:'.$user->id;

        if (static::$cacheAccess) {
            return Cache::remember($cacheKey, now()->addMinutes(10), fn () => static::calculateAccessibleIds($user));
        }

        return static::calculateAccessibleIds($user);
    }

    /**
     * Обчислює доступні ID без кешу з рекурсією через parentRelation()
     */
    protected static function calculateAccessibleIds(User $user): Collection
    {
        $model = new static;
        $table = $model->getTable();
        $key = $model->getKeyName();
        $morphType = static::getMorphType();

        // Прямий доступ
        $directIds = Access::query()
            ->where('user_id', $user->id)
            ->where('accessible_type', $morphType)
            ->pluck('accessible_id');

        // Доступ через батьківські моделі (рекурсивно)
        // Створюємо екземпляр для виклику parentRelation()
        $instance = new static;
        $parentRelation = $instance->parentRelation();

        if ($parentRelation && method_exists($instance, $parentRelation)) {
            $relation = $instance->$parentRelation();
            $parentModelClass = get_class($relation->getRelated());

            // Рекурсивно отримуємо доступні ID батьківської моделі
            if (method_exists($parentModelClass, 'getAccessibleIdsForUser')) {
                $parentIds = $parentModelClass::getAccessibleIdsForUser($user);

                if ($parentIds->isNotEmpty()) {
                    // Отримуємо foreign key name
                    $foreignKey = $relation->getForeignKeyName();
                    if (str_contains($foreignKey, '.')) {
                        $foreignKey = explode('.', $foreignKey)[1];
                    }

                    $childIds = static::query()
                        ->whereIn($foreignKey, $parentIds)
                        ->pluck($key);

                    $directIds = $directIds->merge($childIds)->unique();
                }
            }
        }

        return $directIds->values();
    }

    /**
     * Очистити кеш доступу користувача
     */
    public static function clearAccessCache(?User $user = null): void
    {
        if (static::$cacheAccess) {
            if ($user) {
                $cacheKey = 'accessible_ids:'.static::getMorphType().':user:'.$user->id;
                Cache::forget($cacheKey);
            } else {
                // Clear all caches for this model type
                Cache::flush();
            }
        }
    }
}
