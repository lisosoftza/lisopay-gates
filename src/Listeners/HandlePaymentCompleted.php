<?php

namespace Lisosoft\PaymentGateway\Listeners;

use Lisosoft\PaymentGateway\Events\PaymentCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Lisosoft\PaymentGateway\Notifications\PaymentCompletedNotification;

class HandlePaymentCompleted implements ShouldQueue
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
     * @param  PaymentCompleted  $event
     * @return void
     */
    public function handle(PaymentCompleted $event)
    {
        $transaction = $event->transaction;

        try {
            // Log the successful payment
            $this->logPaymentCompletion($transaction);

            // Send email notification to customer
            $this->sendCustomerNotification($transaction);

            // Send notification to admin/merchant
            $this->sendAdminNotification($transaction);

            // Update related business logic (e.g., order status, inventory)
            $this->updateBusinessLogic($transaction);

            // Trigger any post-payment hooks
            $this->triggerPostPaymentHooks($transaction);

            Log::info('Payment completed successfully processed', [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle payment completion', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to allow queue retry
            throw $e;
        }
    }

    /**
     * Log payment completion.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function logPaymentCompletion($transaction): void
    {
        Log::channel('payments')->info('Payment completed', [
            'reference' => $transaction->reference,
            'gateway' => $transaction->gateway,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'customer_email' => $transaction->customer_email,
            'customer_name' => $transaction->customer_name,
            'completed_at' => $transaction->completed_at,
            'metadata' => $transaction->metadata,
        ]);
    }

    /**
     * Send notification to customer.
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

        try {
            // Prepare email data
            $emailData = [
                'transaction' => $transaction,
                'subject' => 'Payment Confirmation - ' . config('app.name'),
                'to' => $transaction->customer_email,
                'from' => [
                    'email' => config('payment-gateway.notifications.email.sender_email', 'noreply@example.com'),
                    'name' => config('payment-gateway.notifications.email.sender_name', 'Payment System'),
                ],
            ];

            // Send email using Laravel's mail system
            Mail::send('payment-gateway::emails.payment-completed', $emailData, function ($message) use ($emailData) {
                $message->to($emailData['to'])
                        ->from($emailData['from']['email'], $emailData['from']['name'])
                        ->subject($emailData['subject']);
            });

            Log::info('Payment confirmation email sent to customer', [
                'transaction_id' => $transaction->id,
                'customer_email' => $transaction->customer_email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation email', [
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

        try {
            // Prepare notification data
            $notificationData = [
                'transaction' => $transaction,
                'type' => 'payment_completed',
                'timestamp' => now()->toISOString(),
            ];

            // Send notification (could be email, Slack, etc.)
            Notification::route('mail', $adminEmail)
                ->notify(new PaymentCompletedNotification($notificationData));

            Log::info('Payment notification sent to admin', [
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
     * Update business logic after payment.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function updateBusinessLogic($transaction): void
    {
        // This method should be extended by the application
        // to handle business-specific logic after payment

        // Example: Update order status
        if (isset($transaction->metadata['order_id'])) {
            $this->updateOrderStatus($transaction->metadata['order_id'], 'paid');
        }

        // Example: Update inventory
        if (isset($transaction->metadata['product_id'])) {
            $this->updateInventory($transaction->metadata['product_id'], -1);
        }

        // Example: Grant user access or credits
        if ($transaction->user_id) {
            $this->grantUserAccess($transaction->user_id, $transaction);
        }

        // Example: Trigger webhook to external service
        if (isset($transaction->metadata['webhook_url'])) {
            $this->triggerExternalWebhook($transaction);
        }
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
        Log::info('Order status update placeholder', [
            'order_id' => $orderId,
            'status' => $status,
        ]);
    }

    /**
     * Update inventory.
     *
     * @param  mixed  $productId
     * @param  int  $quantityChange
     * @return void
     */
    protected function updateInventory($productId, int $quantityChange): void
    {
        // This is a placeholder - implement based on your application's inventory system
        Log::info('Inventory update placeholder', [
            'product_id' => $productId,
            'quantity_change' => $quantityChange,
        ]);
    }

    /**
     * Grant user access or credits.
     *
     * @param  mixed  $userId
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function grantUserAccess($userId, $transaction): void
    {
        // This is a placeholder - implement based on your application's user system
        Log::info('User access grant placeholder', [
            'user_id' => $userId,
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
        ]);
    }

    /**
     * Trigger external webhook.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function triggerExternalWebhook($transaction): void
    {
        $webhookUrl = $transaction->metadata['webhook_url'];

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($webhookUrl, [
                'json' => [
                    'event' => 'payment.completed',
                    'data' => [
                        'transaction' => $transaction->toArray(),
                        'timestamp' => now()->toISOString(),
                    ],
                ],
                'timeout' => 10,
            ]);

            Log::info('External webhook triggered successfully', [
                'transaction_id' => $transaction->id,
                'webhook_url' => $webhookUrl,
                'status_code' => $response->getStatusCode(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to trigger external webhook', [
                'transaction_id' => $transaction->id,
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Trigger post-payment hooks.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return void
     */
    protected function triggerPostPaymentHooks($transaction): void
    {
        // This method can be used to trigger any custom post-payment hooks
        // defined by the application using the payment gateway

        // Example: Fire a custom event for application-specific logic
        event(new \App\Events\PaymentProcessed($transaction));

        // Example: Dispatch a job for async processing
        // \App\Jobs\ProcessPayment::dispatch($transaction);

        Log::info('Post-payment hooks triggered', [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
        ]);
    }

    /**
     * Handle a job failure.
     *
     * @param  PaymentCompleted  $event
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(PaymentCompleted $event, \Throwable $exception): void
    {
        Log::critical('HandlePaymentCompleted listener failed', [
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
                "Payment completion handler failed for transaction: {$transaction->reference}\n\n" .
                "Error: {$exception->getMessage()}\n\n" .
                "Transaction Details:\n" .
                "- Amount: {$transaction->amount} {$transaction->currency}\n" .
                "- Customer: {$transaction->customer_email}\n" .
                "- Gateway: {$transaction->gateway}\n" .
                "- Time: " . now()->toDateTimeString(),
                function ($message) use ($adminEmail, $transaction) {
                    $message->to($adminEmail)
                            ->subject('Payment Handler Failed - ' . $transaction->reference);
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
