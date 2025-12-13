# Import Sync Implementation Summary

## Overview
Implemented a safe, idempotent data import & sync system from external MySQL database (`mysql_external`) into the current system.

## Architecture

### Components Created

1. **ExternalRepository** (`app/Services/Import/ExternalRepository.php`)
   - Read-only access to `mysql_external` database
   - Methods: `getExpenses()`, `getOrders()`, `testConnection()`
   - Uses SELECT queries only - no writes, no schema changes

2. **DateRangeResolver** (`app/Services/Import/DateRangeResolver.php`)
   - Central date range resolver
   - Supports: single date, date range, last N days
   - Default: last 7 days

3. **CustomerImportService** (`app/Services/Import/CustomerImportService.php`)
   - Normalizes customer data from orders
   - Handles email-based deduplication
   - Supports Amazon FBA/anonymous orders (no email)
   - Updates only if data changed

4. **AddressImportService** (`app/Services/Import/AddressImportService.php`)
   - Handles billing/shipping address logic:
     - Only billing → one address type `billing`
     - Only shipping → one address type `shipping`
     - Both equal → one address type `both`
     - Both different → two addresses (billing + shipping)
   - Address deduplication using `address_hash`
   - Links addresses to customers and orders

5. **OrderImportService** (`app/Services/Import/OrderImportService.php`)
   - Imports orders with idempotent updates
   - Verifies Product (BrandID) exists before import
   - Handles OrderDate format conversion (Ymd → Carbon)
   - Coordinates customer and address import

6. **OrderItemImportService** (`app/Services/Import/OrderItemImportService.php`)
   - Imports order items
   - Verifies ProductItem exists before import
   - Idempotent updates

7. **ExpenseImportService** (`app/Services/Import/ExpenseImportService.php`)
   - Imports expenses
   - Verifies Product and ExpenseType exist before import
   - Uses ExpenseDate + ProductID + ExpenseID as unique identifier

8. **ImportSyncCommand** (`app/Console/Commands/ImportSyncCommand.php`)
   - Artisan command: `php artisan import:sync`
   - Options:
     - `--date=Y-m-d` - Single date
     - `--from=Y-m-d --to=Y-m-d` - Date range
     - `--last-days=N` - Last N days
     - `--only=expenses|orders` - Import specific domain
     - `--chunk=100` - Chunk size for processing

## Key Features

### Idempotency
- All imports are safe to run multiple times
- Records are updated only if data changed
- No duplicate creation on re-runs

### Data Integrity
- Verifies Product/ProductItem/ExpenseType exist before import
- Skips records with missing dependencies (logged)
- Maintains all relationships correctly

### Error Handling
- Graceful handling of partial failures
- Detailed logging of errors and warnings
- Transactions per logical batch

### Performance
- Chunked processing for large datasets
- Eager loading to avoid N+1 queries
- Address deduplication reduces storage

## Testing

Comprehensive test suite (`tests/Feature/ImportSyncTest.php`) covering:
- ✅ Date range resolution (single date, range, last N days)
- ✅ Customer import and deduplication
- ✅ Customer updates only when data changed
- ✅ Amazon FBA orders without email
- ✅ Address billing/shipping logic
- ✅ Address deduplication
- ✅ Order import idempotency
- ✅ Order updates when external data changes
- ✅ Skipping orders/items/expenses with missing dependencies

## Usage Examples

```bash
# Import last 7 days (default)
php artisan import:sync

# Import single date
php artisan import:sync --date=2022-07-02

# Import date range
php artisan import:sync --from=2022-07-01 --to=2022-07-31

# Import last 30 days
php artisan import:sync --last-days=30

# Import only expenses
php artisan import:sync --only=expenses

# Import only orders
php artisan import:sync --only=orders

# Custom chunk size
php artisan import:sync --chunk=500
```

## Assumptions Made

1. **External Database Structure**
   - Table names: `Orders`, `OrderItems`, `expenses`, `product`, `ProductItem`, `expensetype`
   - OrderDate format: Ymd (e.g., 20170102)
   - ExpenseDate format: Y-m-d (standard date)

2. **Data Mapping**
   - External `Orders.id` → Internal `OrderID`
   - External `OrderItems.idOrderItem` → Internal `idOrderItem`
   - External `expenses.id` → Used for identification (not direct mapping)

3. **Customer Identification**
   - Primary: Email (when present)
   - Fallback: Anonymous customer for orders without email

4. **Address Deduplication**
   - Uses normalized address hash (address, address2, city, state, zip, country)
   - Same address reused across orders for same customer

## Potential Data Inconsistencies

During import, the following inconsistencies are detected and logged:

1. **Missing Products**: Orders referencing non-existent BrandID
2. **Missing ProductItems**: OrderItems referencing non-existent ItemID
3. **Missing ExpenseTypes**: Expenses referencing non-existent ExpenseID
4. **Missing Products for Expenses**: Expenses referencing non-existent ProductID

All such records are skipped (not imported) and logged as warnings.

## Logging

All import operations are logged to Laravel log:
- Created records
- Updated records
- Skipped records (with reasons)
- Errors (with full trace)

## Future Enhancements

Potential improvements:
- Dry-run mode to preview changes
- Import statistics dashboard
- Scheduled imports via Laravel scheduler
- Email notifications on import completion
- Import history/audit trail
