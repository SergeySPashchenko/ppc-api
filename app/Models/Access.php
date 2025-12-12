<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Access extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accessible()
    {
        return $this->morphTo();
    }

    /**
     * Get all roles for this access (team).
     *
     * @return HasMany<Role>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'team_id');
    }

    /**
     * Get the name for tenant display.
     */
    public function getNameAttribute(): ?string
    {
        if ($this->accessible === null) {
            return 'Unknown';
        }

        return match ($this->accessible_type) {
            'company' => $this->accessible->name ?? 'Company',
            // 'brand' => $this->accessible->name ?? 'Brand',
            default => class_basename($this->accessible_type),
        };
    }

    /**
     * Get the slug for tenant routing.
     */
    public function getSlugAttribute(): ?string
    {
        if ($this->accessible === null) {
            return (string) $this->id;
        }

        return match ($this->accessible_type) {
            'company' => $this->accessible->slug ?? (string) $this->id,
            // 'brand' => $this->accessible->slug ?? (string) $this->id,
            default => (string) $this->id,
        };
    }

    /**
     * Get the route key name for tenant routing.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
