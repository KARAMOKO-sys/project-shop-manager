<?php

use App\Http\Controllers\Api\V1\AssistantController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\Erp\ActivityController;
use App\Http\Controllers\Api\V1\Erp\CrmLeadController;
use App\Http\Controllers\Api\V1\Erp\ErpDashboardController;
use App\Http\Controllers\Api\V1\Erp\ExpenseController;
use App\Http\Controllers\Api\V1\Erp\InventoryController;
use App\Http\Controllers\Api\V1\Erp\InvoiceController;
use App\Http\Controllers\Api\V1\Erp\ModuleController;
use App\Http\Controllers\Api\V1\Erp\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Erp\SupplierController;
use App\Http\Controllers\Api\V1\Erp\WarehouseController;
use App\Http\Controllers\Api\V1\FlutterwavePaymentController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\ModerationController;
use App\Http\Controllers\Api\V1\ModeratorController;
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

            /*
             * Modular ERP: applications installed per shop, as in Odoo "Apps".
             */
            Route::prefix('erp')->name('shops.erp.')->group(function () {
                Route::get('dashboard', [ErpDashboardController::class, 'show'])->name('dashboard');
                Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');

                Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
                Route::post('modules/{module}', [ModuleController::class, 'store'])->name('modules.store');
                Route::delete('modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');

                Route::middleware('module:inventory')->group(function () {
                    Route::apiResource('warehouses', WarehouseController::class)->except('show')->names('warehouses');
                    Route::get('inventory/stock', [InventoryController::class, 'stock'])->name('inventory.stock');
                    Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
                    Route::get('inventory/reorder-suggestions', [InventoryController::class, 'suggestions'])->name('inventory.suggestions');
                    Route::post('inventory/adjustments', [InventoryController::class, 'adjust'])->name('inventory.adjust');
                    Route::post('inventory/transfers', [InventoryController::class, 'transfer'])->name('inventory.transfer');
                    Route::patch('inventory/products/{product}', [InventoryController::class, 'updateProduct'])->name('inventory.products.update');
                });

                Route::middleware('module:purchase')->group(function () {
                    Route::apiResource('suppliers', SupplierController::class)->except('show')->names('suppliers');
                    Route::post('purchase-orders/from-suggestions', [PurchaseOrderController::class, 'fromSuggestions'])->name('purchase-orders.from-suggestions');
                    Route::apiResource('purchase-orders', PurchaseOrderController::class)->except('destroy')->names('purchase-orders');
                    Route::post('purchase-orders/{purchase_order}/confirm', [PurchaseOrderController::class, 'confirm'])->name('purchase-orders.confirm');
                    Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
                    Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
                });

                Route::middleware('module:invoicing')->group(function () {
                    Route::get('invoiceable-orders', [InvoiceController::class, 'invoiceableOrders'])->name('invoices.invoiceable-orders');
                    Route::post('invoices/from-order/{order}', [InvoiceController::class, 'fromOrder'])->name('invoices.from-order');
                    Route::apiResource('invoices', InvoiceController::class)->except('destroy')->names('invoices');
                    Route::post('invoices/{invoice}/post', [InvoiceController::class, 'post'])->name('invoices.post');
                    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'registerPayment'])->name('invoices.payments.store');
                    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
                });

                Route::middleware('module:expenses')->group(function () {
                    Route::apiResource('expenses', ExpenseController::class)->except('show')->names('expenses');
                });

                Route::middleware('module:crm')->group(function () {
                    Route::get('leads/pipeline', [CrmLeadController::class, 'pipeline'])->name('leads.pipeline');
                    Route::apiResource('leads', CrmLeadController::class)->except('show')->names('leads');
                    Route::post('leads/{lead}/stage', [CrmLeadController::class, 'moveStage'])->name('leads.stage');
                });
            });
        });

        Route::middleware('can:manage-platform')->prefix('admin/moderators')->name('admin.moderators.')->group(function () {
            Route::get('/', [ModeratorController::class, 'index'])->name('index');
            Route::post('/', [ModeratorController::class, 'store'])->name('store');
            Route::patch('{moderator}', [ModeratorController::class, 'update'])->name('update');
            Route::post('{moderator}/reset-password', [ModeratorController::class, 'resetPassword'])->name('reset-password');
            Route::delete('{moderator}', [ModeratorController::class, 'destroy'])->name('destroy');
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
