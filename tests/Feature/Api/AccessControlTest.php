<?php

use App\Models\Access;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(AccessControlSeeder::class);
});

test('unauthenticated users cannot access api endpoints', function () {
    $response = $this->getJson('/api/brands');
    $response->assertUnauthorized();

    $response = $this->getJson('/api/products');
    $response->assertUnauthorized();

    $response = $this->getJson('/api/product-items');
    $response->assertUnauthorized();
});

test('global admin can see all brands', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/brands');
    $response->assertSuccessful();

    $brands = Brand::all();
    expect($response->json('data'))->toHaveCount($brands->count());
});

test('global admin can see all products', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/products');
    $response->assertSuccessful();

    $products = Product::all();
    expect($response->json('data'))->toHaveCount($products->count());
});

test('global admin can see all product items', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/product-items');
    $response->assertSuccessful();

    $items = ProductItem::all();
    expect($response->json('data'))->toHaveCount($items->count());
});

test('brand user can only see their accessible brand', function () {
    $brandUser = User::where('email', 'brand@example.com')->first();
    Sanctum::actingAs($brandUser);

    $response = $this->getJson('/api/brands');
    $response->assertSuccessful();

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    expect($response->json('data'))->toHaveCount($accessibleBrandIds->count());
    expect($accessibleBrandIds->count())->toBe(1);
});

test('brand user can see all products from their accessible brand', function () {
    $brandUser = User::where('email', 'brand@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $brandId = $accessibleBrandIds->first();

    $expectedProductCount = Product::where('brand_id', $brandId)->count();

    $response = $this->getJson('/api/products');
    $response->assertSuccessful();

    $products = collect($response->json('data'));
    expect($products->count())->toBe($expectedProductCount);

    // All products should belong to the accessible brand
    $products->each(function ($product) use ($brandId) {
        expect($product['brand_id'])->toBe($brandId);
    });
});

test('brand user can filter products by their accessible brand_id', function () {
    $brandUser = User::where('email', 'brand@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $brandId = $accessibleBrandIds->first();

    $response = $this->getJson("/api/products?brand_id={$brandId}");
    $response->assertSuccessful();

    $products = collect($response->json('data'));
    $expectedCount = Product::where('brand_id', $brandId)->count();
    expect($products->count())->toBe($expectedCount);
});

test('brand user cannot filter products by inaccessible brand_id', function () {
    $brandUser = User::where('email', 'brand@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $allBrandIds = Brand::pluck('brand_id');
    $inaccessibleBrandId = $allBrandIds->diff($accessibleBrandIds)->first();

    $response = $this->getJson("/api/products?brand_id={$inaccessibleBrandId}");
    $response->assertSuccessful();

    // Should return empty collection
    expect($response->json('data'))->toBeEmpty();
});

test('product user can only see their accessible products', function () {
    $productUser = User::where('email', 'product@example.com')->first();
    Sanctum::actingAs($productUser);

    $response = $this->getJson('/api/products');
    $response->assertSuccessful();

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    expect($response->json('data'))->toHaveCount($accessibleProductIds->count());
    expect($accessibleProductIds->count())->toBe(2);
});

test('product user can filter products by their accessible product_id', function () {
    $productUser = User::where('email', 'product@example.com')->first();
    Sanctum::actingAs($productUser);

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    $productId = $accessibleProductIds->first();

    $response = $this->getJson("/api/product-items?product_id={$productId}");
    $response->assertSuccessful();

    $items = collect($response->json('data'));
    $expectedCount = ProductItem::where('ProductID', $productId)->count();
    expect($items->count())->toBe($expectedCount);
});

test('product user cannot filter products by inaccessible product_id', function () {
    $productUser = User::where('email', 'product@example.com')->first();
    Sanctum::actingAs($productUser);

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    $allProductIds = Product::pluck('ProductID');
    $inaccessibleProductId = $allProductIds->diff($accessibleProductIds)->first();

    $response = $this->getJson("/api/product-items?product_id={$inaccessibleProductId}");
    $response->assertSuccessful();

    // Should return empty collection
    expect($response->json('data'))->toBeEmpty();
});

test('multi-brand user can see products from all accessible brands', function () {
    $multiBrandUser = User::where('email', 'multibrand@example.com')->first();
    Sanctum::actingAs($multiBrandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($multiBrandUser);
    $expectedProductCount = Product::whereIn('brand_id', $accessibleBrandIds)->count();

    $response = $this->getJson('/api/products');
    $response->assertSuccessful();

    $products = collect($response->json('data'));
    expect($products->count())->toBe($expectedProductCount);
    expect($accessibleBrandIds->count())->toBe(2);

    // All products should belong to accessible brands
    $products->each(function ($product) use ($accessibleBrandIds) {
        expect($accessibleBrandIds->contains($product['brand_id']))->toBeTrue();
    });
});

test('mixed access user can see products from brand and specific product', function () {
    $mixedUser = User::where('email', 'mixed@example.com')->first();
    Sanctum::actingAs($mixedUser);

    $response = $this->getJson('/api/products');
    $response->assertSuccessful();

    $accessibleProductIds = Product::getAccessibleIdsForUser($mixedUser);
    $products = collect($response->json('data'));

    expect($products->count())->toBe($accessibleProductIds->count());

    // Should see all products from accessible brand + the specific product
    $accessibleBrandIds = Brand::getAccessibleIdsForUser($mixedUser);
    $brandProducts = Product::whereIn('brand_id', $accessibleBrandIds)->pluck('ProductID');
    $directProductIds = Access::where('user_id', $mixedUser->id)
        ->where('accessible_type', Product::getMorphType())
        ->pluck('accessible_id');

    $expectedIds = $brandProducts->merge($directProductIds)->unique();
    $actualIds = $products->pluck('ProductID');

    expect($actualIds->diff($expectedIds)->isEmpty())->toBeTrue();
    expect($expectedIds->diff($actualIds)->isEmpty())->toBeTrue();
});

test('access inheritance works - brand access gives product access', function () {
    $brandUser = User::where('email', 'brand@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $brandId = $accessibleBrandIds->first();

    // Get products that should be accessible through brand inheritance
    $expectedProducts = Product::where('brand_id', $brandId)->get();
    $accessibleProductIds = Product::getAccessibleIdsForUser($brandUser);

    expect($accessibleProductIds->count())->toBe($expectedProducts->count());

    // Verify all products from the brand are accessible
    $expectedProducts->each(function ($product) use ($accessibleProductIds) {
        expect($accessibleProductIds->contains($product->ProductID))->toBeTrue();
    });
});

test('access inheritance works - product access gives product item access', function () {
    $productUser = User::where('email', 'product@example.com')->first();
    Sanctum::actingAs($productUser);

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    $expectedItemCount = ProductItem::whereIn('ProductID', $accessibleProductIds)->count();

    $response = $this->getJson('/api/product-items');
    $response->assertSuccessful();

    $items = collect($response->json('data'));
    expect($items->count())->toBe($expectedItemCount);

    // All items should belong to accessible products
    $items->each(function ($item) use ($accessibleProductIds) {
        expect($accessibleProductIds->contains($item['ProductID']))->toBeTrue();
    });
});

test('access inheritance works - brand access gives product item access', function () {
    $brandUser = User::where('email', 'brand@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $brandId = $accessibleBrandIds->first();
    $productIds = Product::where('brand_id', $brandId)->pluck('ProductID');
    $expectedItemCount = ProductItem::whereIn('ProductID', $productIds)->count();

    $response = $this->getJson('/api/product-items');
    $response->assertSuccessful();

    $items = collect($response->json('data'));
    expect($items->count())->toBe($expectedItemCount);
});

test('user cannot access specific brand they do not have access to', function () {
    $brandUser = User::where('email', 'brand@example.com')->first();
    Sanctum::actingAs($brandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandUser);
    $allBrandIds = Brand::pluck('brand_id');
    $inaccessibleBrandId = $allBrandIds->diff($accessibleBrandIds)->first();

    $response = $this->getJson("/api/brands/{$inaccessibleBrandId}");
    // Returns 403 (Forbidden) because the brand exists but user doesn't have access
    $response->assertForbidden();
});

test('user cannot access specific product they do not have access to', function () {
    $productUser = User::where('email', 'product@example.com')->first();
    Sanctum::actingAs($productUser);

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    $allProductIds = Product::pluck('ProductID');
    $inaccessibleProductId = $allProductIds->diff($accessibleProductIds)->first();

    $response = $this->getJson("/api/products/{$inaccessibleProductId}");
    // Returns 403 (Forbidden) because the product exists but user doesn't have access
    $response->assertForbidden();
});

test('user cannot access specific product item they do not have access to', function () {
    $productUser = User::where('email', 'product@example.com')->first();
    Sanctum::actingAs($productUser);

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    $accessibleItemIds = ProductItem::whereIn('ProductID', $accessibleProductIds)->pluck('ItemID');
    $allItemIds = ProductItem::pluck('ItemID');
    $inaccessibleItemIds = $allItemIds->diff($accessibleItemIds);

    // Find an item that definitely doesn't belong to accessible products
    $inaccessibleItem = ProductItem::whereNotIn('ProductID', $accessibleProductIds)->first();

    expect($inaccessibleItem)->not->toBeNull();

    $response = $this->getJson("/api/product-items/{$inaccessibleItem->ItemID}");
    // Returns 403 (Forbidden) because the item exists but user doesn't have access
    $response->assertForbidden();
});

test('api responses include correct data structure', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($admin);

    $brand = Brand::first();

    $response = $this->getJson("/api/brands/{$brand->brand_id}");
    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data)->toHaveKeys(['brand_id', 'brand_name', 'slug', 'created_at', 'updated_at']);
});

test('api responses include relationships when loaded', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($admin);

    $product = Product::with('brand')->first();

    $response = $this->getJson("/api/products/{$product->ProductID}");
    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data)->toHaveKey('brand');
    expect($data['brand'])->toHaveKeys(['brand_id', 'brand_name', 'slug']);
});
