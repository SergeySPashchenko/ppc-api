<?php

declare(strict_types=1);

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Read-only repository for accessing external mysql_external database.
 * All methods use SELECT queries only - no writes, no schema changes.
 */
final class ExternalRepository
{
    private const CONNECTION = 'mysql_external';

    /**
     * Get expenses for a date range.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getExpenses(\DateTimeInterface $from, \DateTimeInterface $to, ?int $limit = null): array
    {
        try {
            return DB::connection(self::CONNECTION)
                ->table('expenses as e')
                ->leftJoin('product as p', 'p.ProductID', '=', 'e.ProductID')
                ->leftJoin('category as mc', 'p.main_category_id', '=', 'mc.category_id')
                ->leftJoin('category as mkt', 'p.marketing_category_id', '=', 'mkt.category_id')
                ->leftJoin('gender as g', 'p.gender_id', '=', 'g.gender_id')
                ->leftJoin('expensetype as et', 'et.ExpenseID', '=', 'e.ExpenseID')
                ->whereBetween('e.ExpenseDate', [$from->format('Y-m-d'), $to->format('Y-m-d')])
                ->when($limit !== null, fn ($query) => $query->limit($limit))
                ->orderBy('e.ExpenseDate', 'desc')
                ->orderBy('e.id', 'desc')
                ->select([
                    'e.id',
                    'e.ProductID',
                    'e.ExpenseID',
                    'e.ExpenseDate',
                    'e.Expense',
                    'et.Name as ExpenseTypeName',
                    'p.Product',
                    'p.newSystem',
                    'p.Brand',
                    'p.Visible',
                    'mc.category_name as MainCategoryName',
                    'mkt.category_name as MarketingCategoryName',
                    'g.gender_name as GenderName',
                    'p.flyer',
                    'p.main_category_id',
                    'p.marketing_category_id',
                    'p.gender_id',
                ])
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to fetch expenses from external DB', [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get orders with order items for a date range.
     * Note: OrderDate in external DB is stored as Ymd format (e.g., 20170102).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(\DateTimeInterface $from, \DateTimeInterface $to, ?int $limit = null): array
    {
        try {
            // Convert dates to Ymd format for external DB comparison
            $fromYmd = (int) $from->format('Ymd');
            $toYmd = (int) $to->format('Ymd');

            $orders = DB::connection(self::CONNECTION)
                ->table('Orders')
                ->whereBetween('OrderDate', [$fromYmd, $toYmd])
                ->when($limit !== null, fn ($query) => $query->limit($limit))
                ->orderBy('OrderDate', 'desc')
                ->orderBy('id', 'desc')
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
                    // Customer fields
                    'Orders.Email',
                    'Orders.Name',
                    'Orders.Phone',
                    // Billing address fields
                    'Orders.Address as BillingAddress',
                    'Orders.Address2 as BillingAddress2',
                    'Orders.City as BillingCity',
                    'Orders.State as BillingState',
                    'Orders.Zip as BillingZip',
                    'Orders.Country as BillingCountry',
                    'Orders.Phone as BillingPhone',
                    // Shipping address fields
                    'Orders.ShipName as ShippingName',
                    'Orders.ShipAddress as ShippingAddress',
                    'Orders.ShipAddress2 as ShippingAddress2',
                    'Orders.ShipCity as ShippingCity',
                    'Orders.ShipState as ShippingState',
                    'Orders.ShipZip as ShippingZip',
                    'Orders.ShipCountry as ShippingCountry',
                    'Orders.ShipPhone as ShippingPhone',
                ])
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();

            // Load order items for each order
            $orderIds = array_column($orders, 'OrderID');
            if (empty($orderIds)) {
                return [];
            }

            $orderItems = DB::connection(self::CONNECTION)
                ->table('OrderItems')
                ->leftJoin('ProductItem', 'ProductItem.ItemID', '=', 'OrderItems.ItemID')
                ->leftJoin('product', 'product.ProductID', '=', 'ProductItem.ProductID')
                ->whereIn('OrderItems.OrderID', $orderIds)
                ->select([
                    'OrderItems.idOrderItem',
                    'OrderItems.OrderID',
                    'OrderItems.ItemID',
                    'OrderItems.Price',
                    'OrderItems.Qty',
                    'ProductItem.ProductID',
                    'ProductItem.ProductName',
                    'ProductItem.SKU',
                    'ProductItem.Quantity',
                    'ProductItem.upSell',
                    'ProductItem.active',
                    'ProductItem.deleted',
                    'ProductItem.offerProducts',
                    'ProductItem.extraProduct',
                    'product.ProductID as ProductProductID',
                    'product.newSystem',
                    'product.Visible',
                    'product.flyer',
                    'product.main_category_id',
                    'product.gender_id',
                    'product.Brand',
                ])
                ->get()
                ->groupBy('OrderID')
                ->map(fn ($items) => $items->map(fn ($item) => (array) $item)->toArray())
                ->toArray();

            // Attach order items to orders
            foreach ($orders as &$order) {
                $order['items'] = $orderItems[$order['OrderID']] ?? [];
            }

            return $orders;
        } catch (\Exception $e) {
            Log::error('Failed to fetch orders from external DB', [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            throw $e;
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
