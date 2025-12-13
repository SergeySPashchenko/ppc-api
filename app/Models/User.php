<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasSlug, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'email',
        'password',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->preventOverwrite();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(Access::class, 'user_id', 'id');
    }

    /**
     * Check if user is a global admin.
     */
    public function isGlobalAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Get all accessible brand IDs for this user.
     */
    public function getAccessibleBrandIds(): \Illuminate\Support\Collection
    {
        return app(\App\Services\AccessService::class)->getAccessibleIds($this, \App\Models\Brand::class);
    }

    /**
     * Get all accessible product IDs for this user.
     */
    public function getAccessibleProductIds(): \Illuminate\Support\Collection
    {
        return app(\App\Services\AccessService::class)->getAccessibleIds($this, \App\Models\Product::class);
    }

    /**
     * Check if user has access to any brand or product.
     * If yes, categories and genders should be fully accessible.
     */
    public function hasAnyBrandOrProductAccess(): bool
    {
        if ($this->isGlobalAdmin()) {
            return true;
        }

        $brandIds = $this->getAccessibleBrandIds();
        $productIds = $this->getAccessibleProductIds();

        return $brandIds->isNotEmpty() || $productIds->isNotEmpty();
    }

    /**
     * Check if user can access the panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Get all brands (tenants) that this user belongs to.
     */
    public function getTenants(Panel $panel): Collection
    {
        $tenants = collect();

        // Add "All Brands" option first
        $allTenant = new \App\Models\AllBrandsTenant;
        $tenants->push($allTenant);

        // Global admins see all brands
        if ($this->isGlobalAdmin()) {
            $tenants = $tenants->merge(Brand::all());
        } else {
            // Return only accessible brands
            $accessibleBrandIds = $this->getAccessibleBrandIds();
            $tenants = $tenants->merge(Brand::whereIn('brand_id', $accessibleBrandIds)->get());
        }

        return $tenants;
    }

    /**
     * Check if user can access a specific tenant (brand).
     */
    public function canAccessTenant(Model $tenant): bool
    {
        // Allow access to "All Brands" tenant
        if ($tenant instanceof \App\Models\AllBrandsTenant) {
            return true;
        }

        if ($this->isGlobalAdmin()) {
            return true;
        }

        if (! $tenant instanceof Brand) {
            return false;
        }

        return $this->getAccessibleBrandIds()->contains($tenant->brand_id);
    }

    /**
     * Get the default tenant for this user.
     */
    public function getDefaultTenant(Panel $panel): ?Model
    {
        $tenants = $this->getTenants($panel);

        // Return "All Brands" as default (first in collection)
        return $tenants->first();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
