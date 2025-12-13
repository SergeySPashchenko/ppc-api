<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Access extends Model
{
    /** @use HasFactory<\Database\Factories\AccessFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['level', 'is_guest', 'user_id', 'accessible_type', 'accessible_id'];

    protected static function booted(): void
    {
        static::created(function (Access $access) {
            if ($access->user_id) {
                $user = User::find($access->user_id);
                if ($user) {
                    app(\App\Services\AccessService::class)->clearCache($user, $access->accessible_type);
                }
            }
        });

        static::updated(function (Access $access) {
            if ($access->user_id) {
                $user = User::find($access->user_id);
                if ($user) {
                    app(\App\Services\AccessService::class)->clearCache($user, $access->accessible_type);
                }
            }
        });

        static::deleted(function (Access $access) {
            if ($access->user_id) {
                $user = User::find($access->user_id);
                if ($user) {
                    app(\App\Services\AccessService::class)->clearCache($user, $access->accessible_type);
                }
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function accessible(): MorphTo
    {
        return $this->morphTo('accessible');
    }
}
