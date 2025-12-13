<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductItem;
use Illuminate\Support\Facades\Log;

/**
 * Service for importing and syncing order items from external database.
 * Handles idempotent updates and product item verification.
 */
final class OrderItemImportService
{
    /**
     * Import order items for an order.
     *
     * @param  array<int, array<string, mixed>>  $itemsData
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(array $itemsData, Order $order): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($itemsData as $itemData) {
            try {
                $result = $this->importSingleItem($itemData, $order);
                $stats[$result]++;
            } catch (\Exception $e) {
                $stats['skipped']++;
                Log::error('Failed to import order item', [
                    'idOrderItem' => $itemData['idOrderItem'] ?? null,
                    'OrderID' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Import single order item.
     *
     * @param  array<string, mixed>  $itemData
     * @return 'created'|'updated'|'skipped'
     */
    private function importSingleItem(array $itemData, Order $order): string
    {
        $externalItemId = $itemData['idOrderItem'] ?? null;
        $itemId = $itemData['ItemID'] ?? null;

        if ($externalItemId === null || $itemId === null) {
            throw new \InvalidArgumentException('idOrderItem and ItemID are required');
        }

        // Verify ProductItem exists
        $productItem = ProductItem::query()->where('ItemID', $itemId)->first();
        if ($productItem === null) {
            Log::warning('OrderItem references non-existent ProductItem', [
                'idOrderItem' => $externalItemId,
                'ItemID' => $itemId,
                'OrderID' => $order->id,
            ]);

            return 'skipped';
        }

        // Find or create order item
        $orderItem = OrderItem::query()
            ->where('idOrderItem', $externalItemId)
            ->first();

        $isNew = $orderItem === null;

        if ($isNew) {
            $orderItem = $this->createOrderItem($itemData, $order);
            Log::info('Created new order item', [
                'order_item_id' => $orderItem->idOrderItem,
                'OrderID' => $order->id,
            ]);
        } else {
            $updated = $this->updateOrderItemIfChanged($orderItem, $itemData, $order);
            if ($updated) {
                Log::info('Updated order item', [
                    'order_item_id' => $orderItem->idOrderItem,
                    'OrderID' => $order->id,
                ]);
            }
        }

        return $isNew ? 'created' : 'updated';
    }

    /**
     * Create new order item.
     *
     * @param  array<string, mixed>  $itemData
     */
    private function createOrderItem(array $itemData, Order $order): OrderItem
    {
        return OrderItem::create([
            'idOrderItem' => $itemData['idOrderItem'],
            'Price' => $itemData['Price'] ?? 0,
            'Qty' => $itemData['Qty'] ?? 0,
            'OrderID' => $order->id,
            'ItemID' => $itemData['ItemID'],
        ]);
    }

    /**
     * Update order item if data has changed.
     *
     * @param  array<string, mixed>  $itemData
     */
    private function updateOrderItemIfChanged(OrderItem $orderItem, array $itemData, Order $order): bool
    {
        $changed = false;

        $fields = [
            'Price' => $itemData['Price'] ?? 0,
            'Qty' => $itemData['Qty'] ?? 0,
            'OrderID' => $order->id,
            'ItemID' => $itemData['ItemID'],
        ];

        foreach ($fields as $field => $value) {
            if ($value != $orderItem->$field) {
                $orderItem->$field = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $orderItem->save();
        }

        return $changed;
    }
}
