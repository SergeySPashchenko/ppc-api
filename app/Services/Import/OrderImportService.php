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

        // Determine if this is a marketplace/FBA order
        $isMarketplace = $this->isMarketplaceOrder($orderData);

        // Determine if contact info is missing
        $hasMissingContactInfo = $this->hasMissingContactInfo($orderData);

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
        $isPartialRefund = $isRefunded && (float) $refundAmount < (float) ($orderData['GrandTotal'] ?? 0) && (float) ($orderData['GrandTotal'] ?? 0) > 0;

        return Order::create([
            'OrderID' => $orderData['OrderID'],
            'Agent' => $orderData['Agent'] ?? '',
            'Created' => $created,
            'OrderDate' => $orderDate,
            'OrderNum' => $orderData['OrderNum'] ?? '',
            'OrderN' => $orderData['OrderN'] ?? null,
            'ProductTotal' => $orderData['ProductTotal'] ?? 0,
            'GrandTotal' => $orderData['GrandTotal'] ?? 0,
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
     * Update order if data has changed.
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
        $isPartialRefund = $isRefunded && (float) $refundAmount < (float) ($orderData['GrandTotal'] ?? 0) && (float) ($orderData['GrandTotal'] ?? 0) > 0;

        $fields = [
            'Agent' => $orderData['Agent'] ?? '',
            'Created' => $created,
            'OrderDate' => $orderDate,
            'OrderNum' => $orderData['OrderNum'] ?? '',
            'OrderN' => $orderData['OrderN'] ?? null,
            'ProductTotal' => $orderData['ProductTotal'] ?? 0,
            'GrandTotal' => $orderData['GrandTotal'] ?? 0,
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
            // Boolean fields - compare strictly
            if ($field === 'is_refunded' || $field === 'is_partial_refund' || $field === 'refund_amount_is_valid') {
                if ((bool) $value !== (bool) $order->$field) {
                    $order->$field = (bool) $value;
                    $changed = true;
                }
            } elseif ($value != $order->$field) {
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
     * Normalize decimal value to string format.
     */
    private function normalizeDecimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);
        if ($cleaned !== '' && is_numeric($cleaned)) {
            return number_format((float) $cleaned, 2, '.', '');
        }

        return '0.00';
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
}
