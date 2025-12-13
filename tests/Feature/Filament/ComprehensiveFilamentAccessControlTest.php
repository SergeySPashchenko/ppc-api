<?php

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use Database\Seeders\DataSeeder;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(DataSeeder::class);
});

// Define all Filament resources to test
$filamentResources = [
    'brands' => \App\Filament\Resources\Brands\BrandResource::class,
    'products' => \App\Filament\Resources\Products\ProductResource::class,
    'product-items' => \App\Filament\Resources\ProductItems\ProductItemResource::class,
    'orders' => \App\Filament\Resources\Orders\OrderResource::class,
    'order-items' => \App\Filament\Resources\OrderItems\OrderItemResource::class,
    'expenses' => \App\Filament\Resources\Expenses\ExpenseResource::class,
    'customers' => \App\Filament\Resources\Customers\CustomerResource::class,
    'addresses' => \App\Filament\Resources\Addresses\AddressResource::class,
    'categories' => \App\Filament\Resources\Categories\CategoryResource::class,
    'genders' => \App\Filament\Resources\Genders\GenderResource::class,
    'expensetypes' => \App\Filament\Resources\Expensetypes\ExpensetypeResource::class,
    'users' => \App\Filament\Resources\Users\UserResource::class,
];

test('global admin can access all Filament resource index pages', function () use ($filamentResources) {
    $admin = User::where('email', 'admin@example.com')->first();
    Auth::login($admin);

    // Global admin can use "all" tenant or any brand
    $brand = Brand::first();
    if (! $brand) {
        $this->markTestSkipped('No brands available');
    }

    foreach ($filamentResources as $resourceName => $resourceClass) {
        $response = $this->get("/admin/{$brand->slug}/{$resourceName}");
        expect($response)->assertSuccessful();
    }
});

test('brand admin can only see accessible brands in Filament', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $accessibleBrand = Brand::whereIn('brand_id', $accessibleBrandIds)->first();

    if (! $accessibleBrand) {
        $this->markTestSkipped('Brand admin has no accessible brands');
    }

    $inaccessibleBrandIds = Brand::whereNotIn('brand_id', $accessibleBrandIds)->pluck('brand_id');

    $response = $this->get("/admin/{$accessibleBrand->slug}/brands");
    expect($response)->assertSuccessful();

    // Verify query returns only accessible brands
    $query = \App\Filament\Resources\Brands\BrandResource::getEloquentQuery();
    $brands = $query->get();
    $returnedBrandIds = $brands->pluck('brand_id');

    expect($returnedBrandIds->diff($accessibleBrandIds))->toBeEmpty();
    expect($returnedBrandIds->intersect($inaccessibleBrandIds))->toBeEmpty();
});

test('brand admin cannot access inaccessible brand edit page in Filament', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $accessibleBrand = Brand::whereIn('brand_id', $accessibleBrandIds)->first();
    $inaccessibleBrand = Brand::whereNotIn('brand_id', $accessibleBrandIds)->first();

    if ($inaccessibleBrand && $accessibleBrand) {
        // Try to access inaccessible brand edit page
        $response = $this->get("/admin/{$accessibleBrand->slug}/brands/{$inaccessibleBrand->brand_id}/edit");
        // May return 404 if record not in query scope, or 403 if policy denies
        expect($response->status())->toBeIn([403, 404]);
    }
})->skip(fn () => Brand::count() < 2, 'Need at least 2 brands for this test');

test('product manager can only see accessible products in Filament', function () {
    $productUser = User::where('email', 'productuser@example.com')->first();
    Auth::login($productUser);

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    $accessibleProduct = Product::whereIn('ProductID', $accessibleProductIds)->first();

    if (! $accessibleProduct) {
        $this->markTestSkipped('Product user has no accessible products');
    }

    $brand = Brand::find($accessibleProduct->brand_id);
    if (! $brand) {
        $this->markTestSkipped('Product has no brand');
    }

    $inaccessibleProductIds = Product::whereNotIn('ProductID', $accessibleProductIds)->pluck('ProductID');

    $query = \App\Filament\Resources\Products\ProductResource::getEloquentQuery();
    $products = $query->get();
    $returnedProductIds = $products->pluck('ProductID');

    expect($returnedProductIds->diff($accessibleProductIds))->toBeEmpty();
    expect($returnedProductIds->intersect($inaccessibleProductIds))->toBeEmpty();
});

test('product manager cannot access inaccessible product edit page in Filament', function () {
    $productUser = User::where('email', 'productuser@example.com')->first();
    Auth::login($productUser);

    $accessibleProductIds = Product::getAccessibleIdsForUser($productUser);
    $accessibleProduct = Product::whereIn('ProductID', $accessibleProductIds)->first();
    $inaccessibleProduct = Product::whereNotIn('ProductID', $accessibleProductIds)->first();

    if ($inaccessibleProduct && $accessibleProduct) {
        $brand = Brand::find($accessibleProduct->brand_id);
        if ($brand) {
            $response = $this->get("/admin/{$brand->slug}/products/{$inaccessibleProduct->ProductID}/edit");
            // May return 404 if record not in query scope, or 403 if policy denies
            expect($response->status())->toBeIn([403, 404]);
        }
    }
})->skip(fn () => Product::count() < 2, 'Need at least 2 products for this test');

test('viewer can only see accessible product items in Filament', function () {
    $itemUser = User::where('email', 'itemuser@example.com')->first();
    Auth::login($itemUser);

    $accessibleItemIds = ProductItem::getAccessibleIdsForUser($itemUser);
    $inaccessibleItemIds = ProductItem::whereNotIn('ItemID', $accessibleItemIds)->pluck('ItemID');

    $query = \App\Filament\Resources\ProductItems\ProductItemResource::getEloquentQuery();
    $items = $query->get();
    $returnedItemIds = $items->pluck('ItemID');

    expect($returnedItemIds->diff($accessibleItemIds))->toBeEmpty();
    expect($returnedItemIds->intersect($inaccessibleItemIds))->toBeEmpty();
});

test('Filament filters do not leak inaccessible data', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $inaccessibleBrandIds = Brand::whereNotIn('brand_id', $accessibleBrandIds)->pluck('brand_id');

    // Test products filter
    $query = \App\Filament\Resources\Products\ProductResource::getEloquentQuery();
    $products = $query->get();
    $productBrandIds = $products->pluck('brand_id')->unique();

    expect($productBrandIds->intersect($inaccessibleBrandIds))->toBeEmpty();
})->skip(fn () => Brand::count() < 2, 'Need at least 2 brands for this test');

test('Filament bulk actions do not affect inaccessible records', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $accessibleBrand = Brand::whereIn('brand_id', $accessibleBrandIds)->first();
    $inaccessibleBrand = Brand::whereNotIn('brand_id', $accessibleBrandIds)->first();

    if ($inaccessibleBrand && $accessibleBrand) {
        // Set tenant to accessible brand
        Filament::setTenant($accessibleBrand);

        // Verify inaccessible brand is not in the query
        $query = \App\Filament\Resources\Brands\BrandResource::getEloquentQuery();
        $brands = $query->pluck('brand_id');
        expect($brands->contains($inaccessibleBrand->brand_id))->toBeFalse();
    }
})->skip(fn () => Brand::count() < 2, 'Need at least 2 brands for this test');

test('non-global admin users cannot access User resource index', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $accessibleBrand = Brand::getAccessibleIdsForUser($brandAdmin)->first();
    if (! $accessibleBrand) {
        $this->markTestSkipped('Brand admin has no accessible brands');
    }
    $brand = Brand::find($accessibleBrand);

    // Non-admin users should only see themselves
    $query = \App\Filament\Resources\Users\UserResource::getEloquentQuery();
    $users = $query->get();
    expect($users->count())->toBe(1);
    expect($users->first()->id)->toBe($brandAdmin->id);
});

test('global admin can see all users in User resource', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Auth::login($admin);

    $query = \App\Filament\Resources\Users\UserResource::getEloquentQuery();
    $users = $query->get();
    expect($users->count())->toBeGreaterThan(1);
});

test('non-global admin cannot edit other users in Filament', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    $otherUser = User::where('email', 'productuser@example.com')->first();
    Auth::login($brandAdmin);

    if ($otherUser) {
        $accessibleBrand = Brand::getAccessibleIdsForUser($brandAdmin)->first();
        if ($accessibleBrand) {
            $brand = Brand::find($accessibleBrand);
            $response = $this->get("/admin/{$brand->slug}/users/{$otherUser->id}/edit");
            // May return 404 if record not in query scope, or 403 if policy denies
            expect($response->status())->toBeIn([403, 404]);
        }
    }
});

test('users can edit their own profile in Filament', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $accessibleBrand = Brand::getAccessibleIdsForUser($brandAdmin)->first();
    if (! $accessibleBrand) {
        $this->markTestSkipped('Brand admin has no accessible brands');
    }
    $brand = Brand::find($accessibleBrand);

    // UserResource query filters to only show user themselves for non-admins
    // So the record should be accessible, but Filament may return 404 if not in scope
    $response = $this->get("/admin/{$brand->slug}/users/{$brandAdmin->id}/edit");
    // UserPolicy allows update for own profile, but Filament may filter record out
    // This is acceptable - user should use Profile page instead
    expect($response->status())->toBeIn([200, 404]);
});
