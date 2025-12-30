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
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\GuestCartController;
use App\Http\Controllers\Api\VerificationController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Email verification (signed link)
Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->name('verification.verify')
    ->middleware('signed');

// Guest cart (no auth required)
Route::get('guest-cart', [GuestCartController::class, 'index']);
Route::post('guest-cart/add', [GuestCartController::class, 'add']);
Route::put('guest-cart/update', [GuestCartController::class, 'update']);
Route::post('guest-cart/remove', [GuestCartController::class, 'remove']);
Route::delete('guest-cart', [GuestCartController::class, 'clear']);

// Public product listing routes
Route::get('products', [ProductController::class, 'index']);
Route::get('products/all', [ProductController::class, 'allProducts']);
Route::get('products/newest', [ProductController::class, 'newestProducts']);
Route::get('products/most-sold', [ProductController::class, 'mostSoldProducts']);
Route::get('products/{product}', [ProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('profile', [AuthController::class, 'profile']);
    Route::put('profile', [AuthController::class, 'updateProfile']);
    
    // Product routes (admins only for write operations)

    // Cart routes for authenticated users
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart/add', [CartController::class, 'add']);
    Route::put('cart/item/{item}', [CartController::class, 'update']);
    Route::delete('cart/item/{item}', [CartController::class, 'remove']);
    Route::delete('cart', [CartController::class, 'clear']);
    Route::post('cart/checkout', [CartController::class, 'checkout']);

    // Merge guest cart into authenticated user cart (call after login)
    Route::post('cart/merge', [CartController::class, 'mergeGuest']);

    // resend verification email
    Route::post('email/verification-notification', [VerificationController::class, 'resend'])->name('verification.send');

    // Payment and card management
    Route::get('cards', [\App\Http\Controllers\Api\PaymentController::class, 'listCards']);
    Route::post('cards', [\App\Http\Controllers\Api\PaymentController::class, 'addCard']);
    Route::delete('cards/{card}', [\App\Http\Controllers\Api\PaymentController::class, 'deleteCard']);
    Route::post('cards/{card}/default', [\App\Http\Controllers\Api\PaymentController::class, 'setDefault']);

    // Orders
    Route::get('orders', [\App\Http\Controllers\Api\PaymentController::class, 'myOrders']);
    Route::get('orders/{order}', [\App\Http\Controllers\Api\PaymentController::class, 'showOrder']);

    Route::middleware('is_admin')->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);

        // Product images
        Route::post('products/{product}/images', [ProductController::class, 'uploadImages']);
        Route::delete('products/{product}/images/{image}', [ProductController::class, 'destroyImage']);

        // Admin management
        Route::get('admin/users', [AdminController::class, 'index']);
        Route::get('admin/admins', [AdminController::class, 'admins']);
        Route::post('admin/users/{user}/promote', [AdminController::class, 'promote']);
        Route::post('admin/users/{user}/demote', [AdminController::class, 'demote']);
        Route::put('admin/users/{user}', [AdminController::class, 'updateAdmin']);
        Route::delete('admin/users/{user}', [AdminController::class, 'destroy']);
        // create admin & role assignment
        Route::post('admin/users/create-admin', [AdminController::class, 'createAdmin']);
        Route::post('admin/users/{user}/assign-role', [AdminDashboardController::class, 'assignRole']);
        Route::post('admin/users/{user}/revoke-role', [AdminDashboardController::class, 'revokeRole']);

        // Dashboard metrics
        Route::get('admin/metrics', [AdminDashboardController::class, 'metrics']);
        Route::get('admin/visitor-analytics', [AdminDashboardController::class, 'visitorAnalytics']);
        // Admin-only: create admin and manage promotions
        Route::post('admin/users/create-admin', [AdminController::class, 'createAdmin']);
        Route::post('admin/promotions', [AdminController::class, 'createPromotion']);
        Route::get('admin/promotions', [AdminController::class, 'listPromotions']);
        Route::delete('admin/promotions/{promotion}', [AdminController::class, 'deletePromotion']);
    });
});
