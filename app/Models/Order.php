<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AccessibleByUserUniversalTrait;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Order extends Model
{
    use AccessibleByUserUniversalTrait;

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();
        self::$cacheAccess = true;
    }

    protected $fillable = [
        'OrderID',
        'Agent',
        'Created',
        'OrderDate',
        'OrderNum',
        'ProductTotal',
        'GrandTotal',
        'RefundAmount',
        'Shipping',
        'ShippingMethod',
        'Refund',
        'customer_id',
        'BrandID',
    ];

    /**
     * Назва батьківського відношення для рекурсії доступу
     * Orders inherit access through their associated Product (via BrandID -> ProductID)
     */
    protected function parentRelation(): ?string
    {
        return 'product';
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     *                                   Note: BrandID in orders table references ProductID in products table
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'BrandID', 'ProductID');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'OrderID', 'id');
    }

    /**
     * @return BelongsToMany<Address, $this>
     */
    public function addresses(): BelongsToMany
    {
        return $this->belongsToMany(Address::class, 'address_order', 'order_id', 'address_id')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'Created' => 'datetime',
            'OrderDate' => 'date',
            'ProductTotal' => 'decimal:2',
            'GrandTotal' => 'decimal:2',
            'RefundAmount' => 'decimal:2',
            'Refund' => 'boolean',
        ];
    }
}
