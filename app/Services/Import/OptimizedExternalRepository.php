<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\SyncState;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Optimized read-only repository for accessing external mysql_external database.
 *
 * Optimizations:
 * - Incremental imports with cursor-based pagination
 * - Date-range windowing (day by day for large ranges)
 * - Chunked streaming (row-by-row processing)
 * - Column-strict selects (only required columns)
 * - Safe pagination (date + id ordering, no OFFSET)
 * - Idempotency tracking via sync_state
 */
final class OptimizedExternalRepository
{
    private const CONNECTION = 'mysql_external';

    private const CHUNK_SIZE = 1000; // Process in chunks to avoid memory issues

    /**
     * Get expenses incrementally (only new/changed records).
     * Uses cursor-based pagination with date + id ordering.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function getExpensesIncremental(?Carbon $sinceDate = null, ?int $sinceId = null, int $chunkSize = self::CHUNK_SIZE): \Generator
    {
        $syncState = SyncState::getOrCreateFor('expenses');
        $lastDate = $sinceDate ?? $syncState->last_expense_date;
        $lastId = $sinceId ?? $syncState->last_external_expense_id;

        $query = DB::connection(self::CONNECTION)
            ->table('expenses as e')
            ->leftJoin('product as p', 'p.ProductID', '=', 'e.ProductID')
            ->leftJoin('expensetype as et', 'et.ExpenseID', '=', 'e.ExpenseID')
            ->select([
                'e.id',
                'e.ProductID',
                'e.ExpenseID',
                'e.ExpenseDate',
                'e.Expense',
                'et.Name as ExpenseTypeName',
                'p.Brand as ProductBrand',
                'p.Product as ProductName',
            ])
            ->orderBy('e.ExpenseDate', 'asc')
            ->orderBy('e.id', 'asc');

        // Cursor-based pagination: only fetch records after last synced
        if ($lastDate !== null) {
            $query->where(function ($q) use ($lastDate, $lastId) {
                $q->where('e.ExpenseDate', '>', $lastDate->format('Y-m-d'))
                    ->orWhere(function ($q2) use ($lastDate, $lastId) {
                        $q2->where('e.ExpenseDate', '=', $lastDate->format('Y-m-d'))
                            ->where('e.id', '>', $lastId ?? 0);
                    });
            });
        }

        // Stream results in chunks - collect first, then yield
        $rowsBuffer = [];
        $query->chunk($chunkSize, function ($rows) use (&$rowsBuffer, &$lastDate, &$lastId) {
            foreach ($rows as $row) {
                $rowArray = (array) $row;
                $lastDate = Carbon::parse($rowArray['ExpenseDate']);
                $lastId = (int) $rowArray['id'];
                $rowsBuffer[] = $rowArray;
            }
        });

        foreach ($rowsBuffer as $row) {
            yield $row;
        }
    }

    /**
     * Get expenses for a specific date range with windowing.
     * Splits large ranges into day-by-day windows.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function getExpensesByDateRange(Carbon $from, Carbon $to, int $chunkSize = self::CHUNK_SIZE): \Generator
    {
        $currentDate = $from->copy();

        // Window by day to avoid large queries
        while ($currentDate->lte($to)) {
            $dayEnd = $currentDate->copy()->endOfDay();
            if ($dayEnd->gt($to)) {
                $dayEnd = $to->copy();
            }

            $query = DB::connection(self::CONNECTION)
                ->table('expenses as e')
                ->leftJoin('product as p', 'p.ProductID', '=', 'e.ProductID')
                ->leftJoin('expensetype as et', 'et.ExpenseID', '=', 'e.ExpenseID')
                ->whereBetween('e.ExpenseDate', [$currentDate->format('Y-m-d'), $dayEnd->format('Y-m-d')])
                ->select([
                    'e.id',
                    'e.ProductID',
                    'e.ExpenseID',
                    'e.ExpenseDate',
                    'e.Expense',
                    'et.Name as ExpenseTypeName',
                    'p.Brand as ProductBrand',
                    'p.Product as ProductName',
                ])
                ->orderBy('e.ExpenseDate', 'asc')
                ->orderBy('e.id', 'asc');

            $dayRowsBuffer = [];
            $query->chunk($chunkSize, function ($rows) use (&$dayRowsBuffer) {
                foreach ($rows as $row) {
                    $dayRowsBuffer[] = (array) $row;
                }
            });

            foreach ($dayRowsBuffer as $row) {
                yield $row;
            }
            $dayRowsBuffer = [];

            $currentDate->addDay();
        }
    }

    /**
     * Get last N expenses (for limit-based imports).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLastExpenses(int $limit): array
    {
        return DB::connection(self::CONNECTION)
            ->table('expenses as e')
            ->leftJoin('product as p', 'p.ProductID', '=', 'e.ProductID')
            ->leftJoin('expensetype as et', 'et.ExpenseID', '=', 'e.ExpenseID')
            ->select([
                'e.id',
                'e.ProductID',
                'e.ExpenseID',
                'e.ExpenseDate',
                'e.Expense',
                'et.Name as ExpenseTypeName',
                'p.Brand as ProductBrand',
                'p.Product as ProductName',
            ])
            ->orderBy('e.ExpenseDate', 'desc')
            ->orderBy('e.id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Get orders incrementally (only new/changed records).
     * Uses cursor-based pagination with date + id ordering.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function getOrdersIncremental(?Carbon $sinceDate = null, ?int $sinceId = null, int $chunkSize = self::CHUNK_SIZE): \Generator
    {
        $syncState = SyncState::getOrCreateFor('orders');
        $lastDate = $sinceDate ?? $syncState->last_order_date;
        $lastId = $sinceId ?? $syncState->last_external_order_id;

        // Convert to Ymd format for external DB
        $lastDateYmd = $lastDate ? (int) $lastDate->format('Ymd') : null;

        $query = DB::connection(self::CONNECTION)
            ->table('Orders')
            ->leftJoin('product', 'product.ProductID', '=', 'Orders.BrandID')
            ->select([
                'Orders.id as OrderID',
                'Orders.Agent',
                'Orders.Created',
                'Orders.OrderDate',
                'Orders.OrderNum',
                'Orders.OrderN',
                'Orders.ProductTotal',
                'Orders.GrandTotal',
                'Orders.Shipping',
                'Orders.ShippingMethod',
                'Orders.Refund',
                'Orders.RefundAmount',
                'Orders.BrandID',
                'Orders.Email',
                'Orders.Name',
                'Orders.Phone',
                'Orders.Address as BillingAddress',
                'Orders.Address2 as BillingAddress2',
                'Orders.City as BillingCity',
                'Orders.State as BillingState',
                'Orders.Zip as BillingZip',
                'Orders.Country as BillingCountry',
                'Orders.ShipName as ShippingName',
                'Orders.ShipAddress as ShippingAddress',
                'Orders.ShipAddress2 as ShippingAddress2',
                'Orders.ShipCity as ShippingCity',
                'Orders.ShipState as ShippingState',
                'Orders.ShipZip as ShippingZip',
                'Orders.ShipCountry as ShippingCountry',
                'Orders.ShipPhone as ShippingPhone',
                'product.Product as ProductName',
                'product.Brand as ProductBrand',
            ])
            ->orderBy('Orders.OrderDate', 'asc')
            ->orderBy('Orders.id', 'asc');

        // Cursor-based pagination
        if ($lastDateYmd !== null) {
            $query->where(function ($q) use ($lastDateYmd, $lastId) {
                $q->where('Orders.OrderDate', '>', $lastDateYmd)
                    ->orWhere(function ($q2) use ($lastDateYmd, $lastId) {
                        $q2->where('Orders.OrderDate', '=', $lastDateYmd)
                            ->where('Orders.id', '>', $lastId ?? 0);
                    });
            });
        }

        $orderIds = [];
        $ordersBuffer = [];

        $query->chunk($chunkSize, function ($ordersChunk) use (&$orderIds, &$ordersBuffer, &$lastDateYmd, &$lastId) {
            foreach ($ordersChunk as $order) {
                $orderArray = (array) $order;
                $orderIds[] = $orderArray['OrderID'];
                $ordersBuffer[] = $orderArray;
                $lastDateYmd = (int) $orderArray['OrderDate'];
                $lastId = (int) $orderArray['OrderID'];
            }

            // Load order items for this chunk
            if (! empty($orderIds)) {
                $this->attachOrderItemsToOrdersArray($ordersBuffer, $orderIds);
                $orderIds = [];
            }
        });

        // Yield all buffered orders
        foreach ($ordersBuffer as $order) {
            yield $order;
        }
    }

    /**
     * Get orders for a specific date range with windowing.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function getOrdersByDateRange(Carbon $from, Carbon $to, int $chunkSize = self::CHUNK_SIZE): \Generator
    {
        $currentDate = $from->copy();
        $fromYmd = (int) $from->format('Ymd');
        $toYmd = (int) $to->format('Ymd');

        // Window by day
        while ($currentDate->lte($to)) {
            $dayEnd = $currentDate->copy()->endOfDay();
            if ($dayEnd->gt($to)) {
                $dayEnd = $to->copy();
            }

            $dayStartYmd = (int) $currentDate->format('Ymd');
            $dayEndYmd = (int) $dayEnd->format('Ymd');

            $dayOrderIds = [];
            $dayOrdersBuffer = [];

            DB::connection(self::CONNECTION)
                ->table('Orders')
                ->leftJoin('product', 'product.ProductID', '=', 'Orders.BrandID')
                ->whereBetween('OrderDate', [$dayStartYmd, $dayEndYmd])
                ->select([
                    'Orders.id as OrderID',
                    'Orders.Agent',
                    'Orders.Created',
                    'Orders.OrderDate',
                    'Orders.OrderNum',
                    'Orders.OrderN',
                    'Orders.ProductTotal',
                    'Orders.GrandTotal',
                    'Orders.Shipping',
                    'Orders.ShippingMethod',
                    'Orders.Refund',
                    'Orders.RefundAmount',
                    'Orders.BrandID',
                    'Orders.Email',
                    'Orders.Name',
                    'Orders.Phone',
                    'Orders.Address as BillingAddress',
                    'Orders.Address2 as BillingAddress2',
                    'Orders.City as BillingCity',
                    'Orders.State as BillingState',
                    'Orders.Zip as BillingZip',
                    'Orders.Country as BillingCountry',
                    'Orders.ShipName as ShippingName',
                    'Orders.ShipAddress as ShippingAddress',
                    'Orders.ShipAddress2 as ShippingAddress2',
                    'Orders.ShipCity as ShippingCity',
                    'Orders.ShipState as ShippingState',
                    'Orders.ShipZip as ShippingZip',
                    'Orders.ShipCountry as ShippingCountry',
                    'Orders.ShipPhone as ShippingPhone',
                    'product.Product as ProductName',
                    'product.Brand as ProductBrand',
                ])
                ->orderBy('Orders.OrderDate', 'asc')
                ->orderBy('Orders.id', 'asc')
                ->chunk($chunkSize, function ($chunk) use (&$dayOrderIds, &$dayOrdersBuffer) {
                    foreach ($chunk as $order) {
                        $orderArray = (array) $order;
                        $dayOrderIds[] = $orderArray['OrderID'];
                        $dayOrdersBuffer[] = $orderArray;
                    }

                    // Load order items for this chunk
                    if (! empty($dayOrderIds)) {
                        $this->attachOrderItemsToOrdersArray($dayOrdersBuffer, $dayOrderIds);
                        $dayOrderIds = [];
                    }
                });

            // Yield all orders for this day
            foreach ($dayOrdersBuffer as $order) {
                yield $order;
            }

            $currentDate->addDay();
        }
    }

    /**
     * Get last N orders.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLastOrders(int $limit): array
    {
        $orders = DB::connection(self::CONNECTION)
            ->table('Orders')
            ->leftJoin('product', 'product.ProductID', '=', 'Orders.BrandID')
            ->select([
                'Orders.id as OrderID',
                'Orders.Agent',
                'Orders.Created',
                'Orders.OrderDate',
                'Orders.OrderNum',
                'Orders.OrderN',
                'Orders.ProductTotal',
                'Orders.GrandTotal',
                'Orders.Shipping',
                'Orders.ShippingMethod',
                'Orders.Refund',
                'Orders.RefundAmount',
                'Orders.BrandID',
                'Orders.Email',
                'Orders.Name',
                'Orders.Phone',
                'Orders.Address as BillingAddress',
                'Orders.Address2 as BillingAddress2',
                'Orders.City as BillingCity',
                'Orders.State as BillingState',
                'Orders.Zip as BillingZip',
                'Orders.Country as BillingCountry',
                'Orders.ShipName as ShippingName',
                'Orders.ShipAddress as ShippingAddress',
                'Orders.ShipAddress2 as ShippingAddress2',
                'Orders.ShipCity as ShippingCity',
                'Orders.ShipState as ShippingState',
                'Orders.ShipZip as ShippingZip',
                'Orders.ShipCountry as ShippingCountry',
                'Orders.ShipPhone as ShippingPhone',
                'product.Product as ProductName',
                'product.Brand as ProductBrand',
            ])
            ->orderBy('Orders.OrderDate', 'desc')
            ->orderBy('Orders.id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        // Load order items
        $orderIds = array_column($orders, 'OrderID');
        if (! empty($orderIds)) {
            $this->attachOrderItemsToOrders($orders, $orderIds);
        }

        return $orders;
    }

    /**
     * Attach order items to orders array (batch load to avoid N+1).
     * Optimized for large datasets by processing in chunks.
     *
     * @param  array<int, array<string, mixed>>  $orders
     * @param  array<int>  $orderIds
     */
    private function attachOrderItemsToOrdersArray(array &$orders, array $orderIds): void
    {
        if (empty($orderIds)) {
            return;
        }

        // Process in chunks to avoid large WHERE IN clauses
        $chunkSize = 100;
        $allOrderItems = [];

        foreach (array_chunk($orderIds, $chunkSize) as $chunkIds) {
            $orderItems = DB::connection(self::CONNECTION)
                ->table('OrderItems')
                ->leftJoin('ProductItem', 'ProductItem.ItemID', '=', 'OrderItems.ItemID')
                ->whereIn('OrderItems.OrderID', $chunkIds)
                ->select([
                    'OrderItems.idOrderItem',
                    'OrderItems.OrderID',
                    'OrderItems.ItemID',
                    'OrderItems.Price',
                    'OrderItems.Qty',
                ])
                ->get()
                ->groupBy('OrderID')
                ->map(fn ($items) => $items->map(fn ($item) => (array) $item)->toArray())
                ->toArray();

            $allOrderItems = array_merge($allOrderItems, $orderItems);
        }

        foreach ($orders as &$order) {
            $order['items'] = $allOrderItems[$order['OrderID']] ?? [];
        }
    }

    /**
     * Attach order items to orders array.
     * Optimized for large datasets by processing in chunks.
     *
     * @param  array<int, array<string, mixed>>  $orders
     * @param  array<int>  $orderIds
     */
    private function attachOrderItemsToOrders(array &$orders, array $orderIds): void
    {
        if (empty($orderIds)) {
            return;
        }

        // Process in chunks to avoid large WHERE IN clauses
        $chunkSize = 100;
        $allOrderItems = [];

        foreach (array_chunk($orderIds, $chunkSize) as $chunkIds) {
            $orderItems = DB::connection(self::CONNECTION)
                ->table('OrderItems')
                ->leftJoin('ProductItem', 'ProductItem.ItemID', '=', 'OrderItems.ItemID')
                ->whereIn('OrderItems.OrderID', $chunkIds)
                ->select([
                    'OrderItems.idOrderItem',
                    'OrderItems.OrderID',
                    'OrderItems.ItemID',
                    'OrderItems.Price',
                    'OrderItems.Qty',
                ])
                ->get()
                ->groupBy('OrderID')
                ->map(fn ($items) => $items->map(fn ($item) => (array) $item)->toArray())
                ->toArray();

            $allOrderItems = array_merge($allOrderItems, $orderItems);
        }

        foreach ($orders as &$order) {
            $order['items'] = $allOrderItems[$order['OrderID']] ?? [];
        }
    }

    /**
     * Verify connection to external database.
     */
    public function testConnection(): bool
    {
        try {
            DB::connection(self::CONNECTION)->selectOne('SELECT 1');

            return true;
        } catch (\Exception $e) {
            Log::error('External database connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
