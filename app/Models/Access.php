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
}
