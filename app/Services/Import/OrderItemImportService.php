<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductItem;
use Illuminate\Support\Facades\DB;
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

        // Validate item data
        $validation = $this->validateOrderItem($itemData);

        if (! $validation['is_valid']) {
            Log::warning('Invalid order item detected', [
                'idOrderItem' => $externalItemId,
                'OrderID' => $order->id,
                'ItemID' => $itemId,
                'price' => $validation['price'],
                'qty' => $validation['qty'],
                'errors' => $validation['errors'],
            ]);

            // Still import but mark as invalid for audit
        }

        // Find or create order item - use DB query first to avoid Eloquent scope issues
        $orderItemId = \Illuminate\Support\Facades\DB::table('order_items')
            ->where('idOrderItem', $externalItemId)
            ->value('id');

        $isNew = $orderItemId === null;

        if ($isNew) {
            try {
                $orderItem = $this->createOrderItem($itemData, $order);
            } catch (\Illuminate\Database\QueryException $e) {
                // If order item was created concurrently, try to find it
                if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'UNIQUE constraint')) {
                    $orderItemId = \Illuminate\Support\Facades\DB::table('order_items')
                        ->where('idOrderItem', $externalItemId)
                        ->value('id');
                    if ($orderItemId !== null) {
                        $orderItem = OrderItem::withoutGlobalScopes()->find($orderItemId);
                        $isNew = false;
                    } else {
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            }
        }

        if (! $isNew) {
            if (! isset($orderItem)) {
                $orderItem = OrderItem::withoutGlobalScopes()->find($orderItemId);
            }
            Log::info('Created new order item', [
                'order_item_id' => $orderItem->idOrderItem,
                'OrderID' => $order->id,
                'is_valid' => $orderItem->is_valid,
            ]);
        } else {
            $updated = $this->updateOrderItemIfChanged($orderItem, $itemData, $order);
            if ($updated) {
                Log::info('Updated order item', [
                    'order_item_id' => $orderItem->idOrderItem,
                    'OrderID' => $order->id,
                    'is_valid' => $orderItem->is_valid,
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
        $validation = $this->validateOrderItem($itemData);
        $lineTotal = $validation['is_valid']
            ? $validation['price'] * $validation['qty']
            : 0;

        return OrderItem::create([
            'idOrderItem' => $itemData['idOrderItem'],
            'Price' => $validation['price'],
            'Qty' => $validation['qty'],
            'price_raw' => $itemData['Price'] ?? null,
            'qty_raw' => $itemData['Qty'] ?? null,
            'line_total' => $lineTotal,
            'is_valid' => $validation['is_valid'],
            'validation_errors' => ! empty($validation['errors'])
                ? implode('; ', $validation['errors'])
                : null,
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
        $validation = $this->validateOrderItem($itemData);
        $lineTotal = $validation['is_valid']
            ? $validation['price'] * $validation['qty']
            : 0;

        $changed = false;

        $fields = [
            'Price' => $validation['price'],
            'Qty' => $validation['qty'],
            'price_raw' => $itemData['Price'] ?? null,
            'qty_raw' => $itemData['Qty'] ?? null,
            'line_total' => $lineTotal,
            'is_valid' => $validation['is_valid'],
            'validation_errors' => ! empty($validation['errors'])
                ? implode('; ', $validation['errors'])
                : null,
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

    /**
     * Validate order item data.
     *
     * @param  array<string, mixed>  $itemData
     * @return array{is_valid: bool, errors: array<int, string>, price: float, qty: int}
     */
    private function validateOrderItem(array $itemData): array
    {
        $price = (float) ($itemData['Price'] ?? 0);
        $qty = (int) ($itemData['Qty'] ?? 0);

        $errors = [];
        $isValid = true;

        // Validate price
        if ($price < 0) {
            $errors[] = "Price cannot be negative: {$price}";
            $isValid = false;
        } elseif ($price == 0) {
            $errors[] = 'Price is zero (may be free item or error)';
            // Allow zero price for free items, but mark as potentially invalid
        }

        // Validate quantity
        if ($qty <= 0) {
            $errors[] = "Qty must be positive: {$qty}";
            $isValid = false;
        }

        // Validate extreme values (warnings, not errors)
        if ($price > 10000) {
            $errors[] = "Price seems too high: {$price} (possible data error)";
        }

        if ($qty > 1000) {
            $errors[] = "Qty seems too high: {$qty} (possible data error)";
        }

        return [
            'is_valid' => $isValid,
            'errors' => $errors,
            'price' => $price,
            'qty' => $qty,
        ];
    }
}
