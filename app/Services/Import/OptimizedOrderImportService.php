<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Order;
use App\Models\SyncState;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Optimized service for importing and syncing orders.
 * - Creates Product/Brand if missing
 * - Updates sync_state incrementally
 * - Processes in chunks for memory efficiency
 */
final class OptimizedOrderImportService
{
    public function __construct(
        private readonly CustomerImportService $customerService,
        private readonly AddressImportService $addressService,
        private readonly OrderItemImportService $orderItemService,
        private readonly ProductSyncService $productService,
    ) {}

    /**
     * Import orders incrementally (only new/changed).
     *
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function importIncremental(): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $syncState = SyncState::getOrCreateFor('orders');
        $lastDate = $syncState->last_order_date;
        $lastId = $syncState->last_external_order_id;

        $repository = app(OptimizedExternalRepository::class);
        $processedCount = 0;

        try {
            foreach ($repository->getOrdersIncremental($lastDate, $lastId) as $orderData) {
                try {
                    DB::beginTransaction();

                    $result = $this->importSingleOrder($orderData);
                    $stats[$result]++;
                    $processedCount++;

                    // Checkpoint every 100 records
                    if ($processedCount % 100 === 0) {
                        $this->updateSyncState($orderData);
                        DB::commit();
                        DB::beginTransaction();
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $stats['errors']++;
                    Log::error('Failed to import order', [
                        'OrderID' => $orderData['OrderID'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Final sync state update
            if ($processedCount > 0) {
                $syncState->refresh();
            }
        } catch (\Exception $e) {
            Log::error('Incremental order import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $stats['errors']++;
        }

        return $stats;
    }

    /**
     * Import orders for date range with windowing.
     *
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function importByDateRange(Carbon $from, Carbon $to): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $repository = app(OptimizedExternalRepository::class);
        $processedCount = 0;
        $lastProcessedDate = null;
        $lastProcessedId = null;

        try {
            foreach ($repository->getOrdersByDateRange($from, $to) as $orderData) {
                try {
                    DB::beginTransaction();

                    $result = $this->importSingleOrder($orderData);
                    $stats[$result]++;
                    $processedCount++;

                    // Parse OrderDate from Ymd format
                    $orderDateYmd = (int) ($orderData['OrderDate'] ?? 0);
                    if ($orderDateYmd > 0) {
                        $lastProcessedDate = Carbon::createFromFormat('Ymd', (string) $orderDateYmd);
                        $lastProcessedId = (int) ($orderData['OrderID'] ?? 0);
                    }

                    // Checkpoint every 100 records
                    if ($processedCount % 100 === 0 && $lastProcessedDate !== null) {
                        $this->updateSyncStateFromData($lastProcessedDate, $lastProcessedId);
                        DB::commit();
                        DB::beginTransaction();
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $stats['errors']++;
                    Log::error('Failed to import order', [
                        'OrderID' => $orderData['OrderID'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Final sync state update
            if ($lastProcessedDate !== null) {
                $this->updateSyncStateFromData($lastProcessedDate, $lastProcessedId);
            }
        } catch (\Exception $e) {
            Log::error('Date range order import failed', [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);
            $stats['errors']++;
        }

        return $stats;
    }

    /**
     * Import last N orders.
     *
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function importLast(int $limit): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $repository = app(OptimizedExternalRepository::class);
        $orders = $repository->getLastOrders($limit);

        foreach ($orders as $orderData) {
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

        // Sync Product (create if missing) - use BrandID from order
        $brandId = $orderData['BrandID'] ?? null;
        if ($brandId !== null) {
            $product = $this->productService->syncProduct([
                'ProductID' => $brandId,
                'Brand' => null, // Brand will be synced from product data if needed
            ]);

            if ($product === null) {
                Log::warning('Failed to sync product for order', [
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
        } else {
            $updated = $this->updateOrderIfChanged($order, $orderData, $customer);
            if (! $updated) {
                return 'skipped';
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
     * Update order if data changed.
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
     * Parse OrderDate from Ymd format.
     */
    private function parseOrderDate(mixed $orderDate): ?Carbon
    {
        if ($orderDate === null) {
            return null;
        }

        $dateStr = (string) $orderDate;
        if (strlen($dateStr) === 8 && ctype_digit($dateStr)) {
            return Carbon::createFromFormat('Ymd', $dateStr);
        }

        try {
            return Carbon::parse($orderDate);
        } catch (\Exception $e) {
            Log::warning('Failed to parse OrderDate', ['date' => $orderDate]);

            return null;
        }
    }

    /**
     * Parse Created datetime.
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

    /**
     * Update sync state from order data.
     *
     * @param  array<string, mixed>  $orderData
     */
    private function updateSyncState(array $orderData): void
    {
        $orderDateYmd = (int) ($orderData['OrderDate'] ?? 0);
        if ($orderDateYmd > 0) {
            $orderDate = Carbon::createFromFormat('Ymd', (string) $orderDateYmd);
            $orderId = (int) ($orderData['OrderID'] ?? 0);
            $this->updateSyncStateFromData($orderDate, $orderId);
        }
    }

    /**
     * Update sync state from date and ID.
     */
    private function updateSyncStateFromData(Carbon $orderDate, ?int $orderId): void
    {
        $syncState = SyncState::getOrCreateFor('orders');
        $syncState->updateOrderSync($orderDate->format('Y-m-d'), $orderId);
    }
}
