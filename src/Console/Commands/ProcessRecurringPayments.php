<?php

namespace Lisosoft\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lisosoft\PaymentGateway\Models\Transaction;
use Lisosoft\PaymentGateway\Facades\Payment;
use Carbon\Carbon;

class ProcessRecurringPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment-gateway:process-recurring
                            {--dry-run : Simulate processing without charging}
                            {--force : Force processing even if not due}
                            {--gateway= : Process only specific gateway}
                            {--limit=100 : Maximum number of subscriptions to process}
                            {--retry-failed : Retry failed recurring payments}
                            {--verbose : Show detailed processing information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process recurring/subscription payments';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🔄 Processing Recurring Payments...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $gateway = $this->option('gateway');
        $limit = (int) $this->option('limit');
        $retryFailed = $this->option('retry-failed');
        $verbose = $this->option('verbose');

        if ($dryRun) {
            $this->warn('⚠️ DRY RUN MODE: No actual payments will be processed.');
            $this->newLine();
        }

        // Get subscriptions due for processing
        $subscriptions = $this->getSubscriptionsDue($gateway, $force, $retryFailed, $limit);

        if ($subscriptions->isEmpty()) {
            $this->info('✅ No subscriptions due for processing.');
            return Command::SUCCESS;
        }

        $this->info("📋 Found {$subscriptions->count()} subscription(s) to process:");
        $this->newLine();

        // Process subscriptions
        $results = $this->processSubscriptions($subscriptions, $dryRun, $verbose);

        // Display results
        $this->displayResults($results, $dryRun);

        return Command::SUCCESS;
    }

    /**
     * Get subscriptions due for processing.
     *
     * @param string|null $gateway
     * @param bool $force
     * @param bool $retryFailed
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getSubscriptionsDue(?string $gateway, bool $force, bool $retryFailed, int $limit)
    {
        $query = Transaction::where('is_subscription', true)
            ->whereIn('status', ['active', 'pending_renewal']);

        // Filter by gateway if specified
        if ($gateway) {
            $query->where('gateway', $gateway);
            $this->line("Filter: Gateway = {$gateway}");
        }

        // Filter by next billing date
        if (!$force) {
            $query->where(function ($q) {
                $q->where('next_billing_date', '<=', now())
                  ->orWhereNull('next_billing_date');
            });
        }

        // Include failed subscriptions for retry
        if ($retryFailed) {
            $query->orWhere(function ($q) use ($gateway) {
                $q->where('is_subscription', true)
                  ->where('status', 'failed')
                  ->where('attempts', '<', DB::raw('max_attempts'))
                  ->where('retry_at', '<=', now());

                if ($gateway) {
                    $q->where('gateway', $gateway);
                }
            });
        }

        // Apply limit
        $query->limit($limit);

        // Order by priority
        $query->orderBy('next_billing_date', 'asc')
              ->orderBy('created_at', 'asc');

        return $query->get();
    }

    /**
     * Process subscriptions.
     *
     * @param \Illuminate\Database\Eloquent\Collection $subscriptions
     * @param bool $dryRun
     * @param bool $verbose
     * @return array
     */
    protected function processSubscriptions($subscriptions, bool $dryRun, bool $verbose): array
    {
        $results = [
            'total' => $subscriptions->count(),
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        $progressBar = $this->output->createProgressBar($subscriptions->count());
        $progressBar->start();

        foreach ($subscriptions as $subscription) {
            $result = $this->processSubscription($subscription, $dryRun, $verbose);
            $results['details'][] = $result;

            if ($result['status'] === 'success') {
                $results['successful']++;
            } elseif ($result['status'] === 'failed') {
                $results['failed']++;
            } else {
                $results['skipped']++;
            }

            $progressBar->advance();

            if ($verbose) {
                $this->newLine();
                $this->displaySubscriptionResult($result, $verbose);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        return $results;
    }

    /**
     * Process a single subscription.
     *
     * @param Transaction $subscription
     * @param bool $dryRun
     * @param bool $verbose
     * @return array
     */
    protected function processSubscription(Transaction $subscription, bool $dryRun, bool $verbose): array
    {
        $result = [
            'subscription_id' => $subscription->id,
            'reference' => $subscription->reference,
            'gateway' => $subscription->gateway,
            'customer_email' => $subscription->customer_email,
            'amount' => $subscription->amount,
            'currency' => $subscription->currency,
            'status' => 'pending',
            'message' => '',
            'error' => null,
            'transaction_id' => null,
            'dry_run' => $dryRun,
        ];

        try {
            // Check if subscription is still active
            if ($subscription->status === 'cancelled' || $subscription->status === 'expired') {
                $result['status'] = 'skipped';
                $result['message'] = "Subscription is {$subscription->status}";
                return $result;
            }

            // Check if gateway supports recurring payments
            $gatewayInstance = Payment::gateway($subscription->gateway);

            // For EFT gateway, skip automatic processing
            if ($subscription->gateway === 'eft') {
                $result['status'] = 'skipped';
                $result['message'] = 'EFT subscriptions require manual processing';
                return $result;
            }

            // Prepare payment data
            $paymentData = [
                'amount' => $subscription->amount,
                'currency' => $subscription->currency,
                'description' => "Recurring: {$subscription->description}",
                'customer' => [
                    'email' => $subscription->customer_email,
                    'name' => $subscription->customer_name,
                    'phone' => $subscription->customer_phone,
                ],
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'subscription_id' => $subscription->reference,
                    'recurring_payment' => true,
                    'billing_cycle' => $subscription->recurring_frequency,
                ]),
                'is_subscription' => true,
                'subscription_data' => [
                    'subscription_id' => $subscription->subscription_id,
                    'frequency' => $subscription->recurring_frequency,
                ],
            ];

            if ($dryRun) {
                $result['status'] = 'success';
                $result['message'] = 'Dry run - payment would be processed';
                $result['transaction_id'] = 'DRY-RUN-' . uniqid();
            } else {
                // Process the payment
                $paymentResult = Payment::initializePayment($subscription->gateway, $paymentData);

                if ($paymentResult['success'] ?? false) {
                    // Create transaction record for the payment
                    $transaction = Transaction::create([
                        'reference' => $paymentResult['transaction_id'] ?? Transaction::generateReference(),
                        'gateway' => $subscription->gateway,
                        'gateway_transaction_id' => $paymentResult['gateway_transaction_id'] ?? null,
                        'amount' => $subscription->amount,
                        'currency' => $subscription->currency,
                        'status' => 'pending',
                        'description' => $paymentData['description'],
                        'customer_email' => $subscription->customer_email,
                        'customer_name' => $subscription->customer_name,
                        'customer_phone' => $subscription->customer_phone,
                        'is_subscription' => true,
                        'subscription_id' => $subscription->subscription_id,
                        'parent_transaction_id' => $subscription->id,
                        'recurring_frequency' => $subscription->recurring_frequency,
                        'metadata' => $paymentData['metadata'],
                        'processed_at' => now(),
                    ]);

                    // Update subscription
                    $this->updateSubscriptionAfterPayment($subscription, $transaction);

                    $result['status'] = 'success';
                    $result['message'] = 'Payment processed successfully';
                    $result['transaction_id'] = $transaction->reference;

                    Log::info('Recurring payment processed', [
                        'subscription_id' => $subscription->id,
                        'transaction_id' => $transaction->reference,
                        'amount' => $subscription->amount,
                        'currency' => $subscription->currency,
                        'gateway' => $subscription->gateway,
                    ]);
                } else {
                    // Handle payment failure
                    $this->handlePaymentFailure($subscription, $paymentResult);

                    $result['status'] = 'failed';
                    $result['message'] = $paymentResult['message'] ?? 'Payment processing failed';
                    $result['error'] = $paymentResult['error'] ?? 'Unknown error';

                    Log::error('Recurring payment failed', [
                        'subscription_id' => $subscription->id,
                        'gateway' => $subscription->gateway,
                        'amount' => $subscription->amount,
                        'error' => $result['error'],
                    ]);
                }
            }

        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = 'Exception during processing';
            $result['error'] = $e->getMessage();

            Log::error('Exception processing recurring payment', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $result;
    }

    /**
     * Update subscription after successful payment.
     *
     * @param Transaction $subscription
     * @param Transaction $transaction
     * @return void
     */
    protected function updateSubscriptionAfterPayment(Transaction $subscription, Transaction $transaction): void
    {
        // Calculate next billing date based on frequency
        $nextBillingDate = $this->calculateNextBillingDate(
            $subscription->recurring_frequency,
            $subscription->next_billing_date ?? now()
        );

        // Update subscription
        $subscription->update([
            'status' => 'active',
            'next_billing_date' => $nextBillingDate,
            'attempts' => 0, // Reset attempts on success
            'retry_at' => null,
            'last_payment_date' => now(),
            'last_transaction_id' => $transaction->id,
        ]);

        // Update cycles if applicable
        if ($subscription->recurring_cycles) {
            $cyclesCompleted = $subscription->cycles_completed ?? 0;
            $subscription->cycles_completed = $cyclesCompleted + 1;

            // Check if subscription has completed all cycles
            if ($subscription->cycles_completed >= $subscription->recurring_cycles) {
                $subscription->status = 'completed';
                $subscription->next_billing_date = null;
            }

            $subscription->save();
        }
    }

    /**
     * Handle payment failure.
     *
     * @param Transaction $subscription
     * @param array $paymentResult
     * @return void
     */
    protected function handlePaymentFailure(Transaction $subscription, array $paymentResult): void
    {
        $attempts = $subscription->attempts + 1;
        $maxAttempts = $subscription->max_attempts ?? 3;

        // Calculate retry time (exponential backoff)
        $retryHours = pow(2, min($attempts - 1, 4)); // 1, 2, 4, 8, 16 hours
        $retryAt = now()->addHours($retryHours);

        $subscription->update([
            'status' => $attempts >= $maxAttempts ? 'failed' : 'pending_renewal',
            'attempts' => $attempts,
            'retry_at' => $retryAt,
            'error_message' => $paymentResult['message'] ?? 'Payment processing failed',
            'error_code' => $paymentResult['error_code'] ?? null,
            'failed_at' => now(),
        ]);

        // If max attempts reached, mark as failed
        if ($attempts >= $maxAttempts) {
            $subscription->update([
                'status' => 'failed',
                'next_billing_date' => null,
            ]);

            Log::warning('Subscription marked as failed after max retry attempts', [
                'subscription_id' => $subscription->id,
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
            ]);
        }
    }

    /**
     * Calculate next billing date.
     *
     * @param string $frequency
     * @param \Carbon\Carbon $currentDate
     * @return \Carbon\Carbon
     */
    protected function calculateNextBillingDate(string $frequency, Carbon $currentDate): Carbon
    {
        return match ($frequency) {
            'daily' => $currentDate->addDay(),
            'weekly' => $currentDate->addWeek(),
            'monthly' => $currentDate->addMonth(),
            'quarterly' => $currentDate->addMonths(3),
            'yearly' => $currentDate->addYear(),
            default => $currentDate->addMonth(), // Default to monthly
        };
    }

    /**
     * Display subscription processing result.
     *
     * @param array $result
     * @param bool $verbose
     * @return void
     */
    protected function displaySubscriptionResult(array $result, bool $verbose): void
    {
        $statusIcon = match ($result['status']) {
            'success' => '✅',
            'failed' => '❌',
            'skipped' => '⏭️',
            default => '❓',
        };

        $this->line("{$statusIcon} Subscription: {$result['reference']}");
        $this->line("   Gateway: {$result['gateway']}");
        $this->line("   Customer: {$result['customer_email']}");
        $this->line("   Amount: {$result['amount']} {$result['currency']}");
        $this->line("   Status: {$result['status']}");
        $this->line("   Message: {$result['message']}");

        if ($result['transaction_id']) {
            $this->line("   Transaction: {$result['transaction_id']}");
        }

        if ($result['error']) {
            $this->line("   Error: {$result['error']}");
        }

        if ($result['dry_run']) {
            $this->line("   Mode: 🧪 Dry Run");
        }

        $this->newLine();
    }

    /**
     * Display processing results.
     *
     * @param array $results
     * @param bool $dryRun
     * @return void
     */
    protected function displayResults(array $results, bool $dryRun): void
    {
        $this->info('📊 Processing Results:');
        $this->newLine();

        $this->line("Total Processed: {$results['total']}");
        $this->line("✅ Successful: {$results['successful']}");
        $this->line("❌ Failed: {$results['failed']}");
        $this->line("⏭️ Skipped: {$results['skipped']}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('⚠️ This was a dry run. No actual payments were processed.');
        }

        // Show detailed breakdown by gateway
        $gatewayBreakdown = [];
        foreach ($results['details'] as $detail) {
            $gateway = $detail['gateway'];
            if (!isset($gatewayBreakdown[$gateway])) {
                $gatewayBreakdown[$gateway] = ['total' => 0, 'success' => 0, 'failed' => 0, 'skipped' => 0];
            }

            $gatewayBreakdown[$gateway]['total']++;
            $gatewayBreakdown[$gateway][$detail['status']]++;
        }

        if (!empty($gatewayBreakdown)) {
            $this->newLine();
            $this->info('🌐 Gateway Breakdown:');

            foreach ($gatewayBreakdown as $gateway => $stats) {
                $successRate = $stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 1) : 0;
                $this->line("  {$gateway}: {$stats['success']}/{$stats['total']} successful ({$successRate}%)");
            }
        }

        // Show summary of failures
        $failures = array_filter($results['details'], fn($detail) => $detail['status'] === 'failed');
        if (!empty($failures)) {
            $this->newLine();
            $this->warn('⚠️ Failed Subscriptions:');

            foreach ($failures as $failure) {
                $this->line("  • {$failure['reference']}: {$failure['error']}");
            }
        }

        $this->newLine();
        $this->info('💡 Next Steps:');
        $this->line('• Check failed subscriptions for issues');
        $this->line('• Review transaction logs for details');
        $this->line('• Run with --retry-failed to retry failed payments');

        if ($dryRun) {
            $this->line('• Run without --dry-run to process actual payments');
        }

        $this->newLine();
        $this->info('✅ Recurring payment processing completed!');
    }
}
