<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AccessibleByUserUniversalTrait;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Address extends Model
{
    use AccessibleByUserUniversalTrait;

    protected static function boot(): void
    {
        parent::boot();
        self::$cacheAccess = true;
    }

    /**
     * Address access is determined through customer or orders.
     * An address is accessible if user has access to the customer or any order using it.
     */
    protected function parentRelation(): ?string
    {
        return 'customer'; // Inherit access through customer
    }

    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'address',
        'address2',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'address_hash',
        'customer_id',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsToMany<Order, $this>
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'address_order', 'address_id', 'order_id')
            ->withTimestamps();
    }
}
