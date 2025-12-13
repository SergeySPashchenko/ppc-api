<?php

namespace App\Providers;

use App\Http\Middleware\SetPermissionsTeamId;
use App\Models\Access;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Gender;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
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
        app(PermissionRegistrar::class)
            ->setPermissionClass(Permission::class)
            ->setRoleClass(Role::class);

        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'access' => Access::class,
            'product' => Product::class,
            'category' => Category::class,
            'gender' => Gender::class,
            'brand' => Brand::class,
        ]);

        // Встановлюємо пріоритет middleware перед SubstituteBindings
        // щоб уникнути проблем з 404 замість 403 при перевірці дозволів
        /** @var Kernel $kernel */
        $kernel = app()->make(Kernel::class);

        $kernel->addToMiddlewarePriorityBefore(
            SetPermissionsTeamId::class,
            SubstituteBindings::class,
        );
    }
}
