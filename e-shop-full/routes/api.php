<?php

use App\Http\Controllers\Api\V1\AssistantController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\FlutterwavePaymentController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\ModerationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PlatformSettingsController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\StorefrontController;
use App\Http\Controllers\Api\V1\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);

Route::prefix('v1')->name('v1.')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register'])->middleware('feature:registration')->name('auth.register');
        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    });

    Route::get('platform', [PlatformSettingsController::class, 'public'])->name('platform');
    Route::get('home', [HomeController::class, 'index'])->name('home');
    Route::get('templates', [HomeController::class, 'templates'])->name('templates');
    Route::post('chat', [ChatController::class, 'platform'])->middleware(['feature:platform_chat', 'throttle:ai'])->name('chat');
    Route::post('payments/flutterwave/webhook', [FlutterwavePaymentController::class, 'webhook'])
        ->middleware('throttle:60,1')
        ->name('payments.flutterwave.webhook');
    Route::get('payments/flutterwave/callback', [FlutterwavePaymentController::class, 'callback'])
        ->name('payments.flutterwave.callback');

    Route::prefix('storefront/{shop:slug}')->name('storefront.')->group(function () {
        Route::get('/', [StorefrontController::class, 'show'])->name('show');
        Route::get('products', [StorefrontController::class, 'products'])->name('products');
        Route::get('products/{productSlug}', [StorefrontController::class, 'product'])->name('product');
        Route::get('products/{productSlug}/recommendations', [StorefrontController::class, 'recommendations'])->name('recommendations');
        Route::get('orders/{orderNumber}', [StorefrontController::class, 'order'])->middleware('throttle:10,1')->name('order');
        Route::post('coupons/check', [CouponController::class, 'check'])->middleware('throttle:20,1')->name('coupons.check');
        Route::post('checkout', [StorefrontController::class, 'checkout'])->middleware('throttle:20,1')->name('checkout');
        Route::post('chat', [ChatController::class, 'shop'])->middleware(['feature:shop_chat', 'throttle:ai'])->name('chat');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::middleware(['feature:ai_assistant', 'throttle:ai'])->prefix('ai')->name('ai.')->group(function () {
            Route::post('shop-setup', [AssistantController::class, 'shopSetup'])->name('shop-setup');
            Route::post('product-description', [AssistantController::class, 'productDescription'])->name('product-description');
        });

        Route::get('shops', [ShopController::class, 'index'])->name('shops.index');
        Route::post('shops', [ShopController::class, 'store'])->middleware('feature:shop_creation')->name('shops.store');

        Route::prefix('shops/{shop}')->middleware('can:manage,shop')->scopeBindings()->group(function () {
            Route::get('/', [ShopController::class, 'show'])->name('shops.show');
            Route::put('/', [ShopController::class, 'update'])->name('shops.update');
            Route::delete('/', [ShopController::class, 'destroy'])->name('shops.destroy');
            Route::get('analytics', [ShopController::class, 'analytics'])->name('shops.analytics');

            Route::get('categories', [CategoryController::class, 'index'])->name('shops.categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('shops.categories.store');

            Route::apiResource('products', ProductController::class)->names('shops.products');
            Route::post('products/{product}/moderate', [ProductController::class, 'moderate'])->name('shops.products.moderate');

            Route::apiResource('coupons', CouponController::class)->except('show')->names('shops.coupons');

            Route::post('uploads', [UploadController::class, 'store'])->middleware('throttle:30,1')->name('shops.uploads.store');

            Route::get('orders', [OrderController::class, 'index'])->name('shops.orders.index');
            Route::patch('orders/{order}', [OrderController::class, 'update'])->name('shops.orders.update');
        });

        Route::middleware('can:manage-platform')->prefix('admin/settings')->name('admin.settings.')->group(function () {
            Route::get('/', [PlatformSettingsController::class, 'index'])->name('index');
            Route::put('/', [PlatformSettingsController::class, 'update'])->name('update');
        });

        Route::middleware('can:moderate-products')->prefix('admin/moderation')->name('admin.moderation.')->group(function () {
            Route::get('/', [ModerationController::class, 'index'])->name('index');
            Route::post('{product}', [ModerationController::class, 'decide'])->name('decide');
        });
    });
});
