<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ExpensetypeController;
use App\Http\Controllers\Api\GenderController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductItemController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Authentication routes (public)
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    Route::apiResource('users', UserController::class)->only(['index', 'show']);
    Route::apiResource('brands', BrandController::class)->only(['index', 'show']);
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::apiResource('product-items', ProductItemController::class)->only(['index', 'show']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'show']);
    Route::apiResource('order-items', OrderItemController::class)->only(['index', 'show']);
    Route::apiResource('expenses', ExpenseController::class)->only(['index', 'show']);
    Route::apiResource('customers', CustomerController::class)->only(['index', 'show']);
    Route::apiResource('addresses', AddressController::class)->only(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::apiResource('genders', GenderController::class)->only(['index', 'show']);
    Route::apiResource('expense-types', ExpensetypeController::class)->only(['index', 'show']);

    // Import sync routes
    Route::prefix('import')->group(function () {
        Route::get('/test-connection', [\App\Http\Controllers\Api\ImportController::class, 'testConnection']);
        Route::post('/sync', [\App\Http\Controllers\Api\ImportController::class, 'sync']);
    });
});
