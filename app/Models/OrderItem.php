<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AccessibleByUserUniversalTrait;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class OrderItem extends Model
{
    use AccessibleByUserUniversalTrait;

    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();
        self::$cacheAccess = true;
    }

    protected $primaryKey = 'idOrderItem';

    protected $fillable = [
        'idOrderItem',
        'Price',
        'Qty',
        'line_total',
        'is_valid',
        'price_raw',
        'qty_raw',
        'validation_errors',
        'OrderID',
        'ItemID',
    ];

    /**
     * Назва батьківського відношення для рекурсії доступу
     */
    protected function parentRelation(): ?string
    {
        return 'item';
    }

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
            'line_total' => 'decimal:2',
            'is_valid' => 'boolean',
            'price_raw' => 'decimal:2',
            'qty_raw' => 'integer',
        ];
    }
}
