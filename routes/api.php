<?php

use Illuminate\Support\Facades\Route;
use Lisosoft\PaymentGateway\Http\Controllers\Api\PaymentApiController;

/*
|--------------------------------------------------------------------------
| API Routes for Payment Gateway
|--------------------------------------------------------------------------
|
| These routes are loaded by the PaymentGatewayServiceProvider and
| are responsible for handling API-based payment interactions.
|
*/

Route::prefix('api/v1/payments')->name('api.payments.')->middleware(['api'])->group(function () {
    // Payment initialization
    Route::post('/initialize', [PaymentApiController::class, 'initialize'])
        ->name('initialize')
        ->middleware(['throttle:60,1']);

    // Payment verification
    Route::get('/verify/{transactionReference}', [PaymentApiController::class, 'verify'])
        ->name('verify')
        ->where('transactionReference', '[A-Za-z0-9\-]+')
        ->middleware(['throttle:60,1']);

    // Payment status check
    Route::get('/status/{transactionReference}', [PaymentApiController::class, 'status'])
        ->name('status')
        ->where('transactionReference', '[A-Za-z0-9\-]+')
        ->middleware(['throttle:120,1']);

    // Payment refund
    Route::post('/refund/{transactionReference}', [PaymentApiController::class, 'refund'])
        ->name('refund')
        ->where('transactionReference', '[A-Za-z0-9\-]+')
        ->middleware(['throttle:30,1', 'auth:api']);

    // Payment retry
    Route::post('/retry/{transactionReference}', [PaymentApiController::class, 'retry'])
        ->name('retry')
        ->where('transactionReference', '[A-Za-z0-9\-]+')
        ->middleware(['throttle:30,1', 'auth:api']);

    // Available gateways
    Route::get('/gateways', [PaymentApiController::class, 'gateways'])
        ->name('gateways')
        ->middleware(['throttle:120,1']);

    // Transaction history (authenticated)
    Route::get('/history', [PaymentApiController::class, 'history'])
        ->name('history')
        ->middleware(['throttle:60,1', 'auth:api']);

    // User-specific transactions
    Route::get('/my-transactions', [PaymentApiController::class, 'myTransactions'])
        ->name('my-transactions')
        ->middleware(['throttle:60,1', 'auth:api']);

    // Webhook endpoints (no authentication, CSRF, or throttling)
    Route::prefix('webhook')->name('webhook.')->withoutMiddleware(['auth:api', 'throttle:api'])->group(function () {
        // PayFast ITN
        Route::post('/payfast', [PaymentApiController::class, 'handlePayFastWebhook'])
            ->name('payfast');

        // PayStack webhook
        Route::post('/paystack', [PaymentApiController::class, 'handlePayStackWebhook'])
            ->name('paystack');

        // PayPal webhook
        Route::post('/paypal', [PaymentApiController::class, 'handlePayPalWebhook'])
            ->name('paypal');

        // Stripe webhook
        Route::post('/stripe', [PaymentApiController::class, 'handleStripeWebhook'])
            ->name('stripe');

        // Ozow callback
        Route::post('/ozow', [PaymentApiController::class, 'handleOzowWebhook'])
            ->name('ozow');

        // Zapper callback
        Route::post('/zapper', [PaymentApiController::class, 'handleZapperWebhook'])
            ->name('zapper');

        // Crypto webhook
        Route::post('/crypto', [PaymentApiController::class, 'handleCryptoWebhook'])
            ->name('crypto');

        // VodaPay callback
        Route::post('/vodapay', [PaymentApiController::class, 'handleVodaPayWebhook'])
            ->name('vodapay');

        // SnapScan callback
        Route::post('/snapscan', [PaymentApiController::class, 'handleSnapScanWebhook'])
            ->name('snapscan');

        // Generic webhook handler
        Route::post('/{gateway}', [PaymentApiController::class, 'handleGenericWebhook'])
            ->name('generic')
            ->where('gateway', '[a-z]+');
    });

    // Subscription management (authenticated)
    Route::prefix('subscriptions')->name('subscriptions.')->middleware(['auth:api'])->group(function () {
        // Create subscription
        Route::post('/', [PaymentApiController::class, 'createSubscription'])
            ->name('create')
            ->middleware(['throttle:30,1']);

        // List subscriptions
        Route::get('/', [PaymentApiController::class, 'listSubscriptions'])
            ->name('list')
            ->middleware(['throttle:60,1']);

        // Get subscription details
        Route::get('/{subscriptionId}', [PaymentApiController::class, 'getSubscription'])
            ->name('show')
            ->where('subscriptionId', '[A-Za-z0-9\-]+')
            ->middleware(['throttle:60,1']);

        // Update subscription
        Route::put('/{subscriptionId}', [PaymentApiController::class, 'updateSubscription'])
            ->name('update')
            ->where('subscriptionId', '[A-Za-z0-9\-]+')
            ->middleware(['throttle:30,1']);

        // Cancel subscription
        Route::delete('/{subscriptionId}', [PaymentApiController::class, 'cancelSubscription'])
            ->name('cancel')
            ->where('subscriptionId', '[A-Za-z0-9\-]+')
            ->middleware(['throttle:30,1']);

        // Subscription transactions
        Route::get('/{subscriptionId}/transactions', [PaymentApiController::class, 'subscriptionTransactions'])
            ->name('transactions')
            ->where('subscriptionId', '[A-Za-z0-9\-]+')
            ->middleware(['throttle:60,1']);
    });

    // Admin endpoints (require admin permissions)
    Route::prefix('admin')->name('admin.')->middleware(['auth:api', 'can:admin-payments'])->group(function () {
        // Payment statistics
        Route::get('/statistics', [PaymentApiController::class, 'adminStatistics'])
            ->name('statistics')
            ->middleware(['throttle:60,1']);

        // All transactions
        Route::get('/transactions', [PaymentApiController::class, 'adminTransactions'])
            ->name('transactions')
            ->middleware(['throttle:60,1']);

        // Transaction details
        Route::get('/transactions/{transaction}', [PaymentApiController::class, 'adminTransactionDetails'])
            ->name('transactions.show')
            ->where('transaction', '[0-9]+')
            ->middleware(['throttle:60,1']);

        // Update transaction
        Route::put('/transactions/{transaction}', [PaymentApiController::class, 'adminUpdateTransaction'])
            ->name('transactions.update')
            ->where('transaction', '[0-9]+')
            ->middleware(['throttle:30,1']);

        // Gateway configuration
        Route::get('/gateways', [PaymentApiController::class, 'adminGateways'])
            ->name('gateways')
            ->middleware(['throttle:60,1']);

        // Update gateway configuration
        Route::put('/gateways/{gateway}', [PaymentApiController::class, 'adminUpdateGateway'])
            ->name('gateways.update')
            ->where('gateway', '[a-z]+')
            ->middleware(['throttle:30,1']);

        // Export transactions
        Route::post('/export', [PaymentApiController::class, 'adminExportTransactions'])
            ->name('export')
            ->middleware(['throttle:10,1']);

        // Manual payment processing
        Route::post('/manual-payment', [PaymentApiController::class, 'adminManualPayment'])
            ->name('manual-payment')
            ->middleware(['throttle:30,1']);

        // System health check
        Route::get('/health', [PaymentApiController::class, 'adminHealthCheck'])
            ->name('health')
            ->middleware(['throttle:60,1']);
    });

    // Public endpoints (no authentication required)
    Route::prefix('public')->name('public.')->withoutMiddleware(['auth:api'])->group(function () {
        // Check payment status by reference
        Route::get('/status/{transactionReference}', [PaymentApiController::class, 'publicStatus'])
            ->name('status')
            ->where('transactionReference', '[A-Za-z0-9\-]+')
            ->middleware(['throttle:120,1']);

        // Available currencies
        Route::get('/currencies', [PaymentApiController::class, 'publicCurrencies'])
            ->name('currencies')
            ->middleware(['throttle:120,1']);

        // Payment methods by gateway
        Route::get('/{gateway}/methods', [PaymentApiController::class, 'publicPaymentMethods'])
            ->name('payment-methods')
            ->where('gateway', '[a-z]+')
            ->middleware(['throttle:120,1']);

        // Calculate fees
        Route::post('/calculate-fees', [PaymentApiController::class, 'publicCalculateFees'])
            ->name('calculate-fees')
            ->middleware(['throttle:60,1']);
    });
});

// Quick payment endpoints (simplified API)
Route::prefix('api/v1/pay')->name('api.pay.')->middleware(['api'])->group(function () {
    // Quick payment initialization
    Route::post('/', [PaymentApiController::class, 'quickPayment'])
        ->name('initialize')
        ->middleware(['throttle:60,1']);

    // Quick payment status
    Route::get('/{reference}/status', [PaymentApiController::class, 'quickPaymentStatus'])
        ->name('status')
        ->where('reference', '[A-Za-z0-9\-]+')
        ->middleware(['throttle:120,1']);

    // Quick payment confirmation
    Route::post('/{reference}/confirm', [PaymentApiController::class, 'quickPaymentConfirm'])
        ->name('confirm')
        ->where('reference', '[A-Za-z0-9\-]+')
        ->middleware(['throttle:60,1']);
});

// Webhook test endpoints (development only)
if (app()->environment('local', 'testing')) {
    Route::prefix('api/v1/test')->name('api.test.')->middleware(['api'])->group(function () {
        // Test webhook simulation
        Route::post('/webhook/{gateway}', [PaymentApiController::class, 'testWebhook'])
            ->name('webhook')
            ->where('gateway', '[a-z]+')
            ->middleware(['throttle:60,1']);

        // Test payment simulation
        Route::post('/payment/{gateway}', [PaymentApiController::class, 'testPayment'])
            ->name('payment')
            ->where('gateway', '[a-z]+')
            ->middleware(['throttle:30,1']);

        // Test refund simulation
        Route::post('/refund/{gateway}', [PaymentApiController::class, 'testRefund'])
            ->name('refund')
            ->where('gateway', '[a-z]+')
            ->middleware(['throttle:30,1']);
    });
}
