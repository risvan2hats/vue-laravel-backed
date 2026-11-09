<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'me'])->name('user.show');
        Route::patch('/user', [AuthController::class, 'updateProfile'])->name('user.update');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
        Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.items.store');
        Route::patch('/cart/items/{item}', [CartController::class, 'updateItem'])->name('cart.items.update');
        Route::delete('/cart/items/{item}', [CartController::class, 'removeItem'])->name('cart.items.destroy');
        Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
        Route::apiResource('products', ProductController::class)->except(['index', 'show']);
    });
});
