<?php

namespace Lisosoft\PaymentGateway\Exceptions;

use Exception;

class PaymentGatewayException extends Exception
{
    /**
     * Additional error details
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Gateway name that caused the exception
     *
     * @var string|null
     */
    protected $gateway;

    /**
     * Transaction ID related to the exception
     *
     * @var string|null
     */
    protected $transactionId;

    /**
     * Create a new PaymentGatewayException instance.
     *
     * @param string $message
     * @param array $errors
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct(
        string $message = "",
        array $errors = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Set the gateway name.
     *
     * @param string $gateway
     * @return $this
     */
    public function setGateway(string $gateway): self
    {
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * Get the gateway name.
     *
     * @return string|null
     */
    public function getGateway(): ?string
    {
        return $this->gateway;
    }

    /**
     * Set the transaction ID.
     *
     * @param string $transactionId
     * @return $this
     */
    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    /**
     * Get the transaction ID.
     *
     * @return string|null
     */
    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    /**
     * Get additional error details.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Add an error detail.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function addError(string $key, $value): self
    {
        $this->errors[$key] = $value;
        return $this;
    }

    /**
     * Check if there are any error details.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Create a new instance for invalid configuration.
     *
     * @param string $gateway
     * @param array $missingConfig
     * @return static
     */
    public static function invalidConfiguration(string $gateway, array $missingConfig = []): self
    {
        $message = "Invalid configuration for gateway '{$gateway}'";
        if (!empty($missingConfig)) {
            $message .= ". Missing configuration: " . implode(', ', $missingConfig);
        }

        return new static($message, [
            'gateway' => $gateway,
            'missing_configuration' => $missingConfig,
            'type' => 'configuration_error',
        ]);
    }

    /**
     * Create a new instance for payment initialization failure.
     *
     * @param string $gateway
     * @param string $reason
     * @param array $context
     * @return static
     */
    public static function paymentInitializationFailed(string $gateway, string $reason, array $context = []): self
    {
        return new static(
            "Failed to initialize payment with gateway '{$gateway}': {$reason}",
            array_merge([
                'gateway' => $gateway,
                'reason' => $reason,
                'type' => 'initialization_error',
            ], $context)
        );
    }

    /**
     * Create a new instance for payment verification failure.
     *
     * @param string $gateway
     * @param string $transactionId
     * @param string $reason
     * @return static
     */
    public static function paymentVerificationFailed(string $gateway, string $transactionId, string $reason): self
    {
        return new static(
            "Failed to verify payment '{$transactionId}' with gateway '{$gateway}': {$reason}",
            [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'reason' => $reason,
                'type' => 'verification_error',
            ]
        );
    }

    /**
     * Create a new instance for callback processing failure.
     *
     * @param string $gateway
     * @param string $reason
     * @param array $callbackData
     * @return static
     */
    public static function callbackProcessingFailed(string $gateway, string $reason, array $callbackData = []): self
    {
        return new static(
            "Failed to process callback from gateway '{$gateway}': {$reason}",
            [
                'gateway' => $gateway,
                'reason' => $reason,
                'callback_data' => $callbackData,
                'type' => 'callback_error',
            ]
        );
    }

    /**
     * Create a new instance for refund failure.
     *
     * @param string $gateway
     * @param string $transactionId
     * @param string $reason
     * @param float|null $amount
     * @return static
     */
    public static function refundFailed(string $gateway, string $transactionId, string $reason, ?float $amount = null): self
    {
        $message = "Failed to refund payment '{$transactionId}' with gateway '{$gateway}': {$reason}";

        $errors = [
            'gateway' => $gateway,
            'transaction_id' => $transactionId,
            'reason' => $reason,
            'type' => 'refund_error',
        ];

        if ($amount !== null) {
            $errors['amount'] = $amount;
        }

        return new static($message, $errors);
    }

    /**
     * Create a new instance for invalid signature.
     *
     * @param string $gateway
     * @param string $reason
     * @return static
     */
    public static function invalidSignature(string $gateway, string $reason = 'Invalid signature'): self
    {
        return new static(
            "Invalid signature from gateway '{$gateway}': {$reason}",
            [
                'gateway' => $gateway,
                'reason' => $reason,
                'type' => 'security_error',
            ]
        );
    }

    /**
     * Create a new instance for unsupported currency.
     *
     * @param string $gateway
     * @param string $currency
     * @param array $supportedCurrencies
     * @return static
     */
    public static function unsupportedCurrency(string $gateway, string $currency, array $supportedCurrencies = []): self
    {
        $message = "Currency '{$currency}' is not supported by gateway '{$gateway}'";

        $errors = [
            'gateway' => $gateway,
            'currency' => $currency,
            'type' => 'currency_error',
        ];

        if (!empty($supportedCurrencies)) {
            $errors['supported_currencies'] = $supportedCurrencies;
            $message .= ". Supported currencies: " . implode(', ', $supportedCurrencies);
        }

        return new static($message, $errors);
    }

    /**
     * Create a new instance for invalid amount.
     *
     * @param string $gateway
     * @param float $amount
     * @param float $minimum
     * @param float $maximum
     * @return static
     */
    public static function invalidAmount(string $gateway, float $amount, float $minimum, float $maximum): self
    {
        $message = "Amount {$amount} is invalid for gateway '{$gateway}'. Must be between {$minimum} and {$maximum}";

        return new static($message, [
            'gateway' => $gateway,
            'amount' => $amount,
            'minimum_amount' => $minimum,
            'maximum_amount' => $maximum,
            'type' => 'amount_error',
        ]);
    }

    /**
     * Create a new instance for gateway not available.
     *
     * @param string $gateway
     * @return static
     */
    public static function gatewayNotAvailable(string $gateway): self
    {
        return new static(
            "Gateway '{$gateway}' is not available or not enabled",
            [
                'gateway' => $gateway,
                'type' => 'availability_error',
            ]
        );
    }

    /**
     * Create a new instance for network error.
     *
     * @param string $gateway
     * @param string $reason
     * @param int $statusCode
     * @return static
     */
    public static function networkError(string $gateway, string $reason, int $statusCode = 0): self
    {
        return new static(
            "Network error with gateway '{$gateway}': {$reason}",
            [
                'gateway' => $gateway,
                'reason' => $reason,
                'status_code' => $statusCode,
                'type' => 'network_error',
            ]
        );
    }

    /**
     * Convert the exception to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'gateway' => $this->getGateway(),
            'transaction_id' => $this->getTransactionId(),
            'errors' => $this->getErrors(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTrace(),
        ];
    }

    /**
     * Convert the exception to JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
