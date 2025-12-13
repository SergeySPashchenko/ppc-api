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

test('global admin always has "All" tenant option', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Auth::login($admin);

    // Global admin should be able to access AllBrandsTenant
    $allTenant = new AllBrandsTenant;
    Filament::setTenant($allTenant);

    $query = \App\Filament\Resources\Brands\BrandResource::getEloquentQuery();
    $brands = $query->get();

    // Should see all brands when "All" is selected
    expect($brands->count())->toBe(Brand::count());
});

test('brand admin with single brand access has no extra tenant options', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    expect($accessibleBrandIds->count())->toBe(1);

    // When tenant is set to their accessible brand, should only see that brand's data
    $accessibleBrand = Brand::whereIn('brand_id', $accessibleBrandIds)->first();
    if ($accessibleBrand) {
        Filament::setTenant($accessibleBrand);

        $query = \App\Filament\Resources\Products\ProductResource::getEloquentQuery();
        $products = $query->get();
        $productBrandIds = $products->pluck('brand_id')->unique();

        expect($productBrandIds->count())->toBe(1);
        expect($productBrandIds->first())->toBe($accessibleBrand->brand_id);
    }
});

test('multi-brand user can switch between accessible brands', function () {
    $multiBrandUser = User::where('email', 'multibranduser@example.com')->first();
    Auth::login($multiBrandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($multiBrandUser);
    if ($accessibleBrandIds->count() < 2) {
        $this->markTestSkipped('Multi-brand user needs at least 2 accessible brands');
    }

    // Test each accessible brand
    foreach ($accessibleBrandIds as $brandId) {
        $brand = Brand::find($brandId);
        if ($brand) {
            Filament::setTenant($brand);

            $query = \App\Filament\Resources\Products\ProductResource::getEloquentQuery();
            $products = $query->get();
            $productBrandIds = $products->pluck('brand_id')->unique();

            expect($productBrandIds->count())->toBe(1);
            expect($productBrandIds->first())->toBe($brandId);
        }
    }
})->skip(fn () => Brand::count() < 2, 'Need at least 2 brands for this test');

test('"All" tenant shows only accessible data for non-global admins', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $allTenant = new AllBrandsTenant;
    Filament::setTenant($allTenant);

    // Even with "All" tenant, should only see accessible brands
    $query = \App\Filament\Resources\Brands\BrandResource::getEloquentQuery();
    $brands = $query->get();
    $returnedBrandIds = $brands->pluck('brand_id');

    expect($returnedBrandIds->diff($accessibleBrandIds))->toBeEmpty();
    expect($accessibleBrandIds->diff($returnedBrandIds))->toBeEmpty();
});

test('brand tenant filters products correctly', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Auth::login($brandAdmin);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $accessibleBrand = Brand::whereIn('brand_id', $accessibleBrandIds)->first();

    if ($accessibleBrand) {
        Filament::setTenant($accessibleBrand);

        $query = \App\Filament\Resources\Products\ProductResource::getEloquentQuery();
        $products = $query->get();

        // All products should belong to the selected brand
        $products->each(function ($product) use ($accessibleBrand) {
            expect($product->brand_id)->toBe($accessibleBrand->brand_id);
        });
    }
});

test('switching tenants updates data correctly', function () {
    $multiBrandUser = User::where('email', 'multibranduser@example.com')->first();
    Auth::login($multiBrandUser);

    $accessibleBrandIds = Brand::getAccessibleIdsForUser($multiBrandUser);
    if ($accessibleBrandIds->count() < 2) {
        $this->markTestSkipped('Need at least 2 accessible brands for this test');
    }

    $brand1 = Brand::find($accessibleBrandIds->first());
    $brand2 = Brand::find($accessibleBrandIds->skip(1)->first());

    if (! $brand1 || ! $brand2) {
        $this->markTestSkipped('Could not find both brands');
    }

    // Switch to brand 1
    Filament::setTenant($brand1);
    $query1 = \App\Filament\Resources\Products\ProductResource::getEloquentQuery();
    $products1 = $query1->get();
    $productIds1 = $products1->pluck('ProductID');

    // Switch to brand 2
    Filament::setTenant($brand2);
    $query2 = \App\Filament\Resources\Products\ProductResource::getEloquentQuery();
    $products2 = $query2->get();
    $productIds2 = $products2->pluck('ProductID');

    // Product IDs should be different (unless brands share products, which shouldn't happen)
    expect($productIds1->intersect($productIds2))->toBeEmpty();
})->skip(fn () => Brand::count() < 2, 'Need at least 2 brands for this test');
