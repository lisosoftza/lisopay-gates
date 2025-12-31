<?php

namespace Lisosoft\PaymentGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lisosoft\PaymentGateway\Models\Transaction;

class PaymentCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * The transaction instance.
     *
     * @var Transaction
     */
    public $transaction;

    /**
     * The payment gateway used.
     *
     * @var string
     */
    public $gateway;

    /**
     * The payment amount.
     *
     * @var float
     */
    public $amount;

    /**
     * The payment currency.
     *
     * @var string
     */
    public $currency;

    /**
     * Additional payment data.
     *
     * @var array
     */
    public $paymentData;

    /**
     * Create a new event instance.
     *
     * @param Transaction $transaction
     * @param string $gateway
     * @param float $amount
     * @param string $currency
     * @param array $paymentData
     */
    public function __construct(
        Transaction $transaction,
        string $gateway,
        float $amount,
        string $currency,
        array $paymentData = []
    ) {
        $this->transaction = $transaction;
        $this->gateway = $gateway;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->paymentData = $paymentData;
    }

    /**
     * Get the transaction reference.
     *
     * @return string
     */
    public function getTransactionReference(): string
    {
        return $this->transaction->reference;
    }

    /**
     * Get the customer email.
     *
     * @return string|null
     */
    public function getCustomerEmail(): ?string
    {
        return $this->transaction->customer_email;
    }

    /**
     * Get the customer name.
     *
     * @return string|null
     */
    public function getCustomerName(): ?string
    {
        return $this->transaction->customer_name;
    }

    /**
     * Get the payment description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->transaction->description;
    }

    /**
     * Check if payment is a subscription.
     *
     * @return bool
     */
    public function isSubscription(): bool
    {
        return $this->transaction->is_subscription ?? false;
    }

    /**
     * Get subscription ID if applicable.
     *
     * @return string|null
     */
    public function getSubscriptionId(): ?string
    {
        return $this->transaction->subscription_id ?? null;
    }

    /**
     * Get the payment metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->transaction->metadata ?? [];
    }

    /**
     * Get the payment status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->transaction->status ?? 'completed';
    }

    /**
     * Get the event data as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'transaction' => [
                'id' => $this->transaction->id,
                'reference' => $this->transaction->reference,
                'amount' => $this->transaction->amount,
                'currency' => $this->transaction->currency,
                'status' => $this->transaction->status,
                'description' => $this->transaction->description,
                'customer_email' => $this->transaction->customer_email,
                'customer_name' => $this->transaction->customer_name,
                'gateway' => $this->transaction->gateway,
                'gateway_transaction_id' => $this->transaction->gateway_transaction_id,
                'is_subscription' => $this->transaction->is_subscription,
                'subscription_id' => $this->transaction->subscription_id,
                'metadata' => $this->transaction->metadata,
                'created_at' => $this->transaction->created_at?->toISOString(),
                'updated_at' => $this->transaction->updated_at?->toISOString(),
            ],
            'gateway' => $this->gateway,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_data' => $this->paymentData,
            'timestamp' => now()->toISOString(),
            'event_type' => 'payment_completed',
        ];
    }

    /**
     * Get the event data as JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * Broadcast the event on a specific channel.
     *
     * @return array
     */
    public function broadcastOn(): array
    {
        return [
            'payment.' . $this->transaction->reference,
            'user.' . ($this->transaction->user_id ?? 'anonymous'),
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'payment.completed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'transaction_reference' => $this->transaction->reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'gateway' => $this->gateway,
            'status' => 'completed',
            'timestamp' => now()->toISOString(),
        ];
    }
}
