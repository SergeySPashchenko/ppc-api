<?php

namespace App\Services;

use App\Models\Access;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AccessService
{
    /**
     * Cache prefix for accessible IDs.
     */
    protected string $cachePrefix = 'accessible_ids';

    /**
     * Cache TTL in seconds (default: 1 hour).
     */
    protected int $cacheTtl = 3600;

    /**
     * Models that should use caching.
     */
    protected array $cachedModels = [
        Brand::class,
        Product::class,
        ProductItem::class,
    ];

    /**
     * Get accessible IDs for a user and model type.
     */
    public function getAccessibleIds(User $user, string $modelClass): Collection
    {
        // Global admins have access to all IDs
        if ($user->isGlobalAdmin()) {
            return $this->getAllIds($modelClass);
        }

        // Caching disabled - always calculate directly
        return $this->calculateAccessibleIds($user, $modelClass);
    }

    /**
     * Get cached accessible IDs.
     */
    protected function getCachedAccessibleIds(User $user, string $modelClass): Collection
    {
        $cacheKey = $this->getCacheKey($user, $modelClass);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $modelClass) {
            return $this->calculateAccessibleIds($user, $modelClass);
        });
    }

    /**
     * Calculate accessible IDs for a user and model type.
     */
    protected function calculateAccessibleIds(User $user, string $modelClass): Collection
    {
        $morphType = $modelClass::getMorphType();

        // Get direct access IDs
        $directIds = Access::query()
            ->where('user_id', $user->id)
            ->where('accessible_type', $morphType)
            ->pluck('accessible_id');

        // Get inherited IDs based on model type
        $inheritedIds = $this->getInheritedIds($user, $modelClass, $directIds);

        $result = $directIds->merge($inheritedIds)->unique()->values();

        return $result;
    }

    /**
     * Get inherited accessible IDs based on parent relations.
     */
    protected function getInheritedIds(User $user, string $modelClass, Collection $directIds): Collection
    {
        $inheritedIds = collect();

        // Products inherit access from Brands
        if ($modelClass === Product::class) {
            $brandIds = Access::query()
                ->where('user_id', $user->id)
                ->where('accessible_type', Brand::getMorphType())
                ->pluck('accessible_id');

            if ($brandIds->isNotEmpty()) {
                // Get all products for accessible brands
                $productIds = Product::query()
                    ->whereIn('brand_id', $brandIds)
                    ->pluck('ProductID');
                $inheritedIds = $inheritedIds->merge($productIds);
            }
        }

        // ProductItems inherit access from Products (and through Products from Brands)
        if ($modelClass === ProductItem::class) {
            // Get products user has direct access to
            $productIds = Access::query()
                ->where('user_id', $user->id)
                ->where('accessible_type', Product::getMorphType())
                ->pluck('accessible_id');

            // Get products accessible through brands
            $brandIds = Access::query()
                ->where('user_id', $user->id)
                ->where('accessible_type', Brand::getMorphType())
                ->pluck('accessible_id');

            $allProductIds = $productIds->toBase();
            if ($brandIds->isNotEmpty()) {
                $brandProductIds = Product::query()
                    ->whereIn('brand_id', $brandIds)
                    ->pluck('ProductID');
                $allProductIds = $allProductIds->merge($brandProductIds);
            }

            if ($allProductIds->isNotEmpty()) {
                // Get all product items for accessible products
                $itemIds = ProductItem::query()
                    ->whereIn('ProductID', $allProductIds)
                    ->pluck('ItemID');
                $inheritedIds = $inheritedIds->merge($itemIds);
            }
        }

        return $inheritedIds->values();
    }

    /**
     * Get all IDs for a model class.
     */
    protected function getAllIds(string $modelClass): Collection
    {
        return $modelClass::query()->pluck(
            (new $modelClass)->getKeyName()
        );
    }

    /**
     * Check if caching should be used for a model.
     */
    public function shouldCache(string $modelClass): bool
    {
        return in_array($modelClass, $this->cachedModels, true);
    }

    /**
     * Toggle caching for a model.
     */
    public function toggleCache(string $modelClass, bool $enabled): void
    {
        if ($enabled && ! in_array($modelClass, $this->cachedModels, true)) {
            $this->cachedModels[] = $modelClass;
        } elseif (! $enabled) {
            $this->cachedModels = array_values(
                array_diff($this->cachedModels, [$modelClass])
            );
        }
    }

    /**
     * Clear cache for a user and model.
     */
    public function clearCache(?User $user = null, ?string $modelClass = null): void
    {
        if ($user && $modelClass) {
            Cache::forget($this->getCacheKey($user, $modelClass));

            // If brand access changed, also clear product and product item cache
            if ($modelClass === Brand::class) {
                Cache::forget($this->getCacheKey($user, Product::class));
                Cache::forget($this->getCacheKey($user, ProductItem::class));
            }
            // If product access changed, also clear product item cache
            if ($modelClass === Product::class) {
                Cache::forget($this->getCacheKey($user, ProductItem::class));
            }
        } elseif ($user) {
            // Clear all caches for this user
            foreach ($this->cachedModels as $model) {
                Cache::forget($this->getCacheKey($user, $model));
            }
        } else {
            // Clear all access caches
            Cache::flush();
        }
    }

    /**
     * Get cache key for user and model.
     */
    protected function getCacheKey(User $user, string $modelClass): string
    {
        return "{$this->cachePrefix}:{$user->id}:{$modelClass}";
    }

    /**
     * Set cache TTL.
     */
    public function setCacheTtl(int $seconds): void
    {
        $this->cacheTtl = $seconds;
    }
}
