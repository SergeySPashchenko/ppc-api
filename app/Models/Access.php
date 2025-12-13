<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Access extends Model
{
    /** @use HasFactory<\Database\Factories\AccessFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['level', 'is_guest', 'user_id', 'accessible_type', 'accessible_id'];

    protected static function booted(): void
    {
        static::created(function (Access $access) {
            if ($access->user_id) {
                $user = User::find($access->user_id);
                if ($user) {
                    static::clearAccessCacheForMorphType($user, $access->accessible_type);
                }
            }
        });

        static::updated(function (Access $access) {
            if ($access->user_id) {
                $user = User::find($access->user_id);
                if ($user) {
                    static::clearAccessCacheForMorphType($user, $access->accessible_type);
                }
            }
        });

        static::deleted(function (Access $access) {
            if ($access->user_id) {
                $user = User::find($access->user_id);
                if ($user) {
                    static::clearAccessCacheForMorphType($user, $access->accessible_type);
                }
            }
        });
    }

    /**
     * Clear access cache for a morph type.
     * This clears cache for the model type and all dependent types.
     */
    protected static function clearAccessCacheForMorphType(User $user, string $morphType): void
    {
        $modelClassMap = [
            'brand' => \App\Models\Brand::class,
            'product' => \App\Models\Product::class,
            'product_item' => \App\Models\ProductItem::class,
        ];

        $modelClass = $modelClassMap[$morphType] ?? null;

        if ($modelClass && method_exists($modelClass, 'clearAccessCache')) {
            $modelClass::clearAccessCache($user);

            // Clear dependent caches
            if ($morphType === 'brand') {
                if (method_exists(\App\Models\Product::class, 'clearAccessCache')) {
                    \App\Models\Product::clearAccessCache($user);
                }
                if (method_exists(\App\Models\ProductItem::class, 'clearAccessCache')) {
                    \App\Models\ProductItem::clearAccessCache($user);
                }
            } elseif ($morphType === 'product') {
                if (method_exists(\App\Models\ProductItem::class, 'clearAccessCache')) {
                    \App\Models\ProductItem::clearAccessCache($user);
                }
            }
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function accessible(): MorphTo
    {
        return $this->morphTo('accessible');
    }
}
