<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Create categories and genders
        $categories = collect([
            Category::create(['category_name' => 'Electronics', 'slug' => 'electronics']),
            Category::create(['category_name' => 'Clothing', 'slug' => 'clothing']),
            Category::create(['category_name' => 'Home & Garden', 'slug' => 'home-garden']),
            Category::create(['category_name' => 'Sports', 'slug' => 'sports']),
            Category::create(['category_name' => 'Books', 'slug' => 'books']),
        ]);

        $genders = collect([
            Gender::create(['gender_name' => 'Men', 'slug' => 'men']),
            Gender::create(['gender_name' => 'Women', 'slug' => 'women']),
            Gender::create(['gender_name' => 'Unisex', 'slug' => 'unisex']),
            Gender::create(['gender_name' => 'Kids', 'slug' => 'kids']),
        ]);

        // Create expense types
        $expenseTypes = collect([
            Expensetype::create(['Name' => 'Shipping', 'slug' => 'shipping']),
            Expensetype::create(['Name' => 'Packaging', 'slug' => 'packaging']),
            Expensetype::create(['Name' => 'Marketing', 'slug' => 'marketing']),
            Expensetype::create(['Name' => 'Storage', 'slug' => 'storage']),
        ]);

        // Create 3 brands
        $brands = collect([
            Brand::create(['brand_name' => 'TechBrand', 'slug' => 'techbrand']),
            Brand::create(['brand_name' => 'FashionBrand', 'slug' => 'fashionbrand']),
            Brand::create(['brand_name' => 'HomeBrand', 'slug' => 'homebrand']),
        ]);

        // Create multiple products per brand
        $products = collect();
        foreach ($brands as $brand) {
            $brandProducts = collect();
            for ($i = 1; $i <= 5; $i++) {
                $product = Product::create([
                    'Product' => "{$brand->brand_name} Product {$i}",
                    'slug' => strtolower("{$brand->slug}-product-{$i}"),
                    'newSystem' => $i % 2 === 0,
                    'Visible' => true,
                    'flyer' => $i <= 2,
                    'main_category_id' => $categories->random()->category_id,
                    'marketing_category_id' => $categories->random()->category_id,
                    'gender_id' => $genders->random()->gender_id,
                    'brand_id' => $brand->brand_id,
                ]);
                $brandProducts->push($product);

                // Create product items per product
                for ($j = 1; $j <= 3; $j++) {
                    ProductItem::create([
                        'ProductID' => $product->ProductID,
                        'ProductName' => "{$product->Product} - Item {$j}",
                        'slug' => strtolower("{$product->slug}-item-{$j}"),
                        'SKU' => "SKU-{$brand->brand_id}-{$product->ProductID}-{$j}",
                        'Quantity' => rand(10, 100),
                        'upSell' => $j === 1,
                        'extraProduct' => $j === 2,
                        'offerProducts' => $j === 3 ? 'promo' : null,
                        'active' => true,
                        'deleted' => false,
                    ]);
                }

                // Create expenses for some products
                if ($i <= 2) {
                    Expense::create([
                        'ProductID' => $product->ProductID,
                        'ExpenseID' => $expenseTypes->random()->ExpenseID,
                        'ExpenseDate' => now()->subDays(rand(1, 30)),
                        'Expense' => rand(100, 1000) / 10,
                    ]);
                }
            }
            $products = $products->merge($brandProducts);
        }

        // Create customers
        $customers = collect();
        for ($i = 1; $i <= 10; $i++) {
            $customer = Customer::create([
                'name' => "Customer {$i}",
                'email' => "customer{$i}@example.com",
                'phone' => "+1234567890{$i}",
            ]);

            // Create addresses for customers
            Address::create([
                'customer_id' => $customer->id,
                'type' => 'billing',
                'name' => "{$customer->name} Billing",
                'address' => "{$i} Main Street",
                'city' => 'New York',
                'state' => 'NY',
                'zip' => '1000'.$i,
                'country' => 'USA',
                'phone' => $customer->phone,
                'address_hash' => md5("{$customer->id}-billing"),
            ]);

            $customers->push($customer);
        }

        // Create orders
        $orders = collect();
        foreach ($customers->take(5) as $customer) {
            $product = $products->random();
            $order = Order::create([
                'OrderID' => 'ORD-'.str_pad((string) ($orders->count() + 1), 6, '0', STR_PAD_LEFT),
                'Agent' => 'Agent '.rand(1, 5),
                'Created' => now()->subDays(rand(1, 60)),
                'OrderDate' => now()->subDays(rand(1, 60)),
                'OrderNum' => 'ON-'.rand(1000, 9999),
                'ProductTotal' => rand(1000, 5000) / 10,
                'GrandTotal' => rand(1200, 6000) / 10,
                'RefundAmount' => 0,
                'Shipping' => rand(50, 200) / 10,
                'ShippingMethod' => ['Standard', 'Express', 'Overnight'][rand(0, 2)],
                'Refund' => false,
                'customer_id' => $customer->id,
                'BrandID' => $product->ProductID,
            ]);

            // Create order items
            $productItems = ProductItem::where('ProductID', $product->ProductID)->take(2)->get();
            foreach ($productItems as $item) {
                OrderItem::create([
                    'Price' => rand(100, 1000) / 10,
                    'Qty' => rand(1, 5),
                    'OrderID' => $order->id,
                    'ItemID' => $item->ItemID,
                ]);
            }

            // Attach addresses to orders
            $address = $customer->addresses()->first();
            if ($address) {
                $order->addresses()->attach($address->id);
            }

            $orders->push($order);
        }

        // Create users with different access levels
        // Get roles (created by PermissionSeeder)
        $brandAdminRole = Role::firstOrCreate(['name' => 'brand_admin', 'guard_name' => 'web']);
        $productManagerRole = Role::firstOrCreate(['name' => 'product_manager', 'guard_name' => 'web']);
        $viewerRole = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        // 1. Global Admin - can see everything
        $admin = User::create([
            'name' => 'Global Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'slug' => 'global-admin',
        ]);
        $admin->assignRole($superAdminRole);

        // 2. Brand Admin - access to full brand
        $brandAdmin = User::create([
            'name' => 'Brand Admin',
            'email' => 'brandadmin@example.com',
            'password' => Hash::make('password'),
            'slug' => 'brand-admin',
        ]);
        $brandAdmin->assignRole($brandAdminRole);
        $brand1 = $brands->first();
        Access::create([
            'user_id' => $brandAdmin->id,
            'accessible_type' => Brand::getMorphType(),
            'accessible_id' => $brand1->brand_id,
            'level' => 1,
        ]);

        // 3. Product-level user - access only to specific products
        $productUser = User::create([
            'name' => 'Product User',
            'email' => 'productuser@example.com',
            'password' => Hash::make('password'),
            'slug' => 'product-user',
        ]);
        $productUser->assignRole($productManagerRole);
        $product1 = $products->where('brand_id', $brands->skip(1)->first()->brand_id)->first();
        $product2 = $products->where('brand_id', $brands->skip(1)->first()->brand_id)->skip(1)->first();
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

        // 4. Product-item-level user - access only to specific items
        $itemUser = User::create([
            'name' => 'Product Item User',
            'email' => 'itemuser@example.com',
            'password' => Hash::make('password'),
            'slug' => 'item-user',
        ]);
        $itemUser->assignRole($viewerRole);
        $item1 = ProductItem::where('ProductID', $product1->ProductID)->first();
        $item2 = ProductItem::where('ProductID', $product2->ProductID)->first();
        Access::create([
            'user_id' => $itemUser->id,
            'accessible_type' => ProductItem::getMorphType(),
            'accessible_id' => $item1->ItemID,
            'level' => 1,
        ]);
        Access::create([
            'user_id' => $itemUser->id,
            'accessible_type' => ProductItem::getMorphType(),
            'accessible_id' => $item2->ItemID,
            'level' => 1,
        ]);

        // 5. Mixed access user - access to one brand + one product from another brand
        $mixedUser = User::create([
            'name' => 'Mixed Access User',
            'email' => 'mixed@example.com',
            'password' => Hash::make('password'),
            'slug' => 'mixed-user',
        ]);
        $mixedUser->assignRole($brandAdminRole);
        $brand2 = $brands->skip(1)->first();
        $productFromOtherBrand = $products->where('brand_id', '!=', $brand2->brand_id)->first();
        Access::create([
            'user_id' => $mixedUser->id,
            'accessible_type' => Brand::getMorphType(),
            'accessible_id' => $brand2->brand_id,
            'level' => 1,
        ]);
        Access::create([
            'user_id' => $mixedUser->id,
            'accessible_type' => Product::getMorphType(),
            'accessible_id' => $productFromOtherBrand->ProductID,
            'level' => 1,
        ]);

        // 6. Multi-brand user - access to multiple brands
        $multiBrandUser = User::create([
            'name' => 'Multi Brand User',
            'email' => 'multibrand@example.com',
            'password' => Hash::make('password'),
            'slug' => 'multi-brand-user',
        ]);
        $multiBrandUser->assignRole($brandAdminRole);
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

        $this->command->info('Data seeded successfully!');
        $this->command->info('Users created:');
        $this->command->info('  - admin@example.com (Global Admin) - password: password');
        $this->command->info('  - brandadmin@example.com (Brand Admin) - password: password');
        $this->command->info('  - productuser@example.com (Product-level user) - password: password');
        $this->command->info('  - itemuser@example.com (Product-item-level user) - password: password');
        $this->command->info('  - mixed@example.com (Mixed access) - password: password');
        $this->command->info('  - multibrand@example.com (Multi-brand user) - password: password');
        $this->command->info('');
        $this->command->info('Data created:');
        $this->command->info("  - {$brands->count()} brands");
        $this->command->info("  - {$products->count()} products");
        $this->command->info('  - '.ProductItem::count().' product items');
        $this->command->info("  - {$customers->count()} customers");
        $this->command->info("  - {$orders->count()} orders");
        $this->command->info('  - '.Address::count().' address_order');
        $this->command->info('  - '.Expense::count().' expenses');
    }
}
