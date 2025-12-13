<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    /** @use HasFactory<\Database\Factories\BrandFactory> */
    use HasFactory;
    use HasSlug;
    use SoftDeletes;
    protected $fillable = ['brand_name', 'slug'];
    protected $primaryKey = 'brand_id';
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('brand_name')
            ->saveSlugsTo('slug')
            ->preventOverwrite();
    }
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id', 'brand_id');
    }
}
