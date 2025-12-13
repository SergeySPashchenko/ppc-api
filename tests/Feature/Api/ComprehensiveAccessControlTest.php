<?php

use App\Models\Address;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Expensetype;
use App\Models\Gender;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use Database\Seeders\DataSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(DataSeeder::class);
});

// Define all API resources to test
$apiResources = [
    'brands' => Brand::class,
    'products' => Product::class,
    'product-items' => Product::class, // ProductItem uses product_id filter
    'categories' => Category::class,
    'genders' => Gender::class,
    'expense-types' => Expensetype::class,
    'orders' => Order::class,
    'order-items' => OrderItem::class,
    'customers' => Customer::class,
    'addresses' => Address::class,
    'expenses' => Expense::class,
    'users' => User::class,
];

// Test users from DataSeeder
$testUsers = [
    'global_admin' => 'admin@example.com',
    'brand_admin' => 'brandadmin@example.com',
    'product_manager' => 'productuser@example.com',
    'viewer' => 'itemuser@example.com',
    'mixed_access' => 'mixeduser@example.com',
    'multi_brand' => 'multibranduser@example.com',
];

// ========== API ENDPOINT TESTS ==========

test('global admin can access all API endpoints', function () use ($apiResources) {
    $admin = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($admin);

    foreach ($apiResources as $endpoint => $modelClass) {
        $response = $this->getJson("/api/{$endpoint}");
        expect($response)->assertSuccessful()
            ->assertJsonStructure(['data' => []]);
    }
});

test('brand admin can only access their accessible brands via API', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandAdmin);

    $response = $this->getJson('/api/brands');
    expect($response)->assertSuccessful();

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    // API resource may use brand_id instead of id
    $returnedBrandIds = collect($response->json('data'))->pluck('id')->filter();
    if ($returnedBrandIds->isEmpty()) {
        $returnedBrandIds = collect($response->json('data'))->pluck('brand_id')->filter();
    }

    // Verify only accessible brands are returned
    expect($returnedBrandIds->diff($accessibleBrandIds))->toBeEmpty();
    expect($accessibleBrandIds->diff($returnedBrandIds))->toBeEmpty();
});

test('brand admin cannot access inaccessible brands via API', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $inaccessibleBrand = Brand::whereNotIn('brand_id', $accessibleBrandIds)->first();

    if ($inaccessibleBrand) {
        $response = $this->getJson("/api/brands/{$inaccessibleBrand->brand_id}");
        expect($response)->assertForbidden();
    }
})->skip(fn () => Brand::count() < 2, 'Need at least 2 brands for this test');

test('product manager can only access their accessible products via API', function () {
    $productUser = User::where('email', 'productuser@example.com')->first();
    Sanctum::actingAs($productUser);

    $response = $this->getJson('/api/products');
    expect($response)->assertSuccessful();

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    // API resource may use ProductID instead of id
    $returnedProductIds = collect($response->json('data'))->pluck('id')->filter();
    if ($returnedProductIds->isEmpty()) {
        $returnedProductIds = collect($response->json('data'))->pluck('ProductID')->filter();
    }

    expect($returnedProductIds->diff($accessibleProductIds))->toBeEmpty();
    expect($accessibleProductIds->diff($returnedProductIds))->toBeEmpty();
});

test('product manager cannot access inaccessible products via API', function () {
    $productUser = User::where('email', 'productuser@example.com')->first();
    Sanctum::actingAs($productUser);

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    $inaccessibleProduct = Product::whereNotIn('ProductID', $accessibleProductIds)->first();

    if ($inaccessibleProduct) {
        $response = $this->getJson("/api/products/{$inaccessibleProduct->ProductID}");
        expect($response)->assertForbidden();
    }
})->skip(fn () => Product::count() < 2, 'Need at least 2 products for this test');

test('viewer can only access their accessible product items via API', function () {
    $itemUser = User::where('email', 'itemuser@example.com')->first();
    Sanctum::actingAs($itemUser);

    $response = $this->getJson('/api/product-items');
    expect($response)->assertSuccessful();

    $accessibleItemIds = ProductItem::getAccessibleIdsForUser($itemUser);
    // API resource may use ItemID instead of id
    $returnedItemIds = collect($response->json('data'))->pluck('id')->filter();
    if ($returnedItemIds->isEmpty()) {
        $returnedItemIds = collect($response->json('data'))->pluck('ItemID')->filter();
    }

    expect($returnedItemIds->diff($accessibleItemIds))->toBeEmpty();
    expect($accessibleItemIds->diff($returnedItemIds))->toBeEmpty();
});

test('no-access user gets empty collections from API', function () {
    $noAccessUser = User::create([
        'name' => 'No Access User',
        'email' => 'noaccess@example.com',
        'password' => bcrypt('password'),
        'slug' => 'no-access-user',
    ]);
    Sanctum::actingAs($noAccessUser);

    // Reference data should return empty (not 403)
    $this->getJson('/api/categories')->assertSuccessful()->assertJson(['data' => []]);
    $this->getJson('/api/genders')->assertSuccessful()->assertJson(['data' => []]);
    $this->getJson('/api/expense-types')->assertSuccessful()->assertJson(['data' => []]);

    // Main resources - check if user has permissions, if not they get 403
    // Brands require ViewAny:Brand permission
    $brandsResponse = $this->getJson('/api/brands');
    if ($noAccessUser->can('ViewAny:Brand')) {
        $brandsResponse->assertSuccessful()->assertJson(['data' => []]);
    } else {
        $brandsResponse->assertForbidden();
    }

    // Products require ViewAny:Product permission
    $productsResponse = $this->getJson('/api/products');
    if ($noAccessUser->can('ViewAny:Product')) {
        $productsResponse->assertSuccessful()->assertJson(['data' => []]);
    } else {
        $productsResponse->assertForbidden();
    }

    // ProductItems require ViewAny:ProductItem permission
    $itemsResponse = $this->getJson('/api/product-items');
    if ($noAccessUser->can('ViewAny:ProductItem')) {
        $itemsResponse->assertSuccessful()->assertJson(['data' => []]);
    } else {
        $itemsResponse->assertForbidden();
    }
});

test('API filters do not leak inaccessible data', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $accessibleBrand = Brand::whereIn('brand_id', $accessibleBrandIds)->first();

    if ($accessibleBrand) {
        // Filter by accessible brand_id
        $response = $this->getJson("/api/products?brand_id={$accessibleBrand->brand_id}");
        expect($response)->assertSuccessful();
        $products = collect($response->json('data'));
        $products->each(function ($product) use ($accessibleBrand) {
            expect($product['brand_id'])->toBe($accessibleBrand->brand_id);
        });

        // Try to filter by inaccessible brand_id
        $inaccessibleBrand = Brand::whereNotIn('brand_id', $accessibleBrandIds)->first();
        if ($inaccessibleBrand) {
            $response = $this->getJson("/api/products?brand_id={$inaccessibleBrand->brand_id}");
            expect($response)->assertSuccessful();
            expect($response->json('data'))->toBeEmpty();
        }
    }
})->skip(fn () => Brand::count() < 2, 'Need at least 2 brands for this test');

test('API product_id filter works correctly', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $product = Product::whereIn('brand_id', $accessibleBrandIds)->first();

    if ($product) {
        // Filter expenses by product_id
        $response = $this->getJson("/api/expenses?product_id={$product->ProductID}");
        expect($response)->assertSuccessful();
        $expenses = collect($response->json('data'));
        $expenses->each(function ($expense) use ($product) {
            expect($expense['ProductID'])->toBe($product->ProductID);
        });
    }
})->skip(fn () => Product::count() === 0, 'Need at least 1 product for this test');

test('API customer_id filter works correctly', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandAdmin);

    $accessibleCustomerIds = Customer::getAccessibleIdsForUser($brandAdmin);
    $customer = Customer::whereIn('CustomerID', $accessibleCustomerIds)->first();

    if ($customer) {
        $response = $this->getJson("/api/addresses?customer_id={$customer->CustomerID}");
        expect($response)->assertSuccessful();
        $addresses = collect($response->json('data'));
        $addresses->each(function ($address) use ($customer) {
            expect($address['customer_id'])->toBe($customer->CustomerID);
        });
    }
})->skip(fn () => Customer::count() === 0, 'Need at least 1 customer for this test');

test('API responses do not include foreign records', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $inaccessibleBrandIds = Brand::whereNotIn('brand_id', $accessibleBrandIds)->pluck('brand_id');

    // Check products endpoint
    $response = $this->getJson('/api/products');
    expect($response)->assertSuccessful();
    $products = collect($response->json('data'));
    $products->each(function ($product) use ($inaccessibleBrandIds) {
        expect($inaccessibleBrandIds->contains($product['brand_id']))->toBeFalse();
    });

    // Check orders endpoint - orders are accessible through products/brands
    $response = $this->getJson('/api/orders');
    expect($response)->assertSuccessful();
    $orders = collect($response->json('data'));

    // Verify all returned orders are accessible (through accessibleBy scope)
    $accessibleOrderIds = Order::getAccessibleIdsForUser($brandAdmin);
    $returnedOrderIds = $orders->pluck('id')->filter();
    if ($returnedOrderIds->isEmpty()) {
        $returnedOrderIds = $orders->pluck('OrderID')->filter();
    }

    expect($returnedOrderIds->diff($accessibleOrderIds))->toBeEmpty();
})->skip(fn () => Brand::count() < 2, 'Need at least 2 brands for this test');

test('unauthenticated users cannot access API endpoints', function () use ($apiResources) {
    foreach ($apiResources as $endpoint => $modelClass) {
        $response = $this->getJson("/api/{$endpoint}");
        expect($response)->assertUnauthorized();
    }
});

test('API endpoints return 404 for non-existent resources', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/brands/999999');
    expect($response)->assertNotFound();

    $response = $this->getJson('/api/products/999999');
    expect($response)->assertNotFound();
});
