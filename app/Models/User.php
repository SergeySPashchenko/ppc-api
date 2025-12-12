<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles;
    use HasSlug;
    use SoftDeletes;
    use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
    ];

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

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('username');
    }

    public function getRouteKeyName(): string
    {
        return 'username';
    }

    /**
     * Get all of the accesses.
     *
     * @return HasMany<Access, $this>
     */
    public function accesses(): HasMany
    {
        return $this->hasMany(Access::class);
    }

    /**
     * Get all companies the user has access to.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        // Використовуємо ключ 'company' з морф-мапи (визначено в AppServiceProvider)
        return $this->belongsToMany(
            Company::class,
            'accesses',
            'user_id',
            'accessible_id'
        )->where('accesses.accessible_type', 'company')
            ->whereNull('accesses.deleted_at')
            ->withTimestamps();
    }

    /**
     * Get the main company for the user.
     */
    public function company(): ?Company
    {
        /** @var Company|null $company */
        $company = $this->companies()->where('name', 'Main')->first();

        return $company;
    }

    /**
     * Get team ID for the given model.
     */
    public function getTeamIdFor(Model $model): ?string
    {
        /** @var string $modelId */
        $modelId = $model->getKey();

        // Визначаємо ключ з морф-мапи на основі класу моделі
        $modelMorphKey = match ($model::class) {
            Company::class => 'company',
            // Brand::class => 'brand',
            // Product::class => 'product',
            self::class => 'user',
            Access::class => 'access',
            default => $model::class,
        };

        $access = $this->accesses()
            ->where('accessible_type', $modelMorphKey)
            ->where('accessible_id', $modelId)
            ->first();

        /** @var string|null $accessId */
        $accessId = $access?->id;

        return $accessId; // team_id
    }

    /**
     * Get Main Company Access ID for user.
     */
    public function getMainCompanyAccessId(): ?int
    {
        $mainCompany = $this->company();

        if ($mainCompany === null) {
            return null;
        }

        $mainAccess = Access::query()
            ->where('user_id', $this->id)
            ->where('accessible_type', 'company')
            ->where('accessible_id', $mainCompany->id)
            ->whereNull('deleted_at')
            ->first();

        return $mainAccess?->id;
    }

    /**
     * Check if user has permission at Main Company level (highest level).
     */
    public function hasMainCompanyPermission(string $permission): bool
    {
        $mainAccessId = $this->getMainCompanyAccessId();

        if ($mainAccessId === null) {
            return false;
        }

        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $currentTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($mainAccessId);

        $hasPermission = $this->can($permission);

        $registrar->setPermissionsTeamId($currentTeamId);

        return $hasPermission;
    }

    /**
     * Check if user has permission at specific team level.
     */
    public function hasTeamPermission(string $permission, ?int $teamId): bool
    {
        if ($teamId === null) {
            return false;
        }

        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $currentTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($teamId);

        $hasPermission = $this->can($permission);

        $registrar->setPermissionsTeamId($currentTeamId);

        return $hasPermission;
    }

    /**
     * Check if user has super-admin or admin role at Main Company.
     */
    public function isMainCompanyAdmin(): bool
    {
        $mainAccessId = $this->getMainCompanyAccessId();

        if ($mainAccessId === null) {
            return false;
        }

        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $currentTeamId = $registrar->getPermissionsTeamId();

        // Встановлюємо team_id для перевірки ролей на рівні Main Company
        $registrar->setPermissionsTeamId($mainAccessId);

        // Скидаємо кешовані відношення, щоб завантажити ролі для нового team_id
        $this->unsetRelation('roles')->unsetRelation('permissions');

        // Перевіряємо ролі
        $hasRole = $this->roles()
            ->whereIn('name', ['super-admin', 'admin'])
            ->exists();

        // Відновлюємо попередній team_id
        $registrar->setPermissionsTeamId($currentTeamId);

        // Скидаємо відношення знову, щоб повернутися до поточного стану
        $this->unsetRelation('roles')->unsetRelation('permissions');

        return $hasRole;
    }

    /**
     * Check if user has super-admin or admin role at specific team level.
     */
    public function isTeamAdmin(?int $teamId): bool
    {
        if ($teamId === null) {
            return false;
        }

        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $currentTeamId = $registrar->getPermissionsTeamId();

        // Встановлюємо team_id для перевірки ролей на конкретному рівні
        $registrar->setPermissionsTeamId($teamId);

        // Скидаємо кешовані відношення, щоб завантажити ролі для нового team_id
        $this->unsetRelation('roles')->unsetRelation('permissions');

        // Перевіряємо ролі
        $hasRole = $this->roles()
            ->whereIn('name', ['super-admin', 'admin'])
            ->exists();

        // Відновлюємо попередній team_id
        $registrar->setPermissionsTeamId($currentTeamId);

        // Скидаємо відношення знову, щоб повернутися до поточного стану
        $this->unsetRelation('roles')->unsetRelation('permissions');

        return $hasRole;
    }

    /**
     * Check if user has edit permission at Main Company level.
     */
    public function hasMainCompanyEditPermission(string $permission): bool
    {
        // Визначаємо базову назву дозволу (без префіксу)
        $basePermission = preg_replace('/^[^:]+:/', '', $permission);

        // Перевіряємо різні типи дозволів на редагування
        $editPermissions = [
            'Update:'.$basePermission,
            'Delete:'.$basePermission,
            'Restore:'.$basePermission,
            'ForceDelete:'.$basePermission,
            'Replicate:'.$basePermission,
        ];

        foreach ($editPermissions as $editPerm) {
            if ($this->hasMainCompanyPermission($editPerm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hierarchical permission check: Main Company first, then current level.
     * Prevents lower level restrictions if higher level has edit access (unless admin).
     */
    public function checkHierarchicalPermission(
        string $permission,
        ?int $teamId = null,
        bool $isEditPermission = false
    ): bool {
        // 1. Перевіряємо доступ на рівні Main Company (найвищий рівень)
        $hasMainPermission = $this->hasMainCompanyPermission($permission);

        if ($hasMainPermission) {
            // Якщо є доступ на вищому рівні, повертаємо true
            return true;
        }

        // 2. Якщо немає доступу на вищому рівні, перевіряємо поточний рівень
        if ($teamId === null) {
            // Для методів без team_id (viewAny, create тощо) перевіряємо тільки на рівні Main Company
            return false;
        }

        $hasTeamPermission = $this->hasTeamPermission($permission, $teamId);

        // 3. Захист від колізій: якщо це дозвіл на редагування і на вищому рівні є доступ до редагування,
        // то тільки адміни можуть обмежити доступ на нижчому рівні
        if ($isEditPermission) {
            // Перевіряємо, чи на вищому рівні є доступ до редагування
            $hasMainEditPermission = $this->hasMainCompanyEditPermission($permission);

            // Якщо на вищому рівні є доступ до редагування, але на нижчому немає,
            // то тільки адміни можуть обмежити доступ
            if ($hasMainEditPermission && ! $hasTeamPermission) {
                // Перевіряємо, чи користувач є адміном на нижчому рівні
                // Якщо не адмін, то не можна обмежувати доступ - повертаємо true
                if (! $this->isTeamAdmin($teamId)) {
                    return true; // Повертаємо true, бо на вищому рівні є доступ
                }
            }
        }

        return $hasTeamPermission;
    }

    /**
     * Check if user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true; // Всі авторизовані користувачі мають доступ до панелі
    }

    /**
     * Get all accessible tenants (Accesses) for Filament tenancy.
     * Only returns accesses if user has more than one access.
     */
    public function getTenants(Panel $panel): Collection
    {
        // Отримуємо всі доступні Access для користувача
        $accesses = $this->accesses()
            ->whereNull('deleted_at')
            ->with('accessible')
            ->get();

        // Фільтруємо тільки ті, де accessible не null
        return $accesses->filter(fn (Access $access) => $access->accessible !== null);
    }

    /**
     * Check if user can access a specific tenant (Access).
     */
    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Access) {
            return false;
        }

        return $this->accesses()
            ->where('id', $tenant->id)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Check if user has access to more than one brand/company.
     * If false, tenant switcher should be hidden.
     */
    public function hasMultipleTenants(): bool
    {
        return $this->accesses()
            ->whereNull('deleted_at')
            ->whereNotNull('accessible_id')
            ->count() > 1;
    }

    /**
     * Get available brands/companies count for current accessible type.
     */
    public function getAvailableTenantsCount(?string $accessibleType = null): int
    {
        $query = $this->accesses()
            ->whereNull('deleted_at')
            ->whereNotNull('accessible_id');

        if ($accessibleType !== null) {
            $query->where('accessible_type', $accessibleType);
        }

        return $query->count();
    }

    /**
     * Get default tenant (Main Company Access) for Filament.
     */
    public function getDefaultTenant(Panel $panel): ?Model
    {
        $mainAccessId = $this->getMainCompanyAccessId();

        if ($mainAccessId === null) {
            return null;
        }

        return Access::query()
            ->where('id', $mainAccessId)
            ->whereNull('deleted_at')
            ->first();
    }
}
