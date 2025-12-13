<?php

namespace App\Models;

use App\Models\Concerns\AccessibleByUserUniversalTrait;
use Filament\Models\Contracts\HasCurrentTenantLabel;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Brand extends Model implements HasCurrentTenantLabel, HasName
{
    use AccessibleByUserUniversalTrait;

    protected static function boot(): void
    {
        parent::boot();
        static::$cacheAccess = true;
    }

    /** @use HasFactory<\Database\Factories\BrandFactory> */
    use HasFactory;

    use HasSlug;
    use SoftDeletes;

    protected $fillable = ['brand_name', 'slug'];

    protected $primaryKey = 'brand_id';

    /**
     * Get the name attribute (for Filament compatibility).
     */
    public function getNameAttribute(): string
    {
        return $this->brand_name ?? '';
    }

    /**
     * Get the current tenant label.
     */
    public function getCurrentTenantLabel(): string
    {
        return 'Active Brand';
    }

    /**
     * Get the Filament name for the tenant.
     */
    public function getFilamentName(): string
    {
        return $this->brand_name ?? '';
    }

    /**
     * Get the slug attribute for Filament.
     */
    public function getSlug(): string
    {
        return $this->slug ?? '';
    }

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

    /**
     * Resolve the route binding value.
     * Handle "all" slug for AllBrandsTenant.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($value === 'all') {
            return new AllBrandsTenant;
        }

        return static::where($field ?? $this->getRouteKeyName(), $value)->first();
    }
}
