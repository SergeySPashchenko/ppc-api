<?php

namespace Database\Seeders;

use App\Models\Access;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Gender;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AccessControlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Create categories and genders
        $categories = Category::factory(5)->create();
        $genders = Gender::factory(4)->create();

        // Create brands
        $brands = Brand::factory(5)->create();

        // Create products for each brand
        $products = collect();
        foreach ($brands as $brand) {
            $brandProducts = Product::factory(3)->create([
                'brand_id' => $brand->brand_id,
                'main_category_id' => $categories->random()->category_id,
                'marketing_category_id' => $categories->random()->category_id,
                'gender_id' => $genders->random()->gender_id,
            ]);
            $products = $products->merge($brandProducts);

            // Create product items for each product
            foreach ($brandProducts as $index => $product) {
                for ($j = 1; $j <= 2; $j++) {
                    ProductItem::create([
                        'ProductID' => $product->ProductID,
                        'ProductName' => "{$product->Product} - Item {$j}",
                        'slug' => strtolower("{$product->slug}-item-{$j}"),
                        'SKU' => "SKU-{$brand->brand_id}-{$product->ProductID}-{$j}",
                        'Quantity' => rand(10, 100),
                        'upSell' => $j === 1,
                        'extraProduct' => $j === 2,
                        'offerProducts' => null,
                        'active' => true,
                        'deleted' => false,
                    ]);
                }
            }
        }

        // Get roles (created by PermissionSeeder)
        $brandAdminRole = Role::firstOrCreate(['name' => 'brand_admin', 'guard_name' => 'web']);
        $productManagerRole = Role::firstOrCreate(['name' => 'product_manager', 'guard_name' => 'web']);

        // Create users with different access levels

        // 1. Global Admin - can see everything
        $admin = User::factory()->create([
            'name' => 'Global Admin',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole($superAdminRole);

        // 2. User with access to one brand (should see all products of that brand)
        $brandUser = User::factory()->create([
            'name' => 'Brand User',
            'email' => 'brand@example.com',
        ]);
        $brandUser->assignRole($brandAdminRole);
        $brand1 = $brands->first();
        Access::create([
            'user_id' => $brandUser->id,
            'accessible_type' => Brand::getMorphType(),
            'accessible_id' => $brand1->brand_id,
            'level' => 1,
        ]);

        // 3. User with access to specific products only
        $productUser = User::factory()->create([
            'name' => 'Product User',
            'email' => 'product@example.com',
        ]);
        $productUser->assignRole($productManagerRole);
        $product1 = $products->first();
        $product2 = $products->skip(1)->first();
        Access::create([
            'user_id' => $productUser->id,
            'accessible_type' => Product::getMorphType(),
            'accessible_id' => $product1->ProductID,
            'level' => 1,
        ]);
        Access::create([
            'user_id' => $productUser->id,
            'accessible_type' => Product::getMorphType(),
            'accessible_id' => $product2->ProductID,
            'level' => 1,
        ]);

        // 4. User with access to multiple brands
        $multiBrandUser = User::factory()->create([
            'name' => 'Multi Brand User',
            'email' => 'multibrand@example.com',
        ]);
        $multiBrandUser->assignRole($brandAdminRole);
        $brand2 = $brands->skip(1)->first();
        $brand3 = $brands->skip(2)->first();
        Access::create([
            'user_id' => $multiBrandUser->id,
            'accessible_type' => Brand::getMorphType(),
            'accessible_id' => $brand2->brand_id,
            'level' => 1,
        ]);
        Access::create([
            'user_id' => $multiBrandUser->id,
            'accessible_type' => Brand::getMorphType(),
            'accessible_id' => $brand3->brand_id,
            'level' => 1,
        ]);

        // 5. User with mixed access (one brand + one specific product from another brand)
        $mixedUser = User::factory()->create([
            'name' => 'Mixed Access User',
            'email' => 'mixed@example.com',
        ]);
        $mixedUser->assignRole($brandAdminRole);
        $brand4 = $brands->skip(3)->first();
        $productFromOtherBrand = $products->where('brand_id', '!=', $brand4->brand_id)->first();
        Access::create([
            'user_id' => $mixedUser->id,
            'accessible_type' => Brand::getMorphType(),
            'accessible_id' => $brand4->brand_id,
            'level' => 1,
        ]);
        Access::create([
            'user_id' => $mixedUser->id,
            'accessible_type' => Product::getMorphType(),
            'accessible_id' => $productFromOtherBrand->ProductID,
            'level' => 1,
        ]);

        $this->command->info('Access control test data seeded successfully!');
        $this->command->info('Users created:');
        $this->command->info('  - admin@example.com (Global Admin)');
        $this->command->info('  - brand@example.com (Access to 1 brand)');
        $this->command->info('  - product@example.com (Access to 2 specific products)');
        $this->command->info('  - multibrand@example.com (Access to 2 brands)');
        $this->command->info('  - mixed@example.com (Access to 1 brand + 1 product from another brand)');
    }
}
