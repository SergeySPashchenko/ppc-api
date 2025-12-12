<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles;
    use HasSlug;
    use SoftDeletes;

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
}
