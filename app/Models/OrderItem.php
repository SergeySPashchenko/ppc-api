<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $primaryKey = 'idOrderItem';

    protected $fillable = [
        'idOrderItem',
        'Price',
        'Qty',
        'OrderID',
        'ItemID',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'OrderID', 'id');
    }

    /**
     * @return BelongsTo<ProductItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductItem::class, 'ItemID', 'ItemID');
    }

    protected function casts(): array
    {
        return [
            'Price' => 'decimal:2',
            'Qty' => 'integer',
        ];
    }
}
