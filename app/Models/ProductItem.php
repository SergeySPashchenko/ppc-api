<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property Product|null $product
 */
final class ProductItem extends Model
{
    /** @use HasFactory<ProductItemFactory> */
    use HasFactory;

    use HasSlug;
    use SoftDeletes;

    protected $primaryKey = 'ItemID';

    protected $fillable = [
        'ProductID',
        'ProductName',
        'slug',
        'SKU',
        'Quantity',
        'upSell',
        'extraProduct',
        'offerProducts',
        'active',
        'deleted',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('ProductName')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(50)
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'upSell' => 'boolean',
            'extraProduct' => 'boolean',
            'active' => 'boolean',
            'deleted' => 'boolean',
        ];
    }
}
