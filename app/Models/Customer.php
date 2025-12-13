<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AccessibleByUserUniversalTrait;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Customer extends Model
{
    use AccessibleByUserUniversalTrait;

    protected static function boot(): void
    {
        parent::boot();
        self::$cacheAccess = true;
    }

    /**
     * Customer access is determined through orders.
     * A customer is accessible if user has access to any of their orders.
     * We override calculateAccessibleIds to handle this special case.
     */
    protected static function calculateAccessibleIds(\App\Models\User $user): \Illuminate\Support\Collection
    {
        // Get accessible order IDs
        $accessibleOrderIds = \App\Models\Order::getAccessibleIdsForUser($user);

        if ($accessibleOrderIds->isEmpty()) {
            return collect();
        }

        // Get customer IDs from accessible orders
        return \App\Models\Order::query()
            ->whereIn('id', $accessibleOrderIds)
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id');
    }

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'email',
        'name',
        'phone',
    ];

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }
}
