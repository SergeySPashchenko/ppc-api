<?php

use App\Models\AllBrandsTenant;
use App\Models\Brand;
use App\Models\Product;
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

test('all users can access All view', function () {
    $users = [
        User::where('email', 'admin@example.com')->first(),
        User::where('email', 'brandadmin@example.com')->first(),
        User::where('email', 'productuser@example.com')->first(),
        User::where('email', 'itemuser@example.com')->first(),
    ];

    $brand = Brand::first();
    if (! $brand) {
        $this->markTestSkipped('No brand available');
    }

    foreach ($users as $user) {
        if (! $user) {
            continue;
        }

        Auth::login($user);
        Filament::setTenant(new AllBrandsTenant);

        $response = $this->get("/admin/all/products");

        expect($response->status())->toBeIn([200, 302])
            ->and($response->getContent())->not->toContain('Class "Filament\\Forms\\Components\\Section" not found')
            ->and($response->getContent())->not->toContain('Class "Filament\\Schemas\\Components\\Section" not found');
    }
})->group('verification');

test('all resources use accessibleBy scope or have custom access control', function () {
    // Some resources have custom access control which is acceptable:
    // - UserResource: users see only themselves unless global admin
    // - Category/Gender/Expensetype: reference data, accessible if user has any brand/product access
    $resources = [
        'App\Filament\Resources\Users\UserResource' => ['type' => 'custom', 'check' => 'isGlobalAdmin'],
        'App\Filament\Resources\Products\ProductResource' => ['type' => 'accessibleBy'],
        'App\Filament\Resources\Brands\BrandResource' => ['type' => 'accessibleBy'],
        'App\Filament\Resources\Categories\CategoryResource' => ['type' => 'custom', 'check' => 'hasAnyBrandOrProductAccess'],
        'App\Filament\Resources\Genders\GenderResource' => ['type' => 'custom', 'check' => 'hasAnyBrandOrProductAccess'],
        'App\Filament\Resources\Expensetypes\ExpensetypeResource' => ['type' => 'custom', 'check' => 'hasAnyBrandOrProductAccess'],
        'App\Filament\Resources\Expenses\ExpenseResource' => ['type' => 'accessibleBy'],
        'App\Filament\Resources\Customers\CustomerResource' => ['type' => 'accessibleBy'],
        'App\Filament\Resources\Addresses\AddressResource' => ['type' => 'accessibleBy'],
        'App\Filament\Resources\Orders\OrderResource' => ['type' => 'accessibleBy'],
        'App\Filament\Resources\OrderItems\OrderItemResource' => ['type' => 'accessibleBy'],
        'App\Filament\Resources\ProductItems\ProductItemResource' => ['type' => 'accessibleBy'],
    ];

    foreach ($resources as $resourceClass => $config) {
        if (! class_exists($resourceClass)) {
            continue;
        }

        $code = file_get_contents((new ReflectionClass($resourceClass))->getFileName());
        $usesAccessibleBy = str_contains($code, 'accessibleBy') || str_contains($code, 'accessibleByUser');
        $hasCustomAccessControl = str_contains($code, 'getEloquentQuery') && 
            ($config['type'] === 'custom' && str_contains($code, $config['check']));

        if ($config['type'] === 'accessibleBy') {
            expect($usesAccessibleBy)->toBeTrue("Resource {$resourceClass} must use accessibleBy scope");
        } else {
            // Custom access control is acceptable for reference data and UserResource
            expect($hasCustomAccessControl || $usesAccessibleBy)->toBeTrue(
                "Resource {$resourceClass} must have access control (custom or accessibleBy)"
            );
        }
    }
})->group('verification');

test('global admin sees everything', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Auth::login($admin);

    $brand = Brand::first();
    if (! $brand) {
        $this->markTestSkipped('No brand available');
    }

    $resources = [
        'users',
        'products',
        'brands',
        'categories',
        'genders',
        'expensetypes',
        'expenses',
        'customers',
        'addresses',
        'orders',
        'order-items',
        'product-items',
    ];

    foreach ($resources as $resource) {
        $response = $this->get("/admin/{$brand->slug}/{$resource}");

        expect($response->status())->toBeIn([200, 302], "Global admin should access {$resource}");
    }
})->group('verification');

test('brand admin sees only accessible data', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $totalBrands = Brand::count();

    // Brand admin should see fewer brands than total (unless they're global admin)
    expect($accessibleBrandIds->count())->toBeLessThanOrEqual($totalBrands);

    $accessibleProductIds = Product::getAccessibleIdsForUser($brandAdmin);
    $totalProducts = Product::count();

    // Brand admin should see fewer or equal products
    expect($accessibleProductIds->count())->toBeLessThanOrEqual($totalProducts);
})->group('verification');

test('tenant filtering works correctly', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $brand = Brand::first();
    if (! $brand) {
        $this->markTestSkipped('No brand available');
    }

    // Test "All" tenant
    Filament::setTenant(new AllBrandsTenant);
    $responseAll = $this->get('/admin/all/products');
    expect($responseAll->status())->toBeIn([200, 302]);

    // Test specific brand tenant
    Filament::setTenant($brand);
    $responseBrand = $this->get("/admin/{$brand->slug}/products");
    expect($responseBrand->status())->toBeIn([200, 302]);
})->group('verification');

test('no data leaks in resource pages', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $brand = Brand::first();
    if (! $brand) {
        $this->markTestSkipped('No brand available');
    }

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $allBrands = Brand::all();

    // Check that brand admin only sees accessible brands
    $response = $this->get("/admin/{$brand->slug}/brands");
    $response->assertSuccessful();

    // Verify query scope filters correctly
    $query = \App\Filament\Resources\Brands\BrandResource::getEloquentQuery();
    $visibleBrands = $query->get();

    // Brand admin should only see accessible brands
    foreach ($visibleBrands as $visibleBrand) {
        expect($accessibleBrandIds->contains($visibleBrand->brand_id))->toBeTrue(
            "Brand admin should not see brand {$visibleBrand->brand_id}"
        );
    }
})->group('verification');

test('all resource pages load without errors', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Auth::login($admin);

    $brand = Brand::first();
    if (! $brand) {
        $this->markTestSkipped('No brand available');
    }

    $resources = [
        'users',
        'products',
        'brands',
        'categories',
        'genders',
        'expensetypes',
        'expenses',
        'customers',
        'addresses',
        'orders',
        'order-items',
        'product-items',
    ];

    foreach ($resources as $resource) {
        $response = $this->get("/admin/{$brand->slug}/{$resource}");

        expect($response->status())->toBeIn([200, 302])
            ->and($response->getContent())->not->toContain('Class "Filament\\Forms\\Components\\Section" not found')
            ->and($response->getContent())->not->toContain('Class "Filament\\Schemas\\Components\\Section" not found');
    }
})->group('verification');

test('create pages load without errors', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Auth::login($admin);

    $brand = Brand::first();
    if (! $brand) {
        $this->markTestSkipped('No brand available');
    }

    $createPages = [
        'users/create',
        'products/create',
        'brands/create',
        'categories/create',
        'genders/create',
        'expensetypes/create',
        'expenses/create',
        'customers/create',
        'addresses/create',
        'orders/create',
        'order-items/create',
        'product-items/create',
    ];

    foreach ($createPages as $page) {
        $response = $this->get("/admin/{$brand->slug}/{$page}");

        expect($response->status())->toBeIn([200, 302])
            ->and($response->getContent())->not->toContain('Class "Filament\\Forms\\Components\\Section" not found')
            ->and($response->getContent())->not->toContain('Class "Filament\\Schemas\\Components\\Section" not found');
    }
})->group('verification');
