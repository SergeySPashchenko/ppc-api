<?php

namespace App\Models;

use Filament\Models\Contracts\HasCurrentTenantLabel;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;

class AllBrandsTenant extends Model implements HasCurrentTenantLabel, HasName
{
    protected $table = 'brands';

    protected $primaryKey = 'brand_id';

    public $exists = false;

    /**
     * Get the name attribute (for Filament compatibility).
     */
    public function getNameAttribute(): string
    {
        return 'All Brands';
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
        return 'All Brands';
    }

    /**
     * Get the slug attribute for Filament.
     */
    public function getSlug(): string
    {
        return 'all';
    }

    /**
     * Get the slug attribute (accessor).
     */
    public function getSlugAttribute(): string
    {
        return 'all';
    }

    /**
     * Get the route key name.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the key for route model binding.
     */
    public function getRouteKey(): string
    {
        return 'all';
    }

    /**
     * Check if this is the "All" tenant.
     */
    public function isAllTenant(): bool
    {
        return true;
    }
}
