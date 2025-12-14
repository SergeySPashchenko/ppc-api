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
     * Optimized for large datasets by processing in chunks.
     *
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function importLast(int $limit): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $repository = app(OptimizedExternalRepository::class);
        $orders = $repository->getLastOrders($limit);

        // Process in chunks to reduce memory usage and database load
        $chunkSize = 50;
        $processed = 0;

        foreach (array_chunk($orders, $chunkSize) as $chunk) {
            foreach ($chunk as $orderData) {
                try {
                    DB::beginTransaction();
                    $result = $this->importSingleOrder($orderData);
                    $stats[$result]++;
                    DB::commit();
                    $processed++;

                    // Log progress every 50 records
                    if ($processed % 50 === 0) {
                        Log::info('Import progress', [
                            'processed' => $processed,
                            'total' => $limit,
                            'stats' => $stats,
                        ]);
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    $stats['errors']++;
                    Log::error('Failed to import order', [
                        'OrderID' => $orderData['OrderID'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Small delay between chunks to reduce database load
            if (count($chunk) === $chunkSize) {
                usleep(100000); // 0.1 second delay
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
            try {
                $product = $this->productService->syncProduct([
                    'ProductID' => $brandId,
                    'Product' => $orderData['ProductName'] ?? null,
                    'Brand' => $orderData['ProductBrand'] ?? null,
                ]);

                // In optimized mode, syncProduct should always create product if missing
                // If it returns null, something went wrong
                if ($product === null) {
                    Log::error('Failed to sync product for order - syncProduct returned null', [
                        'OrderID' => $externalOrderId,
                        'BrandID' => $brandId,
                    ]);
                    // Don't skip - continue with order import even if product sync failed
                    // The order will have BrandID set, and product can be created later
                }
            } catch (\Exception $e) {
                Log::error('Exception syncing product for order', [
                    'OrderID' => $externalOrderId,
                    'BrandID' => $brandId,
                    'error' => $e->getMessage(),
                ]);
                // Don't skip - continue with order import even if product sync failed
                // The order will have BrandID set, and product can be created later
            }
        }

        // Import customer
        $customer = $this->customerService->importFromOrder($orderData);

        // Find or create order - use DB query first to avoid Eloquent scope issues
        $orderId = DB::table('orders')
            ->where('OrderID', $externalOrderId)
            ->value('id');

        $isNew = $orderId === null;

        if ($isNew) {
            try {
                $order = $this->createOrder($orderData, $customer);
            } catch (\Illuminate\Database\QueryException $e) {
                // If order was created concurrently, try to find it
                if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'UNIQUE constraint')) {
                    $orderId = DB::table('orders')
                        ->where('OrderID', $externalOrderId)
                        ->value('id');
                    if ($orderId !== null) {
                        $order = Order::find($orderId);
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
            if (! isset($order)) {
                // Use withoutGlobalScopes to avoid any scope filtering
                $order = Order::withoutGlobalScopes()->find($orderId);
            }
            if ($order === null) {
                Log::error('Order not found after ID lookup', [
                    'OrderID' => $externalOrderId,
                    'order_id' => $orderId,
                ]);
                throw new \RuntimeException("Order with OrderID {$externalOrderId} not found");
            }
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

        // Determine if this is a marketplace/FBA order
        $isMarketplace = $this->isMarketplaceOrder($orderData);

        // Determine if contact info is missing
        $hasMissingContactInfo = $this->hasMissingContactInfo($orderData);

        $productTotal = $this->normalizeDecimal($orderData['ProductTotal'] ?? 0);
        $grandTotal = $this->normalizeDecimal($orderData['GrandTotal'] ?? 0);

        // Store original RefundAmount before normalization
        $refundAmountRaw = $orderData['RefundAmount'] ?? null;
        $refundAmount = $this->normalizeDecimal($refundAmountRaw ?? 0);

        // Validate RefundAmount
        $refundAmountIsValid = $this->isValidRefundAmount($refundAmountRaw);

        // Store original Refund type (can be string like "*Cancelled", "*Refund", etc.)
        $refundType = is_string($orderData['Refund'] ?? null) ? $orderData['Refund'] : null;
        $refund = (bool) ($orderData['Refund'] ?? false);

        // Calculate refund flags
        $isRefunded = (float) $refundAmount > 0;
        $isPartialRefund = $isRefunded && (float) $refundAmount < (float) $grandTotal && (float) $grandTotal > 0;

        Log::debug('Creating order with normalized values', [
            'OrderID' => $orderData['OrderID'],
            'ProductTotal' => $productTotal,
            'GrandTotal' => $grandTotal,
            'RefundAmount' => $refundAmount,
            'ProductTotal_raw' => $orderData['ProductTotal'] ?? 'not set',
            'GrandTotal_raw' => $orderData['GrandTotal'] ?? 'not set',
            'RefundAmount_raw' => $refundAmountRaw ?? 'not set',
        ]);

        return Order::create([
            'OrderID' => $orderData['OrderID'],
            'Agent' => $orderData['Agent'] ?? '',
            'Created' => $created,
            'OrderDate' => $orderDate,
            'OrderNum' => $orderData['OrderNum'] ?? '',
            'OrderN' => $orderData['OrderN'] ?? null,
            'ProductTotal' => $productTotal,
            'GrandTotal' => $grandTotal,
            'RefundAmount' => $refundAmount,
            'refund_amount_raw' => $refundAmountRaw !== null ? (string) $refundAmountRaw : null,
            'refund_amount_is_valid' => $refundAmountIsValid,
            'Shipping' => $orderData['Shipping'] ?? null,
            'ShippingMethod' => $orderData['ShippingMethod'] ?? null,
            'Refund' => $refund,
            'refund_type' => $refundType,
            'is_refunded' => $isRefunded,
            'is_partial_refund' => $isPartialRefund,
            'is_marketplace' => $isMarketplace,
            'has_missing_contact_info' => $hasMissingContactInfo,
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

        // Determine if this is a marketplace/FBA order
        $isMarketplace = $this->isMarketplaceOrder($orderData);

        // Determine if contact info is missing
        $hasMissingContactInfo = $this->hasMissingContactInfo($orderData);

        $productTotal = $this->normalizeDecimal($orderData['ProductTotal'] ?? 0);
        $grandTotal = $this->normalizeDecimal($orderData['GrandTotal'] ?? 0);

        // Store original RefundAmount before normalization
        $refundAmountRaw = $orderData['RefundAmount'] ?? null;
        $refundAmount = $this->normalizeDecimal($refundAmountRaw ?? 0);

        // Validate RefundAmount
        $refundAmountIsValid = $this->isValidRefundAmount($refundAmountRaw);

        // Store original Refund type (can be string like "*Cancelled", "*Refund", etc.)
        $refundType = is_string($orderData['Refund'] ?? null) ? $orderData['Refund'] : null;
        $refund = (bool) ($orderData['Refund'] ?? false);

        // Calculate refund flags
        $isRefunded = (float) $refundAmount > 0;
        $isPartialRefund = $isRefunded && (float) $refundAmount < (float) $grandTotal && (float) $grandTotal > 0;

        $fields = [
            'Agent' => $orderData['Agent'] ?? '',
            'Created' => $created,
            'OrderDate' => $orderDate,
            'OrderNum' => $orderData['OrderNum'] ?? '',
            'OrderN' => $orderData['OrderN'] ?? null,
            'ProductTotal' => $productTotal,
            'GrandTotal' => $grandTotal,
            'RefundAmount' => $refundAmount,
            'refund_amount_raw' => $refundAmountRaw !== null ? (string) $refundAmountRaw : null,
            'refund_amount_is_valid' => $refundAmountIsValid,
            'Shipping' => $orderData['Shipping'] ?? null,
            'ShippingMethod' => $orderData['ShippingMethod'] ?? null,
            'Refund' => $refund,
            'refund_type' => $refundType,
            'is_refunded' => $isRefunded,
            'is_partial_refund' => $isPartialRefund,
            'is_marketplace' => $isMarketplace,
            'has_missing_contact_info' => $hasMissingContactInfo,
            'customer_id' => $customer?->id,
            'BrandID' => $orderData['BrandID'] ?? null,
        ];

        foreach ($fields as $field => $value) {
            try {
                // Compare normalized decimal values properly
                if ($field === 'ProductTotal' || $field === 'GrandTotal' || $field === 'RefundAmount') {
                    $currentValue = is_numeric($order->getRawOriginal($field)) ? (float) $order->getRawOriginal($field) : 0.0;
                    $newValue = is_numeric($value) ? (float) $value : 0.0;
                    if (abs($newValue - $currentValue) > 0.01) {
                        // Use setAttribute to bypass cast temporarily, then set the raw value
                        $order->setRawAttributes(array_merge($order->getAttributes(), [$field => $newValue]), true);
                        $changed = true;
                    }
                } elseif ($field === 'is_refunded' || $field === 'is_partial_refund') {
                    // Boolean fields - compare strictly
                    if ((bool) $value !== (bool) $order->$field) {
                        $order->$field = (bool) $value;
                        $changed = true;
                    }
                } elseif ($value != $order->$field) {
                    $order->$field = $value;
                    $changed = true;
                }
            } catch (\Exception $e) {
                Log::error('Error updating order field', [
                    'OrderID' => $order->OrderID,
                    'field' => $field,
                    'value' => $value,
                    'value_type' => gettype($value),
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        if ($changed) {
            try {
                // Use query builder to update decimal fields to avoid casting issues
                $updateData = [];
                foreach ($fields as $field => $value) {
                    if ($field === 'ProductTotal' || $field === 'GrandTotal' || $field === 'RefundAmount') {
                        $updateData[$field] = is_numeric($value) ? (float) $value : 0.0;
                    } else {
                        $updateData[$field] = $value;
                    }
                }

                \Illuminate\Support\Facades\DB::table('orders')
                    ->where('id', $order->id)
                    ->update($updateData);

                // Refresh the model
                $order->refresh();
            } catch (\Exception $e) {
                Log::error('Error saving order', [
                    'OrderID' => $order->OrderID,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        }

        return $changed;
    }

    /**
     * Determine if order is from marketplace/FBA.
     *
     * @param  array<string, mixed>  $orderData
     */
    private function isMarketplaceOrder(array $orderData): bool
    {
        $email = trim((string) ($orderData['Email'] ?? ''));
        $name = trim((string) ($orderData['Name'] ?? ''));
        $phone = trim((string) ($orderData['Phone'] ?? ''));

        // Marketplace/FBA orders typically have no email, name, or phone
        // Or have specific patterns (e.g., Amazon FBA)
        if (empty($email) && empty($name) && empty($phone)) {
            return true;
        }

        // Check for common marketplace indicators
        $agent = strtolower(trim((string) ($orderData['Agent'] ?? '')));
        if (str_contains($agent, 'amazon') || str_contains($agent, 'fba') || str_contains($agent, 'marketplace')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if order has missing contact information.
     *
     * @param  array<string, mixed>  $orderData
     */
    private function hasMissingContactInfo(array $orderData): bool
    {
        $email = trim((string) ($orderData['Email'] ?? ''));
        $name = trim((string) ($orderData['Name'] ?? ''));
        $phone = trim((string) ($orderData['Phone'] ?? ''));

        return empty($email) || empty($name) || empty($phone);
    }

    /**
     * Validate RefundAmount value.
     * Returns true if value is numeric or can be parsed as decimal.
     */
    private function isValidRefundAmount(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true; // Empty/null is valid (means no refund)
        }

        if (is_numeric($value)) {
            return true;
        }

        // Try to clean and parse string values like "99.80+102." or "sent retur"
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);
        if ($cleaned !== '' && is_numeric($cleaned)) {
            return true;
        }

        return false;
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
            return Carbon::now();
        }

        // Handle Unix timestamp (numeric string or integer)
        if (is_numeric($datetime)) {
            try {
                return Carbon::createFromTimestamp((int) $datetime);
            } catch (\Exception $e) {
                Log::warning('Failed to parse Created datetime as timestamp', ['datetime' => $datetime]);

                return Carbon::now();
            }
        }

        try {
            return Carbon::parse($datetime);
        } catch (\Exception $e) {
            Log::warning('Failed to parse Created datetime', ['datetime' => $datetime]);

            return Carbon::now();
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

    /**
     * Normalize decimal value for database storage.
     * Returns string to avoid casting issues with Eloquent decimal fields.
     */
    private function normalizeDecimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        // Try to extract numeric value from string
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);
        if ($cleaned !== '' && is_numeric($cleaned)) {
            return number_format((float) $cleaned, 2, '.', '');
        }

        return '0.00';
    }
}
