<?php

use App\Models\Access;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DataSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(DataSeeder::class);
});

test('global admin can perform all actions via policies', function () {
    $admin = User::where('email', 'admin@example.com')->first();

    $brand = Brand::first();
    expect($admin->can('viewAny', Brand::class))->toBeTrue();
    expect($admin->can('view', $brand))->toBeTrue();
    expect($admin->can('create', Brand::class))->toBeTrue();
    expect($admin->can('update', $brand))->toBeTrue();
    expect($admin->can('delete', $brand))->toBeTrue();

    $product = Product::first();
    expect($admin->can('viewAny', Product::class))->toBeTrue();
    expect($admin->can('view', $product))->toBeTrue();
    expect($admin->can('create', Product::class))->toBeTrue();
    expect($admin->can('update', $product))->toBeTrue();
    expect($admin->can('delete', $product))->toBeTrue();
});

test('user without permission cannot view models', function () {
    $user = User::factory()->create();
    $brand = Brand::first();

    expect($user->can('viewAny', Brand::class))->toBeFalse();
    expect($user->can('view', $brand))->toBeFalse();
    expect($user->can('create', Brand::class))->toBeFalse();
    expect($user->can('update', $brand))->toBeFalse();
    expect($user->can('delete', $brand))->toBeFalse();
});

test('user with permission but no access cannot view inaccessible model', function () {
    $viewerRole = Role::where('name', 'viewer')->first();
    $user = User::factory()->create();
    $user->assignRole($viewerRole);

    // User has ViewAny:Brand permission but no access to brands
    expect($user->can('viewAny', Brand::class))->toBeTrue();

    // But cannot view a specific brand they don't have access to
    $brand = Brand::first();
    expect($user->can('view', $brand))->toBeFalse();
});

test('user with permission and access can view accessible model', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $brand = Brand::find($accessibleBrandIds->first());

    expect($brandAdmin->can('viewAny', Brand::class))->toBeTrue();
    expect($brandAdmin->can('view', $brand))->toBeTrue();
});

test('user cannot view inaccessible model even with permission', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $allBrandIds = Brand::pluck('brand_id');
    $inaccessibleBrandId = $allBrandIds->diff($accessibleBrandIds)->first();

    if ($inaccessibleBrandId) {
        $inaccessibleBrand = Brand::find($inaccessibleBrandId);
        expect($brandAdmin->can('view', $inaccessibleBrand))->toBeFalse();
    }
})->skip(fn () => Brand::count() < 2, 'Need at least 2 brands for this test');

test('brand admin role has correct permissions', function () {
    $brandAdminRole = Role::where('name', 'brand_admin')->first();
    expect($brandAdminRole)->not->toBeNull();

    // Should have permissions for brands, products, etc.
    expect($brandAdminRole->hasPermissionTo('ViewAny:Brand'))->toBeTrue();
    expect($brandAdminRole->hasPermissionTo('View:Brand'))->toBeTrue();
    expect($brandAdminRole->hasPermissionTo('Create:Brand'))->toBeTrue();
    expect($brandAdminRole->hasPermissionTo('Update:Brand'))->toBeTrue();
    expect($brandAdminRole->hasPermissionTo('Delete:Brand'))->toBeTrue();

    expect($brandAdminRole->hasPermissionTo('ViewAny:Product'))->toBeTrue();
    expect($brandAdminRole->hasPermissionTo('ViewAny:ProductItem'))->toBeTrue();
});

test('product manager role has limited permissions', function () {
    $productManagerRole = Role::where('name', 'product_manager')->first();
    expect($productManagerRole)->not->toBeNull();

    // Should have permissions for products and product items
    expect($productManagerRole->hasPermissionTo('ViewAny:Product'))->toBeTrue();
    expect($productManagerRole->hasPermissionTo('ViewAny:ProductItem'))->toBeTrue();

    // Should NOT have permissions for brands
    expect($productManagerRole->hasPermissionTo('ViewAny:Brand'))->toBeFalse();
});

test('viewer role has read-only permissions', function () {
    $viewerRole = Role::where('name', 'viewer')->first();
    expect($viewerRole)->not->toBeNull();

    // Should have ViewAny and View permissions
    expect($viewerRole->hasPermissionTo('ViewAny:Brand'))->toBeTrue();
    expect($viewerRole->hasPermissionTo('View:Brand'))->toBeTrue();

    // Should NOT have create/update/delete permissions
    expect($viewerRole->hasPermissionTo('Create:Brand'))->toBeFalse();
    expect($viewerRole->hasPermissionTo('Update:Brand'))->toBeFalse();
    expect($viewerRole->hasPermissionTo('Delete:Brand'))->toBeFalse();
});

test('api endpoints respect policy authorization', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // User without permissions should get 403
    $response = $this->getJson('/api/brands');
    $response->assertForbidden();
});

test('api endpoints allow access with permission and model access', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($brandAdmin);

    $response = $this->getJson('/api/brands');
    $response->assertSuccessful();
});

test('cache is cleared when access is created', function () {
    $user = User::where('email', 'brandadmin@example.com')->first();
    $allBrands = Brand::all();
    $accessibleBrandIds = Brand::getAccessibleIdsForUser($user);
    $inaccessibleBrand = $allBrands->whereNotIn('brand_id', $accessibleBrandIds)->first();

    if ($inaccessibleBrand) {
        $cacheKey = 'accessible_ids:brand:user:'.$user->id;

        // Get accessible IDs (should cache)
        Brand::getAccessibleIdsForUser($user);

        // Verify cache exists
        expect(Cache::has($cacheKey))->toBeTrue();

        // Create new access for a brand the user doesn't have access to
        Access::create([
            'user_id' => $user->id,
            'accessible_type' => Brand::getMorphType(),
            'accessible_id' => $inaccessibleBrand->brand_id,
            'level' => 1,
        ]);

        // Cache should be cleared
        expect(Cache::has($cacheKey))->toBeFalse();
    }
})->skip(fn () => Brand::count() < 2, 'Need at least 2 brands for this test');

test('cache is cleared when access is updated', function () {
    $user = User::where('email', 'brandadmin@example.com')->first();
    $access = Access::where('user_id', $user->id)->first();

    if ($access) {
        $cacheKey = 'accessible_ids:brand:user:'.$user->id;

        // Get accessible IDs (should cache)
        Brand::getAccessibleIdsForUser($user);
        expect(Cache::has($cacheKey))->toBeTrue();

        // Update access
        $access->update(['level' => 2]);

        // Cache should be cleared
        expect(Cache::has($cacheKey))->toBeFalse();
    }
})->skip(fn () => Access::count() === 0, 'Need at least 1 access record for this test');

test('cache is cleared when access is deleted', function () {
    $user = User::where('email', 'brandadmin@example.com')->first();
    $access = Access::where('user_id', $user->id)->first();

    if ($access) {
        $cacheKey = 'accessible_ids:brand:user:'.$user->id;

        // Get accessible IDs (should cache)
        Brand::getAccessibleIdsForUser($user);
        expect(Cache::has($cacheKey))->toBeTrue();

        // Delete access
        $access->delete();

        // Cache should be cleared
        expect(Cache::has($cacheKey))->toBeFalse();
    }
})->skip(fn () => Access::count() === 0, 'Need at least 1 access record for this test');

test('access inheritance works through policies', function () {
    $brandAdmin = User::where('email', 'brandadmin@example.com')->first();
    $accessibleBrandIds = Brand::getAccessibleIdsForUser($brandAdmin);
    $brandId = $accessibleBrandIds->first();
    $product = Product::where('brand_id', $brandId)->first();

    if ($product) {
        // Brand admin should be able to view products from accessible brand
        expect($brandAdmin->can('view', $product))->toBeTrue();
    }
})->skip(fn () => Product::count() === 0, 'Need at least 1 product for this test');

test('filament resources respect policies', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // User without permissions should not see resources
    $response = $this->get('/admin/all/brands');
    $response->assertForbidden();
});

test('all permissions are created for all models', function () {
    $models = ['User', 'Brand', 'Product', 'ProductItem', 'Order', 'OrderItem', 'Expense', 'Customer', 'Address', 'Category', 'Gender', 'Expensetype'];
    $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete', 'ForceDeleteAny', 'RestoreAny', 'Replicate', 'Reorder'];

    foreach ($models as $model) {
        foreach ($actions as $action) {
            $permissionName = "{$action}:{$model}";
            $permission = Permission::where('name', $permissionName)->first();
            expect($permission)->not->toBeNull("Permission {$permissionName} should exist");
        }
    }
});

test('super admin role has all permissions', function () {
    $superAdminRole = Role::where('name', 'super_admin')->first();
    expect($superAdminRole)->not->toBeNull();

    $permissionCount = Permission::count();
    expect($superAdminRole->permissions->count())->toBe($permissionCount);
});

test('user policy allows global admin to view all users', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    $otherUser = User::where('email', 'brandadmin@example.com')->first();

    expect($admin->can('viewAny', User::class))->toBeTrue();
    expect($admin->can('view', $otherUser))->toBeTrue();
    expect($admin->can('create', User::class))->toBeTrue();
    expect($admin->can('update', $otherUser))->toBeTrue();
    expect($admin->can('delete', $otherUser))->toBeTrue();
});

test('user policy allows users to view their own profile', function () {
    $user = User::where('email', 'brandadmin@example.com')->first();

    expect($user->can('view', $user))->toBeTrue();
    expect($user->can('update', $user))->toBeTrue();
});

test('user policy prevents users from viewing other users', function () {
    $user = User::where('email', 'brandadmin@example.com')->first();
    $otherUser = User::where('email', 'productuser@example.com')->first();

    expect($user->can('viewAny', User::class))->toBeFalse();
    expect($user->can('view', $otherUser))->toBeFalse();
    expect($user->can('update', $otherUser))->toBeFalse();
    expect($user->can('delete', $otherUser))->toBeFalse();
});

test('user policy prevents users from deleting themselves', function () {
    $user = User::where('email', 'brandadmin@example.com')->first();

    expect($user->can('delete', $user))->toBeFalse();
    expect($user->can('forceDelete', $user))->toBeFalse();
});

test('api user endpoints respect policy authorization', function () {
    $user = User::where('email', 'brandadmin@example.com')->first();
    Sanctum::actingAs($user);

    // Non-admin users should only see themselves
    $response = $this->getJson('/api/users');
    $response->assertSuccessful();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['id'])->toBe($user->id);

    // Can view own profile
    $response = $this->getJson("/api/users/{$user->id}");
    $response->assertSuccessful();

    // Cannot view other users
    $otherUser = User::where('email', 'productuser@example.com')->first();
    $response = $this->getJson("/api/users/{$otherUser->id}");
    $response->assertForbidden();
});

test('api user endpoints allow global admin to view all users', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/users');
    $response->assertSuccessful();
    $data = $response->json('data');
    expect($data)->not->toBeEmpty();

    // Can view any user
    $otherUser = User::where('email', 'brandadmin@example.com')->first();
    $response = $this->getJson("/api/users/{$otherUser->id}");
    $response->assertSuccessful();
});
