<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CartController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('profile', [AuthController::class, 'profile']);
    Route::put('profile', [AuthController::class, 'updateProfile']);
    
    // Product routes (admins only for write operations)
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);

    // Cart routes for authenticated users
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart/add', [CartController::class, 'add']);
    Route::put('cart/item/{item}', [CartController::class, 'update']);
    Route::delete('cart/item/{item}', [CartController::class, 'remove']);
    Route::delete('cart', [CartController::class, 'clear']);
    Route::post('cart/checkout', [CartController::class, 'checkout']);

    Route::middleware('is_admin')->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);

        // Admin management
        Route::get('admin/users', [AdminController::class, 'index']);
        Route::get('admin/admins', [AdminController::class, 'admins']);
        Route::post('admin/users/{user}/promote', [AdminController::class, 'promote']);
        Route::post('admin/users/{user}/demote', [AdminController::class, 'demote']);
        Route::delete('admin/users/{user}', [AdminController::class, 'destroy']);
    });
});
