<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create super admin role
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Define all models that need permissions
        $models = [
            'User' => \App\Models\User::class,
            'Brand' => Brand::class,
            'Product' => Product::class,
            'ProductItem' => ProductItem::class,
            'Order' => Order::class,
            'OrderItem' => OrderItem::class,
            'Expense' => Expense::class,
            'Customer' => Customer::class,
            'Address' => Address::class,
            'Category' => Category::class,
            'Gender' => Gender::class,
            'Expensetype' => Expensetype::class,
        ];

        // Define permission actions
        $actions = [
            'ViewAny',
            'View',
            'Create',
            'Update',
            'Delete',
            'Restore',
            'ForceDelete',
            'ForceDeleteAny',
            'RestoreAny',
            'Replicate',
            'Reorder',
        ];

        // Create permissions for all models and actions
        $allPermissions = [];
        foreach ($models as $modelName => $modelClass) {
            foreach ($actions as $action) {
                $permissionName = "{$action}:{$modelName}";
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
                $allPermissions[] = $permission;
            }
        }

        // Assign all permissions to super admin role
        $superAdminRole->syncPermissions($allPermissions);

        // Create additional roles with specific permissions
        $this->createBrandAdminRole($allPermissions);
        $this->createProductManagerRole($allPermissions);
        $this->createViewerRole($allPermissions);

        $this->command->info('Permissions created and assigned successfully!');
        $this->command->info('Created '.count($allPermissions).' permissions');
    }

    /**
     * Create Brand Admin role with full access to brands and related data.
     */
    protected function createBrandAdminRole(array $allPermissions): void
    {
        $role = Role::firstOrCreate(['name' => 'brand_admin', 'guard_name' => 'web']);

        // Brand admin can manage brands, products, product items, orders, expenses, customers, addresses
        $allowedModels = ['Brand', 'Product', 'ProductItem', 'Order', 'OrderItem', 'Expense', 'Customer', 'Address'];
        $allowedActions = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore'];

        $permissions = collect($allPermissions)->filter(function ($permission) use ($allowedModels, $allowedActions) {
            foreach ($allowedModels as $model) {
                foreach ($allowedActions as $action) {
                    if ($permission->name === "{$action}:{$model}") {
                        return true;
                    }
                }
            }

            return false;
        });

        $role->syncPermissions($permissions);
    }

    /**
     * Create Product Manager role with product and product item access.
     */
    protected function createProductManagerRole(array $allPermissions): void
    {
        $role = Role::firstOrCreate(['name' => 'product_manager', 'guard_name' => 'web']);

        // Product manager can manage products, product items, and order items (inherited access)
        $allowedModels = ['Product', 'ProductItem', 'OrderItem'];
        $allowedActions = ['ViewAny', 'View', 'Create', 'Update', 'Delete'];

        $permissions = collect($allPermissions)->filter(function ($permission) use ($allowedModels, $allowedActions) {
            foreach ($allowedModels as $model) {
                foreach ($allowedActions as $action) {
                    if ($permission->name === "{$action}:{$model}") {
                        return true;
                    }
                }
            }

            return false;
        });

        $role->syncPermissions($permissions);
    }

    /**
     * Create Viewer role with read-only access.
     */
    protected function createViewerRole(array $allPermissions): void
    {
        $role = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        // Viewer can only view
        $allowedActions = ['ViewAny', 'View'];

        $permissions = collect($allPermissions)->filter(function ($permission) use ($allowedActions) {
            foreach ($allowedActions as $action) {
                if (str_starts_with($permission->name, "{$action}:")) {
                    return true;
                }
            }

            return false;
        });

        $role->syncPermissions($permissions);
    }
}
