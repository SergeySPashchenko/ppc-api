<?php

namespace App\Providers;

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
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Role;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'access' => Access::class,
            'product' => Product::class,
            'product_item' => ProductItem::class,
            'category' => Category::class,
            'gender' => Gender::class,
            'brand' => Brand::class,
            'order' => Order::class,
            'order_item' => OrderItem::class,
            'customer' => Customer::class,
            'address' => Address::class,
            'expense' => Expense::class,
            'expensetype' => Expensetype::class,
            'role' => Role::class,
            'permission' => Permission::class,
        ]);
        app(PermissionRegistrar::class)
            ->setPermissionClass(Permission::class)
            ->setRoleClass(Role::class);

        // Disable tenancy for Shield resources (Role, Permission, etc.)
        // They should not be scoped to tenants
        // Use reflection to set the static property
        try {
            $reflection = new \ReflectionClass(RoleResource::class);
            if ($reflection->hasProperty('isScopedToTenant')) {
                $property = $reflection->getProperty('isScopedToTenant');
                $property->setAccessible(true);
                $property->setValue(null, false);
            }
        } catch (\ReflectionException $e) {
            // Fallback: try using scopeToTenant method if available
            if (method_exists(RoleResource::class, 'scopeToTenant')) {
                RoleResource::scopeToTenant(false);
            }
        }
    }
}
