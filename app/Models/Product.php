<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;
    use HasSlug;
    use SoftDeletes;
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
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
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
}
