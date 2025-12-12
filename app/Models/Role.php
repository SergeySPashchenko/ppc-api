<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'team_id',
    ];

    /**
     * Get the access (team) that owns this role.
     *
     * @return BelongsTo<Access>
     */
    public function access(): BelongsTo
    {
        return $this->belongsTo(Access::class, 'team_id');
    }
}
