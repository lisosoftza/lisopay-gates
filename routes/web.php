<?php

use Illuminate\Support\Facades\Route;
use Lisosoft\PaymentGateway\Http\Controllers\PaymentController;
use Lisosoft\PaymentGateway\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| Web Routes for Payment Gateway
|--------------------------------------------------------------------------
|
| These routes are loaded by the PaymentGatewayServiceProvider and
| are responsible for handling web-based payment interactions.
|
*/

Route::prefix('payment')->name('payment.')->group(function () {
    // Payment initialization
    Route::post('/initialize', [PaymentController::class, 'initialize'])
        ->name('initialize')
        ->middleware(['web', 'throttle:60,1']);

    // Payment verification
    Route::get('/verify/{transactionReference}', [PaymentController::class, 'verify'])
        ->name('verify')
        ->where('transactionReference', '[A-Za-z0-9\-]+')
        ->middleware(['web', 'throttle:60,1']);

    // Payment status check
    Route::get('/status/{transactionReference}', [PaymentController::class, 'status'])
        ->name('status')
        ->where('transactionReference', '[A-Za-z0-9\-]+')
        ->middleware(['web', 'throttle:120,1']);

    // Payment refund
    Route::post('/refund/{transactionReference}', [PaymentController::class, 'refund'])
        ->name('refund')
        ->where('transactionReference', '[A-Za-z0-9\-]+')
        ->middleware(['web', 'throttle:30,1']);

    // Payment retry
    Route::post('/retry/{transactionReference}', [PaymentController::class, 'retry'])
        ->name('retry')
        ->where('transactionReference', '[A-Za-z0-9\-]+')
        ->middleware(['web', 'throttle:30,1']);

    // Available gateways
    Route::get('/gateways', [PaymentController::class, 'gateways'])
        ->name('gateways')
        ->middleware(['web', 'throttle:120,1']);

    // Transaction history
    Route::get('/history', [PaymentController::class, 'history'])
        ->name('history')
        ->middleware(['web', 'throttle:60,1']);

    // Payment success page
    Route::get('/success', function () {
        return view('payment-gateway::success', [
            'transaction' => request()->query('transaction'),
            'message' => 'Payment completed successfully!',
        ]);
    })->name('success');

    // Payment cancel page
    Route::get('/cancel', function () {
        return view('payment-gateway::cancel', [
            'transaction' => request()->query('transaction'),
            'message' => 'Payment was cancelled.',
        ]);
    })->name('cancel');

    // Payment error page
    Route::get('/error', function () {
        return view('payment-gateway::error', [
            'transaction' => request()->query('transaction'),
            'error' => request()->query('error'),
            'message' => 'An error occurred during payment processing.',
        ]);
    })->name('error');

    // Payment form (for direct gateway integration)
    Route::get('/form/{gateway}', function ($gateway) {
        if (!in_array($gateway, ['payfast', 'paystack', 'paypal', 'stripe', 'ozow', 'zapper', 'crypto', 'eft', 'vodapay', 'snapscan'])) {
            abort(404, 'Payment gateway not found');
        }

        return view("payment-gateway::payment-form", [
            'gateway' => $gateway,
            'transaction' => request()->query('transaction'),
            'amount' => request()->query('amount'),
            'currency' => request()->query('currency'),
            'description' => request()->query('description'),
        ]);
    })->name('form')
      ->where('gateway', '[a-z]+')
      ->middleware(['web', 'throttle:60,1']);

    // EFT payment instructions
    Route::get('/eft/instructions', function () {
        return view('payment-gateway::eft', [
            'transaction' => request()->query('transaction'),
            'bank_details' => [
                'bank_name' => config('payment-gateway.gateways.eft.bank_name', 'Standard Bank'),
                'account_name' => config('payment-gateway.gateways.eft.account_name'),
                'account_number' => config('payment-gateway.gateways.eft.account_number'),
                'branch_code' => config('payment-gateway.gateways.eft.branch_code'),
                'reference' => request()->query('reference'),
            ],
        ]);
    })->name('eft.instructions');

    // Crypto payment instructions
    Route::get('/crypto/instructions', function () {
        return view('payment-gateway::crypto', [
            'transaction' => request()->query('transaction'),
            'crypto_details' => [
                'provider' => config('payment-gateway.gateways.crypto.provider', 'coinbase'),
                'currencies' => config('payment-gateway.gateways.crypto.currencies', ['BTC', 'ETH', 'USDT', 'USDC']),
                'wallet_address' => request()->query('wallet_address'),
                'amount' => request()->query('amount'),
                'currency' => request()->query('currency'),
            ],
        ]);
    })->name('crypto.instructions');
});

// Webhook routes (no CSRF protection needed for external callbacks)
Route::prefix('payment/webhook')->name('payment.webhook.')->group(function () {
    // PayFast ITN (Instant Transaction Notification)
    Route::post('/payfast', [WebhookController::class, 'handlePayFast'])
        ->name('payfast')
        ->withoutMiddleware(['web', 'csrf']);

    // PayStack webhook
    Route::post('/paystack', [WebhookController::class, 'handlePayStack'])
        ->name('paystack')
        ->withoutMiddleware(['web', 'csrf']);

    // PayPal webhook
    Route::post('/paypal', [WebhookController::class, 'handlePayPal'])
        ->name('paypal')
        ->withoutMiddleware(['web', 'csrf']);

    // Stripe webhook
    Route::post('/stripe', [WebhookController::class, 'handleStripe'])
        ->name('stripe')
        ->withoutMiddleware(['web', 'csrf']);

    // Ozow callback
    Route::post('/ozow', [WebhookController::class, 'handleOzow'])
        ->name('ozow')
        ->withoutMiddleware(['web', 'csrf']);

    // Zapper callback
    Route::post('/zapper', [WebhookController::class, 'handleZapper'])
        ->name('zapper')
        ->withoutMiddleware(['web', 'csrf']);

    // Crypto webhook
    Route::post('/crypto', [WebhookController::class, 'handleCrypto'])
        ->name('crypto')
        ->withoutMiddleware(['web', 'csrf']);

    // VodaPay callback
    Route::post('/vodapay', [WebhookController::class, 'handleVodaPay'])
        ->name('vodapay')
        ->withoutMiddleware(['web', 'csrf']);

    // SnapScan callback
    Route::post('/snapscan', [WebhookController::class, 'handleSnapScan'])
        ->name('snapscan')
        ->withoutMiddleware(['web', 'csrf']);

    // Generic callback handler (for testing and fallback)
    Route::post('/{gateway}', [WebhookController::class, 'handleGeneric'])
        ->name('generic')
        ->where('gateway', '[a-z]+')
        ->withoutMiddleware(['web', 'csrf']);
});

// Admin routes (protected by auth middleware)
Route::prefix('admin/payments')->name('admin.payments.')->middleware(['web', 'auth'])->group(function () {
    // Payment dashboard
    Route::get('/dashboard', [\Lisosoft\PaymentGateway\Http\Controllers\Admin\PaymentGatewayController::class, 'dashboard'])
        ->name('dashboard')
        ->middleware('can:view-payments');

    // Transaction management
    Route::get('/transactions', [\Lisosoft\PaymentGateway\Http\Controllers\Admin\PaymentGatewayController::class, 'transactions'])
        ->name('transactions')
        ->middleware('can:view-payments');

    // Transaction details
    Route::get('/transactions/{transaction}', [\Lisosoft\PaymentGateway\Http\Controllers\Admin\PaymentGatewayController::class, 'showTransaction'])
        ->name('transactions.show')
        ->where('transaction', '[0-9]+')
        ->middleware('can:view-payments');

    // Gateway configuration
    Route::get('/gateways', [\Lisosoft\PaymentGateway\Http\Controllers\Admin\PaymentGatewayController::class, 'gateways'])
        ->name('gateways')
        ->middleware('can:configure-payments');

    // Update gateway configuration
    Route::put('/gateways/{gateway}', [\Lisosoft\PaymentGateway\Http\Controllers\Admin\PaymentGatewayController::class, 'updateGateway'])
        ->name('gateways.update')
        ->where('gateway', '[a-z]+')
        ->middleware('can:configure-payments');

    // Analytics and reports
    Route::get('/analytics', [\Lisosoft\PaymentGateway\Http\Controllers\Admin\PaymentGatewayController::class, 'analytics'])
        ->name('analytics')
        ->middleware('can:view-analytics');

    // Export transactions
    Route::get('/export', [\Lisosoft\PaymentGateway\Http\Controllers\Admin\PaymentGatewayController::class, 'export'])
        ->name('export')
        ->middleware('can:export-payments');

    // Subscription management
    Route::get('/subscriptions', [\Lisosoft\PaymentGateway\Http\Controllers\Admin\PaymentGatewayController::class, 'subscriptions'])
        ->name('subscriptions')
        ->middleware('can:manage-subscriptions');

    // Manual payment processing
    Route::post('/manual-payment', [\Lisosoft\PaymentGateway\Http\Controllers\Admin\PaymentGatewayController::class, 'manualPayment'])
        ->name('manual.payment')
        ->middleware('can:process-payments');
});

// Public payment pages (no authentication required)
Route::prefix('pay')->name('pay.')->group(function () {
    // Quick payment page
    Route::get('/{reference}', function ($reference) {
        $transaction = \Lisosoft\PaymentGateway\Models\Transaction::where('reference', $reference)->first();

        if (!$transaction) {
            abort(404, 'Payment not found');
        }

        return view('payment-gateway::quick-payment', [
            'transaction' => $transaction,
            'gateways' => \Lisosoft\PaymentGateway\Facades\Payment::getAvailableGateways(),
        ]);
    })->name('quick')
      ->where('reference', '[A-Za-z0-9\-]+')
      ->middleware(['web', 'throttle:60,1']);

    // Payment confirmation
    Route::get('/confirm/{reference}', function ($reference) {
        $transaction = \Lisosoft\PaymentGateway\Models\Transaction::where('reference', $reference)->first();

        if (!$transaction) {
            abort(404, 'Payment not found');
        }

        return view('payment-gateway::confirmation', [
            'transaction' => $transaction,
            'status' => $transaction->status,
            'message' => $transaction->is_successful
                ? 'Payment completed successfully!'
                : ($transaction->is_pending
                    ? 'Payment is being processed...'
                    : 'Payment failed. Please try again.'),
        ]);
    })->name('confirm')
      ->where('reference', '[A-Za-z0-9\-]+')
      ->middleware(['web', 'throttle:60,1']);
});
