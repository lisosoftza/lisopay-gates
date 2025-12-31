<?php

namespace Lisosoft\PaymentGateway\Listeners;

use Lisosoft\PaymentGateway\Events\PaymentFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Lisosoft\PaymentGateway\Notifications\PaymentFailedNotification;

class HandlePaymentFailed implements ShouldQueue
{
    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $retryAfter = 60;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Handle the event.
     *
     * @param  PaymentFailed  $event
     * @return void
     */
    public function handle(PaymentFailed $event)
    {
        $transaction = $event->transaction;

        try {
            // Log the failed payment
            $this->logPaymentFailure($transaction);

            // Send notification to customer about failed payment
            $this->sendCustomerNotification($transaction);

            // Send notification to admin/merchant
            $this->sendAdminNotification($transaction);

            // Update related business logic
            $this->updateBusinessLogic($transaction);

            // Handle retry logic if applicable
            $this->handleRetryLogic($transaction);

            // Trigger any post-failure hooks
            $this->triggerPostFailureHooks($transaction);

            Log::info('Payment failure processed', [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'error_message' => $transaction->error_message,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle payment failure', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to allow queue retry
            throw $e;
        }
    }

    /**
     * Log payment failure.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function logPaymentFailure($transaction): void
    {
        Log::channel('payments')->error('Payment failed', [
            'reference' => $transaction->reference,
            'gateway' => $transaction->gateway,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'customer_email' => $transaction->customer_email,
            'customer_name' => $transaction->customer_name,
            'failed_at' => $transaction->failed_at,
            'error_message' => $transaction->error_message,
            'error_code' => $transaction->error_code,
            'error_details' => $transaction->error_details,
            'metadata' => $transaction->metadata,
        ]);
    }

    /**
     * Send notification to customer about failed payment.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function sendCustomerNotification($transaction): void
    {
        // Check if email notifications are enabled
        if (!config('payment-gateway.notifications.email.enabled', true)) {
            return;
        }

        // Check if we should notify customer about failures
        if (!config('payment-gateway.notifications.notify_on_failure', true)) {
            return;
        }

        try {
            // Prepare email data
            $emailData = [
                'transaction' => $transaction,
                'subject' => 'Payment Failed - ' . config('app.name'),
                'to' => $transaction->customer_email,
                'from' => [
                    'email' => config('payment-gateway.notifications.email.sender_email', 'noreply@example.com'),
                    'name' => config('payment-gateway.notifications.email.sender_name', 'Payment System'),
                ],
                'retry_url' => $this->generateRetryUrl($transaction),
                'support_email' => config('payment-gateway.notifications.support_email', 'support@example.com'),
            ];

            // Send email using Laravel's mail system
            Mail::send('payment-gateway::emails.payment-failed', $emailData, function ($message) use ($emailData) {
                $message->to($emailData['to'])
                        ->from($emailData['from']['email'], $emailData['from']['name'])
                        ->subject($emailData['subject']);
            });

            Log::info('Payment failure email sent to customer', [
                'transaction_id' => $transaction->id,
                'customer_email' => $transaction->customer_email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment failure email', [
                'transaction_id' => $transaction->id,
                'customer_email' => $transaction->customer_email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification to admin/merchant.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function sendAdminNotification($transaction): void
    {
        // Check if admin notifications are enabled
        $adminEmail = config('payment-gateway.notifications.admin_email');
        if (!$adminEmail) {
            return;
        }

        // Check if we should notify admin about failures
        if (!config('payment-gateway.notifications.notify_admin_on_failure', true)) {
            return;
        }

        try {
            // Prepare notification data
            $notificationData = [
                'transaction' => $transaction,
                'type' => 'payment_failed',
                'timestamp' => now()->toISOString(),
                'retry_attempts' => $transaction->attempts,
                'max_attempts' => $transaction->max_attempts,
            ];

            // Send notification (could be email, Slack, etc.)
            Notification::route('mail', $adminEmail)
                ->notify(new PaymentFailedNotification($notificationData));

            Log::info('Payment failure notification sent to admin', [
                'transaction_id' => $transaction->id,
                'admin_email' => $adminEmail,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send admin notification', [
                'transaction_id' => $transaction->id,
                'admin_email' => $adminEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update business logic after payment failure.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function updateBusinessLogic($transaction): void
    {
        // This method should be extended by the application
        // to handle business-specific logic after payment failure

        // Example: Update order status
        if (isset($transaction->metadata['order_id'])) {
            $this->updateOrderStatus($transaction->metadata['order_id'], 'payment_failed');
        }

        // Example: Restore inventory if it was reserved
        if (isset($transaction->metadata['product_id']) && isset($transaction->metadata['inventory_reserved'])) {
            $this->restoreInventory($transaction->metadata['product_id'], $transaction->metadata['inventory_reserved']);
        }

        // Example: Notify customer support system
        if (config('payment-gateway.notifications.integrate_with_support', false)) {
            $this->createSupportTicket($transaction);
        }
    }

    /**
     * Handle retry logic for failed payments.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function handleRetryLogic($transaction): void
    {
        // Check if retry is enabled
        if (!config('payment-gateway.recurring.enabled', true)) {
            return;
        }

        // Check if this is a subscription payment
        if ($transaction->is_subscription) {
            $this->handleSubscriptionRetry($transaction);
            return;
        }

        // Check if we should retry regular payments
        if (!config('payment-gateway.retry_failed_payments', false)) {
            return;
        }

        // Check if max retry attempts reached
        if ($transaction->attempts >= $transaction->max_attempts) {
            Log::warning('Max retry attempts reached for payment', [
                'transaction_id' => $transaction->id,
                'attempts' => $transaction->attempts,
                'max_attempts' => $transaction->max_attempts,
            ]);
            return;
        }

        // Schedule retry
        $retryInterval = config('payment-gateway.retry_interval_hours', 24);
        $retryAt = now()->addHours($retryInterval);

        $transaction->update([
            'retry_at' => $retryAt,
            'attempts' => $transaction->attempts + 1,
        ]);

        Log::info('Payment retry scheduled', [
            'transaction_id' => $transaction->id,
            'retry_at' => $retryAt,
            'attempts' => $transaction->attempts,
        ]);
    }

    /**
     * Handle subscription retry logic.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function handleSubscriptionRetry($transaction): void
    {
        // Check if subscription retry is enabled
        if (!config('payment-gateway.recurring.retry_failed_subscriptions', true)) {
            return;
        }

        // Calculate retry time (exponential backoff)
        $retryHours = pow(2, min($transaction->attempts, 4)); // 1, 2, 4, 8, 16 hours
        $retryAt = now()->addHours($retryHours);

        $transaction->update([
            'status' => 'pending_renewal',
            'retry_at' => $retryAt,
            'attempts' => $transaction->attempts + 1,
            'error_message' => $transaction->error_message,
        ]);

        Log::info('Subscription payment retry scheduled', [
            'transaction_id' => $transaction->id,
            'subscription_id' => $transaction->subscription_id,
            'retry_at' => $retryAt,
            'attempts' => $transaction->attempts,
        ]);
    }

    /**
     * Update order status.
     *
     * @param  mixed  $orderId
     * @param  string  $status
     * @return void
     */
    protected function updateOrderStatus($orderId, string $status): void
    {
        // This is a placeholder - implement based on your application's order system
        Log::info('Order status update (failed payment) placeholder', [
            'order_id' => $orderId,
            'status' => $status,
        ]);
    }

    /**
     * Restore inventory.
     *
     * @param  mixed  $productId
     * @param  int  $quantity
     * @return void
     */
    protected function restoreInventory($productId, int $quantity): void
    {
        // This is a placeholder - implement based on your application's inventory system
        Log::info('Inventory restoration placeholder', [
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Create support ticket.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function createSupportTicket($transaction): void
    {
        // This is a placeholder - implement based on your application's support system
        Log::info('Support ticket creation placeholder', [
            'transaction_id' => $transaction->id,
            'customer_email' => $transaction->customer_email,
            'error_message' => $transaction->error_message,
        ]);
    }

    /**
     * Generate retry URL for customer.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return string
     */
    protected function generateRetryUrl($transaction): string
    {
        return url('/payment/retry/' . $transaction->reference);
    }

    /**
     * Trigger post-failure hooks.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function triggerPostFailureHooks($transaction): void
    {
        // This method can be used to trigger any custom post-failure hooks
        // defined by the application using the payment gateway

        // Example: Fire a custom event for application-specific logic
        event(new \App\Events\PaymentFailed($transaction));

        // Example: Dispatch a job for async processing
        // \App\Jobs\HandleFailedPayment::dispatch($transaction);

        Log::info('Post-failure hooks triggered', [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
        ]);
    }

    /**
     * Handle a job failure.
     *
     * @param  PaymentFailed  $event
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(PaymentFailed $event, \Throwable $exception): void
    {
        Log::critical('HandlePaymentFailed listener failed', [
            'transaction_id' => $event->transaction->id,
            'reference' => $event->transaction->reference,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Optionally send an alert to administrators
        $this->sendFailureAlert($event->transaction, $exception);
    }

    /**
     * Send failure alert.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @param  \Throwable  $exception
     * @return void
     */
    protected function sendFailureAlert($transaction, \Throwable $exception): void
    {
        $adminEmail = config('payment-gateway.notifications.admin_email');
        if (!$adminEmail) {
            return;
        }

        try {
            Mail::raw(
                "Payment failure handler failed for transaction: {$transaction->reference}\n\n" .
                "Original Error: {$transaction->error_message}\n" .
                "Handler Error: {$exception->getMessage()}\n\n" .
                "Transaction Details:\n" .
                "- Amount: {$transaction->amount} {$transaction->currency}\n" .
                "- Customer: {$transaction->customer_email}\n" .
                "- Gateway: {$transaction->gateway}\n" .
                "- Failed At: " . ($transaction->failed_at ? $transaction->failed_at->toDateTimeString() : 'N/A') . "\n" .
                "- Time: " . now()->toDateTimeString(),
                function ($message) use ($adminEmail, $transaction) {
                    $message->to($adminEmail)
                            ->subject('Payment Failure Handler Failed - ' . $transaction->reference);
                }
            );
        } catch (\Exception $e) {
            Log::error('Failed to send failure alert', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
