<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for importing and syncing orders from external database.
 * Handles idempotent updates and relationship management.
 */
final class OrderImportService
{
    public function __construct(
        private readonly CustomerImportService $customerService,
        private readonly AddressImportService $addressService,
        private readonly OrderItemImportService $orderItemService,
    ) {}

    /**
     * Import orders from external data.
     *
     * @param  array<int, array<string, mixed>>  $ordersData
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function import(array $ordersData): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        foreach ($ordersData as $orderData) {
            try {
                DB::beginTransaction();

                $result = $this->importSingleOrder($orderData);
                $stats[$result]++;

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $stats['errors']++;
                Log::error('Failed to import order', [
                    'OrderID' => $orderData['OrderID'] ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Import single order.
     *
     * @param  array<string, mixed>  $orderData
     * @return 'created'|'updated'|'skipped'
     */
    private function importSingleOrder(array $orderData): string
    {
        $externalOrderId = $orderData['OrderID'] ?? null;

        if ($externalOrderId === null) {
            throw new \InvalidArgumentException('OrderID is required');
        }

        // Verify Product exists (BrandID)
        $brandId = $orderData['BrandID'] ?? null;
        if ($brandId !== null) {
            $product = Product::query()->where('ProductID', $brandId)->first();
            if ($product === null) {
                Log::warning('Order references non-existent Product', [
                    'OrderID' => $externalOrderId,
                    'BrandID' => $brandId,
                ]);

                return 'skipped';
            }
        }

        // Import customer
        $customer = $this->customerService->importFromOrder($orderData);

        // Find or create order
        $order = Order::query()
            ->where('OrderID', $externalOrderId)
            ->first();

        $isNew = $order === null;

        if ($isNew) {
            $order = $this->createOrder($orderData, $customer);
            Log::info('Created new order', ['order_id' => $order->id, 'OrderID' => $externalOrderId]);
        } else {
            $updated = $this->updateOrderIfChanged($order, $orderData, $customer);
            if ($updated) {
                Log::info('Updated order', ['order_id' => $order->id, 'OrderID' => $externalOrderId]);
            }
        }

        // Import addresses
        $this->addressService->importFromOrder($orderData, $customer, $order->id);

        // Import order items
        $items = $orderData['items'] ?? [];
        if (! empty($items)) {
            $this->orderItemService->import($items, $order);
        }

        return $isNew ? 'created' : 'updated';
    }

    /**
     * Create new order.
     *
     * @param  array<string, mixed>  $orderData
     */
    private function createOrder(array $orderData, ?\App\Models\Customer $customer): Order
    {
        // Convert OrderDate from Ymd to date format
        $orderDate = $this->parseOrderDate($orderData['OrderDate'] ?? null);
        $created = $this->parseDateTime($orderData['Created'] ?? null);

        return Order::create([
            'OrderID' => $orderData['OrderID'],
            'Agent' => $orderData['Agent'] ?? '',
            'Created' => $created,
            'OrderDate' => $orderDate,
            'OrderNum' => $orderData['OrderNum'] ?? '',
            'ProductTotal' => $orderData['ProductTotal'] ?? 0,
            'GrandTotal' => $orderData['GrandTotal'] ?? 0,
            'RefundAmount' => $orderData['RefundAmount'] ?? 0,
            'Shipping' => $orderData['Shipping'] ?? null,
            'ShippingMethod' => $orderData['ShippingMethod'] ?? null,
            'Refund' => (bool) ($orderData['Refund'] ?? false),
            'customer_id' => $customer?->id,
            'BrandID' => $orderData['BrandID'] ?? null,
        ]);
    }

    /**
     * Update order if data has changed.
     *
     * @param  array<string, mixed>  $orderData
     */
    private function updateOrderIfChanged(Order $order, array $orderData, ?\App\Models\Customer $customer): bool
    {
        $changed = false;

        $orderDate = $this->parseOrderDate($orderData['OrderDate'] ?? null);
        $created = $this->parseDateTime($orderData['Created'] ?? null);

        $fields = [
            'Agent' => $orderData['Agent'] ?? '',
            'Created' => $created,
            'OrderDate' => $orderDate,
            'OrderNum' => $orderData['OrderNum'] ?? '',
            'ProductTotal' => $orderData['ProductTotal'] ?? 0,
            'GrandTotal' => $orderData['GrandTotal'] ?? 0,
            'RefundAmount' => $orderData['RefundAmount'] ?? 0,
            'Shipping' => $orderData['Shipping'] ?? null,
            'ShippingMethod' => $orderData['ShippingMethod'] ?? null,
            'Refund' => (bool) ($orderData['Refund'] ?? false),
            'customer_id' => $customer?->id,
            'BrandID' => $orderData['BrandID'] ?? null,
        ];

        foreach ($fields as $field => $value) {
            if ($value != $order->$field) {
                $order->$field = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $order->save();
        }

        return $changed;
    }

    /**
     * Parse OrderDate from Ymd format (e.g., 20170102) to Carbon date.
     */
    private function parseOrderDate(mixed $orderDate): ?Carbon
    {
        if ($orderDate === null) {
            return null;
        }

        // Handle Ymd format (integer or string)
        $dateStr = (string) $orderDate;
        if (strlen($dateStr) === 8 && ctype_digit($dateStr)) {
            return Carbon::createFromFormat('Ymd', $dateStr);
        }

        // Try standard date parsing
        try {
            return Carbon::parse($orderDate);
        } catch (\Exception $e) {
            Log::warning('Failed to parse OrderDate', ['date' => $orderDate]);

            return null;
        }
    }

    /**
     * Parse Created datetime string.
     */
    private function parseDateTime(mixed $datetime): ?Carbon
    {
        if ($datetime === null) {
            return null;
        }

        try {
            return Carbon::parse($datetime);
        } catch (\Exception $e) {
            Log::warning('Failed to parse Created datetime', ['datetime' => $datetime]);

            return null;
        }
    }
}
