Task: Implement a safe, idempotent data import & sync from external MySQL database (mysql_external) into the current system.

CRITICAL CONSTRAINTS
1. The external database `mysql_external` is READ-ONLY.
   - No schema changes
   - No data updates
   - No locks
2. All logic must operate via SELECT queries only.
3. Import must be safe to run multiple times (idempotent).

--------------------------------------------------
GENERAL GOAL
--------------------------------------------------
Synchronize historical business data from mysql_external into our current system,
ensuring data integrity, correct relationships, access control compatibility,
and zero data leakage.

--------------------------------------------------
DATA DOMAINS TO IMPORT
--------------------------------------------------

1) EXPENSES
2) ORDERS
3) ORDER ITEMS
4) PRODUCTS (already exist, keys match)
5) PRODUCT ITEMS (already exist, keys match)

Primary keys from external DB match existing internal keys.

--------------------------------------------------
DATE-BASED IMPORT LOGIC
--------------------------------------------------

The import must support:
- Single day
- Last 7 days
- Last 30 days
- Arbitrary date range (from → to)

Implementation requirements:
- Central date-range resolver
- No hardcoded dates
- All imports must work consistently for any range

--------------------------------------------------
CUSTOMER & ADDRESS NORMALIZATION
--------------------------------------------------

External Orders table contains denormalized customer/address data.

You must:

### CUSTOMER
- Create or update a Customer record based on:
  - Email (primary identifier when present)
  - Fallback strategy if email is missing (e.g. Amazon FBA)
- If customer already exists:
  - Detect changes (name, phone)
  - Update only if data differs
- If email is missing:
  - Mark customer as "external / anonymous / marketplace"
  - Preserve order linkage

### ADDRESS
Create Address records derived from order fields.

Address types:
- billing
- shipping
- both

Rules:
1. If only billing fields exist → create ONE address of type `billing`
2. If only shipping fields exist → create ONE address of type `shipping`
3. If both exist:
   - If billing == shipping → create ONE address of type `both`
   - If different → create TWO addresses (billing + shipping)

Addresses must:
- Be deduplicated (same address reused)
- Be linked to Customer
- Be linked to Order via pivot or FK (depending on schema)

--------------------------------------------------
AMAZON FBA / MARKETPLACE ORDERS
--------------------------------------------------

Some orders:
- Have no email
- Have no customer contact data

These must:
- Still be imported
- Be clearly marked in our DB (e.g. source = amazon_fba)
- NOT break customer/address constraints
- Remain queryable & reportable

--------------------------------------------------
SYNC STRATEGY (VERY IMPORTANT)
--------------------------------------------------

For EVERY imported entity:
- If record does NOT exist → create it
- If record EXISTS:
  - Compare relevant fields
  - Update ONLY if data changed
  - Do NOT overwrite newer internal data blindly

Entities requiring sync logic:
- Customer
- Address
- Order
- OrderItem
- Expense

--------------------------------------------------
DATA INTEGRITY & RELATIONS
--------------------------------------------------

Ensure:
- Orders link correctly to:
  - Customer
  - Addresses
  - Brand
- OrderItems link correctly to:
  - Orders
  - ProductItems
  - Products
- Expenses link correctly to:
  - Products
  - Expense Types

No orphan records allowed.

--------------------------------------------------
ARCHITECTURE REQUIREMENTS
--------------------------------------------------

Implement using:
- Dedicated Import Services (NOT controllers)
- Clear separation:
  - External DB access layer
  - Mapping / transformation layer
  - Persistence layer

Recommended structure:
- ExternalRepository (read-only)
- ImportService per domain
- DTOs or mappers (no raw array spaghetti)

--------------------------------------------------
SAFETY & PERFORMANCE
--------------------------------------------------

- Chunk large datasets
- Avoid N+1 queries
- Use transactions per logical batch
- Gracefully handle partial failures
- Log import results (created / updated / skipped)

--------------------------------------------------
TESTING (MANDATORY)
--------------------------------------------------

Create automated tests that verify:
1. Re-running import does NOT create duplicates
2. Updated external data updates internal records
3. Amazon FBA orders import without customers
4. Address deduplication works correctly
5. Mixed billing/shipping logic is correct
6. Import respects date ranges
7. No writes occur on mysql_external connection

Tests must FAIL if:
- Duplicate customers are created
- Addresses are incorrectly typed
- Orders lose relationships
- External DB is written to

--------------------------------------------------
OUTPUT EXPECTED
--------------------------------------------------

- Import services fully implemented
- Tests covering all edge cases
- Summary of assumptions made
- List of potential data inconsistencies detected during import

DO NOT:
- Hardcode SQL
- Assume data cleanliness
- Skip edge cases
- Modify external DB in any way
