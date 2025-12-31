<?php

namespace Lisosoft\PaymentGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lisosoft\PaymentGateway\Models\Transaction;

class PaymentFailed
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
     * The error message.
     *
     * @var string
     */
    public $errorMessage;

    /**
     * The error code.
     *
     * @var string|null
     */
    public $errorCode;

    /**
     * Additional error details.
     *
     * @var array
     */
    public $errorDetails;

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
     * @param string $errorMessage
     * @param string|null $errorCode
     * @param array $errorDetails
     * @param array $paymentData
     */
    public function __construct(
        Transaction $transaction,
        string $gateway,
        float $amount,
        string $currency,
        string $errorMessage,
        ?string $errorCode = null,
        array $errorDetails = [],
        array $paymentData = []
    ) {
        $this->transaction = $transaction;
        $this->gateway = $gateway;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->errorMessage = $errorMessage;
        $this->errorCode = $errorCode;
        $this->errorDetails = $errorDetails;
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
        return $this->transaction->status ?? 'failed';
    }

    /**
     * Get the error message.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * Get the error code.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get the error details.
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    /**
     * Check if error is retryable.
     *
     * @return bool
     */
    public function isRetryable(): bool
    {
        $nonRetryableCodes = [
            'invalid_card',
            'insufficient_funds',
            'card_declined',
            'expired_card',
            'invalid_amount',
            'invalid_currency',
        ];

        if ($this->errorCode && in_array($this->errorCode, $nonRetryableCodes)) {
            return false;
        }

        // Check error message for non-retryable patterns
        $nonRetryablePatterns = [
            'insufficient',
            'declined',
            'expired',
            'invalid',
            'not supported',
            'unauthorized',
        ];

        foreach ($nonRetryablePatterns as $pattern) {
            if (stripos($this->errorMessage, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get retry suggestion.
     *
     * @return string|null
     */
    public function getRetrySuggestion(): ?string
    {
        if (!$this->isRetryable()) {
            return 'This error is not retryable. Please check payment details and try again.';
        }

        $suggestions = [
            'network_error' => 'Network issue detected. Please try again in a few moments.',
            'timeout' => 'Payment request timed out. Please try again.',
            'temporary_error' => 'Temporary error occurred. Please try again.',
            'gateway_busy' => 'Payment gateway is busy. Please try again in a few minutes.',
        ];

        if ($this->errorCode && isset($suggestions[$this->errorCode])) {
            return $suggestions[$this->errorCode];
        }

        return 'Please try again. If the problem persists, contact support.';
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
            'error' => [
                'message' => $this->errorMessage,
                'code' => $this->errorCode,
                'details' => $this->errorDetails,
                'retryable' => $this->isRetryable(),
                'retry_suggestion' => $this->getRetrySuggestion(),
            ],
            'payment_data' => $this->paymentData,
            'timestamp' => now()->toISOString(),
            'event_type' => 'payment_failed',
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
            'errors.payment',
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'payment.failed';
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
            'status' => 'failed',
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'retryable' => $this->isRetryable(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Create a new instance from exception.
     *
     * @param Transaction $transaction
     * @param \Throwable $exception
     * @param array $paymentData
     * @return static
     */
    public static function fromException(
        Transaction $transaction,
        \Throwable $exception,
        array $paymentData = []
    ): self {
        $errorCode = method_exists($exception, 'getCode') ? (string) $exception->getCode() : null;

        $errorDetails = [];
        if (method_exists($exception, 'getErrors')) {
            $errorDetails = $exception->getErrors();
        }

        return new static(
            $transaction,
            $transaction->gateway,
            $transaction->amount,
            $transaction->currency,
            $exception->getMessage(),
            $errorCode,
            $errorDetails,
            $paymentData
        );
    }

    /**
     * Create a new instance from gateway error.
     *
     * @param Transaction $transaction
     * @param string $gateway
     * @param string $errorMessage
     * @param string|null $errorCode
     * @param array $errorDetails
     * @param array $paymentData
     * @return static
     */
    public static function fromGatewayError(
        Transaction $transaction,
        string $gateway,
        string $errorMessage,
        ?string $errorCode = null,
        array $errorDetails = [],
        array $paymentData = []
    ): self {
        return new static(
            $transaction,
            $gateway,
            $transaction->amount,
            $transaction->currency,
            $errorMessage,
            $errorCode,
            $errorDetails,
            $paymentData
        );
    }
}
