<?php

use App\Models\Brand;
use App\Models\User;
use Database\Seeders\DataSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Auth;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(DataSeeder::class);
});

test('profile page loads without class errors', function () {
    $user = User::first();
    Auth::login($user);

    $brand = Brand::first();
    if (! $brand) {
        $this->markTestSkipped('No brands available');
    }

    // Profile page URL with tenant
    $response = $this->get("/admin/{$brand->slug}/profile");

    // Should either succeed or redirect (not 404)
    expect($response->status())->toBeIn([200, 302, 403])
        ->and($response->getContent())->not->toContain('Class "Filament\\Forms\\Components\\Section" not found')
        ->and($response->getContent())->not->toContain('Class "Filament\\Schemas\\Components\\Section" not found');
});

test('all Filament resource pages load without class errors', function () {
    $admin = User::where('email', 'admin@example.com')->first();
    Auth::login($admin);

    $brand = Brand::first();
    if (! $brand) {
        $this->markTestSkipped('No brands available');
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
});
