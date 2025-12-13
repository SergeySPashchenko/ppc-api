<?php

use App\Models\Access;
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

test('global admin can access all models via API', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($admin);

    // Test all endpoints return data
    expect($this->getJson('/api/brands'))->assertSuccessful();
    expect($this->getJson('/api/products'))->assertSuccessful();
    expect($this->getJson('/api/product-items'))->assertSuccessful();
    expect($this->getJson('/api/orders'))->assertSuccessful();
    expect($this->getJson('/api/order-items'))->assertSuccessful();
    expect($this->getJson('/api/expenses'))->assertSuccessful();
    expect($this->getJson('/api/customers'))->assertSuccessful();
    expect($this->getJson('/api/addresses'))->assertSuccessful();
    expect($this->getJson('/api/categories'))->assertSuccessful();
    expect($this->getJson('/api/genders'))->assertSuccessful();
    expect($this->getJson('/api/expense-types'))->assertSuccessful();
});

test('brand user can access orders through product inheritance', function () {
    $brandUser = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $brandId = $accessibleBrandIds->first();
    $productIds = Product::where('brand_id', $brandId)->pluck('ProductID');
    $expectedOrderCount = Order::whereIn('BrandID', $productIds)->count();

    $response = $this->getJson('/api/orders');
    $response->assertSuccessful();

    $orders = collect($response->json('data'));
    expect($orders->count())->toBe($expectedOrderCount);

    // All orders should be for products from accessible brand
    $orders->each(function ($order) use ($productIds) {
        expect($productIds->contains($order['BrandID']))->toBeTrue();
    });
});

test('brand user can access expenses through product inheritance', function () {
    $brandUser = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $brandId = $accessibleBrandIds->first();
    $productIds = Product::where('brand_id', $brandId)->pluck('ProductID');
    $expectedExpenseCount = Expense::whereIn('ProductID', $productIds)->count();

    $response = $this->getJson('/api/expenses');
    $response->assertSuccessful();

    $expenses = collect($response->json('data'));
    expect($expenses->count())->toBe($expectedExpenseCount);
});

test('brand user can access customers through orders', function () {
    $brandUser = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $brandId = $accessibleBrandIds->first();
    $productIds = Product::where('brand_id', $brandId)->pluck('ProductID');
    $orderIds = Order::whereIn('BrandID', $productIds)->whereNotNull('customer_id')->pluck('customer_id')->unique();
    $expectedCustomerCount = $orderIds->count();

    $response = $this->getJson('/api/customers');
    $response->assertSuccessful();

    $customers = collect($response->json('data'));
    expect($customers->count())->toBe($expectedCustomerCount);
});

test('brand user can access addresses through customers', function () {
    $brandUser = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $brandId = $accessibleBrandIds->first();
    $productIds = Product::where('brand_id', $brandId)->pluck('ProductID');
    $orderIds = Order::whereIn('BrandID', $productIds)->whereNotNull('customer_id')->pluck('customer_id')->unique();
    $expectedAddressCount = Address::whereIn('customer_id', $orderIds)->count();

    $response = $this->getJson('/api/addresses');
    $response->assertSuccessful();

    $addresses = collect($response->json('data'));
    expect($addresses->count())->toBe($expectedAddressCount);
});

test('brand user can access reference data (categories, genders, expense types)', function () {
    $brandUser = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandUser);

    // Should have access to all reference data if any exists
    $categoriesResponse = $this->getJson('/api/categories');
    $categoriesResponse->assertSuccessful();
    // Categories should be accessible (may be empty if none seeded)

    $gendersResponse = $this->getJson('/api/genders');
    $gendersResponse->assertSuccessful();
    // Genders should be accessible (may be empty if none seeded)

    $expenseTypesResponse = $this->getJson('/api/expense-types');
    $expenseTypesResponse->assertSuccessful();
    // Expense types should be accessible (may be empty if none seeded)

    // Brand user has access to brands, so should see reference data
    // Verify the user actually has brand access
    $hasAccess = $brandUser->hasAnyBrandOrProductAccess();

    if ($hasAccess && Category::count() > 0) {
        expect($categoriesResponse->json('data'))->not->toBeEmpty();
    }
    if ($hasAccess && Gender::count() > 0) {
        expect($gendersResponse->json('data'))->not->toBeEmpty();
    }
    if ($hasAccess && Expensetype::count() > 0) {
        expect($expenseTypesResponse->json('data'))->not->toBeEmpty();
    }
});

test('product user can access order items through product item inheritance', function () {
    $productUser = User::where('email', 'productuser@example.com')->first();
    Sanctum::actingAs($productUser);

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    $accessibleItemIds = ProductItem::whereIn('ProductID', $accessibleProductIds)->pluck('ItemID');
    $expectedOrderItemCount = OrderItem::whereIn('ItemID', $accessibleItemIds)->count();

    $response = $this->getJson('/api/order-items');
    $response->assertSuccessful();

    $orderItems = collect($response->json('data'));
    expect($orderItems->count())->toBe($expectedOrderItemCount);
});

test('user cannot access order they do not have access to', function () {
    $brandUser = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleOrderIds = Order::getAccessibleIdsForUser($brandUser);
    $allOrderIds = Order::pluck('id');
    $inaccessibleOrderId = $allOrderIds->diff($accessibleOrderIds)->first();

    if ($inaccessibleOrderId) {
        $response = $this->getJson("/api/orders/{$inaccessibleOrderId}");
        $response->assertForbidden();
    }
})->skip(fn () => Order::count() < 2, 'Need at least 2 orders for this test');

test('user cannot access customer they do not have access to', function () {
    $brandUser = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleCustomerIds = Customer::getAccessibleIdsForUser($brandUser);
    $allCustomerIds = Customer::pluck('id');
    $inaccessibleCustomerId = $allCustomerIds->diff($accessibleCustomerIds)->first();

    if ($inaccessibleCustomerId) {
        $response = $this->getJson("/api/customers/{$inaccessibleCustomerId}");
        $response->assertForbidden();
    }
})->skip(fn () => Customer::count() < 2, 'Need at least 2 customers for this test');

test('filtering orders by product_id works correctly', function () {
    $brandUser = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $brandId = $accessibleBrandIds->first();
    $product = Product::where('brand_id', $brandId)->first();

    if (! $product) {
        $this->markTestSkipped('No products found for brand user');
    }

    $response = $this->getJson("/api/orders?product_id={$product->ProductID}");
    $response->assertSuccessful();

    $orders = collect($response->json('data'));
    $orders->each(function ($order) use ($product) {
        expect($order['BrandID'])->toBe($product->ProductID);
    });
});

test('filtering expenses by product_id works correctly', function () {
    $brandUser = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $brandId = $accessibleBrandIds->first();
    $product = Product::where('brand_id', $brandId)->first();

    if (! $product) {
        $this->markTestSkipped('No products found for brand user');
    }

    $response = $this->getJson("/api/expenses?product_id={$product->ProductID}");
    $response->assertSuccessful();

    $expenses = collect($response->json('data'));
    $expenses->each(function ($expense) use ($product) {
        expect($expense['ProductID'])->toBe($product->ProductID);
    });
});

test('filtering addresses by customer_id works correctly', function () {
    $brandUser = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleCustomerIds = Customer::getAccessibleIdsForUser($brandUser);
    $customerId = $accessibleCustomerIds->first();

    $response = $this->getJson("/api/addresses?customer_id={$customerId}");
    $response->assertSuccessful();

    $addresses = collect($response->json('data'));
    $addresses->each(function ($address) use ($customerId) {
        expect($address['customer_id'])->toBe($customerId);
    });
});

test('api responses include correct relationships when loaded', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($admin);

    $order = Order::with(['customer', 'product', 'orderItems.item', 'addresses'])->first();

    if ($order) {
        $response = $this->getJson("/api/orders/{$order->id}");
        $response->assertSuccessful();

        $data = $response->json('data');
        expect($data)->toHaveKey('customer');
        expect($data)->toHaveKey('product');
        expect($data)->toHaveKey('orderItems');
        expect($data)->toHaveKey('addresses');
    }
})->skip(fn () => Order::count() === 0, 'Need at least 1 order for this test');

test('reference data endpoints return correct structure', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($admin);

    $category = Category::first();
    $response = $this->getJson("/api/categories/{$category->category_id}");
    $response->assertSuccessful();
    expect($response->json('data'))->toHaveKeys(['category_id', 'category_name', 'slug']);

    $gender = Gender::first();
    $response = $this->getJson("/api/genders/{$gender->gender_id}");
    $response->assertSuccessful();
    expect($response->json('data'))->toHaveKeys(['gender_id', 'gender_name', 'slug']);

    $expenseType = Expensetype::first();
    $response = $this->getJson("/api/expense-types/{$expenseType->ExpenseID}");
    $response->assertSuccessful();
    expect($response->json('data'))->toHaveKeys(['ExpenseID', 'Name', 'slug']);
});

test('user without brand or product access cannot see reference data', function () {
    // Create a user with no access
    $noAccessUser = User::factory()->create([
        'name' => 'No Access User',
        'email' => 'noaccess@example.com',
    ]);
    Sanctum::actingAs($noAccessUser);

    $this->getJson('/api/categories')->assertSuccessful()->assertJson(['data' => []]);
    $this->getJson('/api/genders')->assertSuccessful()->assertJson(['data' => []]);
    $this->getJson('/api/expense-types')->assertSuccessful()->assertJson(['data' => []]);
});

test('access inheritance chain works: brand -> product -> expense', function () {
    $brandUser = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $brandId = $accessibleBrandIds->first();
    $productIds = Product::where('brand_id', $brandId)->pluck('ProductID');
    $expectedExpenseIds = Expense::whereIn('ProductID', $productIds)->pluck('id');

    $accessibleExpenseIds = Expense::getAccessibleIdsForUser($brandUser);

    expect($accessibleExpenseIds->count())->toBe($expectedExpenseIds->count());
    $expectedExpenseIds->each(function ($expenseId) use ($accessibleExpenseIds) {
        expect($accessibleExpenseIds->contains($expenseId))->toBeTrue();
    });
});

test('access inheritance chain works: product -> product item -> order item', function () {
    $productUser = User::where('email', 'productuser@example.com')->first();
    Sanctum::actingAs($productUser);

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    $accessibleItemIds = ProductItem::whereIn('ProductID', $accessibleProductIds)->pluck('ItemID');
    $expectedOrderItemIds = OrderItem::whereIn('ItemID', $accessibleItemIds)->pluck('idOrderItem');

    $accessibleOrderItemIds = OrderItem::getAccessibleIdsForUser($productUser);

    expect($accessibleOrderItemIds->count())->toBe($expectedOrderItemIds->count());
});
