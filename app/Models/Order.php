<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use SoftDeletes;

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
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
