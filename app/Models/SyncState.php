<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SyncState extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'entity_type',
        'last_order_date',
        'last_expense_date',
        'last_external_order_id',
        'last_external_expense_id',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'last_order_date' => 'date',
            'last_expense_date' => 'date',
            'last_sync_at' => 'datetime',
        ];
    }

    /**
     * Get or create sync state for entity type.
     */
    public static function getOrCreateFor(string $entityType): self
    {
        return self::firstOrCreate(
            ['entity_type' => $entityType],
            [
                'last_order_date' => null,
                'last_expense_date' => null,
                'last_external_order_id' => null,
                'last_external_expense_id' => null,
                'last_sync_at' => null,
            ]
        );
    }

    /**
     * Update sync state for orders.
     */
    public function updateOrderSync(?string $orderDate, ?int $orderId): void
    {
        $this->last_order_date = $orderDate ? \Carbon\Carbon::parse($orderDate) : null;
        $this->last_external_order_id = $orderId;
        $this->last_sync_at = now();
        $this->save();
    }

    /**
     * Update sync state for expenses.
     */
    public function updateExpenseSync(?string $expenseDate, ?int $expenseId): void
    {
        $this->last_expense_date = $expenseDate ? \Carbon\Carbon::parse($expenseDate) : null;
        $this->last_external_expense_id = $expenseId;
        $this->last_sync_at = now();
        $this->save();
    }
}
