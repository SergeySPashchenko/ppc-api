<?php

namespace App\Models;

use App\Models\Concerns\AccessibleByUserTrait;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model
{
    use AccessibleByUserTrait;

    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    use HasSlug;
    use SoftDeletes;

    // Filament automatically adds tenant scope for tenant-aware resources
    // No need for manual global scope here

    protected $fillable = [
        'ProductID',
        'Product',
        'slug',
        'newSystem',
        'Visible',
        'flyer',
        'main_category_id',
        'marketing_category_id',
        'gender_id',
        'brand_id',
    ];

    protected $primaryKey = 'ProductID';

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('Product')
            ->saveSlugsTo('slug')
            ->preventOverwrite();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the brand that owns this product.
     *
     * @return BelongsTo<Brand>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'brand_id');
    }

    /**
     * Check if user has inherited access through brand.
     */
    protected function hasInheritedAccess(User $user): bool
    {
        if (! $this->brand_id) {
            return false;
        }

        $brand = Brand::find($this->brand_id);

        return $brand && $brand->isAccessibleBy($user);
    }

    /**
     * Get all accessible IDs for a user, including inherited access from brands.
     */
    public static function getAccessibleIdsForUser(User $user): Collection
    {
        $morphType = static::getMorphType();

        // Direct access
        $directIds = \App\Models\Access::query()
            ->where('user_id', $user->id)
            ->where('accessible_type', $morphType)
            ->pluck('accessible_id');

        // Inherited access from brands
        $brandIds = \App\Models\Access::query()
            ->where('user_id', $user->id)
            ->where('accessible_type', Brand::getMorphType())
            ->pluck('accessible_id');

        $inheritedIds = collect();
        if ($brandIds->isNotEmpty()) {
            $inheritedIds = static::query()
                ->whereIn('brand_id', $brandIds)
                ->pluck('ProductID');
        }

        return $directIds->merge($inheritedIds)->unique();
    }

    /**
     * Get the main category for this product.
     *
     * @return BelongsTo<Category>
     */
    public function mainCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'main_category_id', 'category_id');
    }

    /**
     * Get the marketing category for this product.
     *
     * @return BelongsTo<Category>
     */
    public function marketingCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'marketing_category_id', 'category_id');
    }

    /**
     * Get the gender for this product.
     *
     * @return BelongsTo<Gender>
     */
    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class, 'gender_id', 'gender_id');
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'ProductID', 'ProductID');
    }

    /**
     * @return HasMany<ProductItem, $this>
     */
    public function productItems(): HasMany
    {
        return $this->hasMany(ProductItem::class, 'ProductID', 'ProductID');
    }
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'ProductID', 'ProductID');
    }

}
