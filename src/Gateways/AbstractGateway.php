<?php

namespace Lisosoft\PaymentGateway\Gateways;

use Lisosoft\PaymentGateway\Contracts\PaymentGatewayInterface;
use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class AbstractGateway implements PaymentGatewayInterface
{
    /**
     * Gateway configuration
     *
     * @var array
     */
    protected $config = [];

    /**
     * Gateway name
     *
     * @var string
     */
    protected $name;

    /**
     * Gateway display name
     *
     * @var string
     */
    protected $displayName;

    /**
     * Last error information
     *
     * @var array|null
     */
    protected $lastError = null;

    /**
     * HTTP client instance
     *
     * @var \Illuminate\Http\Client\PendingRequest
     */
    protected $httpClient;

    /**
     * Supported currencies
     *
     * @var array
     */
    protected $supportedCurrencies = [];

    /**
     * Supported payment methods
     *
     * @var array
     */
    protected $paymentMethods = [];

    /**
     * Constructor
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->initializeHttpClient();
        $this->initialize();
    }

    /**
     * Initialize the gateway
     *
     * @return void
     */
    protected function initialize(): void
    {
        // Override in child classes for gateway-specific initialization
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    abstract protected function getDefaultConfig(): array;

    /**
     * Initialize HTTP client with gateway-specific settings
     *
     * @return void
     */
    protected function initializeHttpClient(): void
    {
        $this->httpClient = Http::timeout($this->config['timeout'] ?? 30)
            ->retry($this->config['retry_attempts'] ?? 3, $this->config['retry_delay'] ?? 100)
            ->withHeaders($this->getDefaultHeaders());

        if ($this->config['verify_ssl'] ?? true) {
            $this->httpClient->withOptions(['verify' => true]);
        } else {
            $this->httpClient->withOptions(['verify' => false]);
        }
    }

    /**
     * Get default HTTP headers
     *
     * @return array
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'User-Agent' => 'Lisosoft-Payment-Gateway/' . $this->getVersion(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Initialize a payment transaction
     *
     * @param array $paymentData
     * @return array
     * @throws PaymentGatewayException
     */
    public function initializePayment(array $paymentData): array
    {
        try {
            $this->clearLastError();

            // Validate payment data
            $validationResult = $this->validatePaymentData($paymentData);
            if (!$validationResult['valid']) {
                throw new PaymentGatewayException(
                    $validationResult['message'] ?? 'Invalid payment data',
                    $validationResult['errors'] ?? []
                );
            }

            // Generate reference if not provided
            if (empty($paymentData['reference'])) {
                $paymentData['reference'] = $this->generateReference($paymentData);
            }

            // Set default currency if not provided
            if (empty($paymentData['currency'])) {
                $paymentData['currency'] = $this->config['currency'] ?? 'ZAR';
            }

            // Check currency support
            if (!$this->supportsCurrency($paymentData['currency'])) {
                throw new PaymentGatewayException(
                    "Currency {$paymentData['currency']} is not supported by {$this->getDisplayName()}"
                );
            }

            // Check amount limits
            $this->validateAmount($paymentData['amount']);

            // Call gateway-specific implementation
            return $this->processInitializePayment($paymentData);

        } catch (PaymentGatewayException $e) {
            $this->setLastError($e->getMessage(), $e->getCode(), $e->getErrors());
            throw $e;
        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), $e->getCode());
            throw new PaymentGatewayException(
                "Failed to initialize payment: {$e->getMessage()}",
                [],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Gateway-specific payment initialization
     *
     * @param array $paymentData
     * @return array
     */
    abstract protected function processInitializePayment(array $paymentData): array;

    /**
     * Verify a payment transaction
     *
     * @param string $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $this->clearLastError();

            if (empty($transactionId)) {
                throw new PaymentGatewayException('Transaction ID is required');
            }

            return $this->processVerifyPayment($transactionId);

        } catch (PaymentGatewayException $e) {
            $this->setLastError($e->getMessage(), $e->getCode(), $e->getErrors());
            throw $e;
        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), $e->getCode());
            throw new PaymentGatewayException(
                "Failed to verify payment: {$e->getMessage()}",
                [],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Gateway-specific payment verification
     *
     * @param string $transactionId
     * @return array
     */
    abstract protected function processVerifyPayment(string $transactionId): array;

    /**
     * Process a payment callback/webhook
     *
     * @param array $callbackData
     * @return array
     * @throws PaymentGatewayException
     */
    public function processCallback(array $callbackData): array
    {
        try {
            $this->clearLastError();

            // Validate callback signature if required
            if ($this->config['validate_signature'] ?? true) {
                $this->validateCallbackSignature($callbackData);
            }

            return $this->processCallbackData($callbackData);

        } catch (PaymentGatewayException $e) {
            $this->setLastError($e->getMessage(), $e->getCode(), $e->getErrors());
            throw $e;
        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), $e->getCode());
            throw new PaymentGatewayException(
                "Failed to process callback: {$e->getMessage()}",
                [],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Gateway-specific callback processing
     *
     * @param array $callbackData
     * @return array
     */
    abstract protected function processCallbackData(array $callbackData): array;

    /**
     * Validate callback signature
     *
     * @param array $callbackData
     * @return bool
     * @throws PaymentGatewayException
     */
    protected function validateCallbackSignature(array $callbackData): bool
    {
        // Override in child classes for signature validation
        return true;
    }

    /**
     * Refund a payment
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     * @throws PaymentGatewayException
     */
    public function refundPayment(string $transactionId, ?float $amount = null): array
    {
        try {
            $this->clearLastError();

            if (empty($transactionId)) {
                throw new PaymentGatewayException('Transaction ID is required');
            }

            return $this->processRefundPayment($transactionId, $amount);

        } catch (PaymentGatewayException $e) {
            $this->setLastError($e->getMessage(), $e->getCode(), $e->getErrors());
            throw $e;
        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), $e->getCode());
            throw new PaymentGatewayException(
                "Failed to process refund: {$e->getMessage()}",
                [],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Gateway-specific refund processing
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     */
    abstract protected function processRefundPayment(string $transactionId, ?float $amount = null): array;

    /**
     * Check if gateway is available
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Get gateway configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get gateway name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get gateway display name
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * Get supported currencies
     *
     * @return array
     */
    public function getSupportedCurrencies(): array
    {
        return $this->supportedCurrencies;
    }

    /**
     * Check if currency is supported
     *
     * @param string $currency
     * @return bool
     */
    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->supportedCurrencies);
    }

    /**
     * Get minimum payment amount
     *
     * @return float
     */
    public function getMinimumAmount(): float
    {
        return $this->config['minimum_amount'] ?? 1.00;
    }

    /**
     * Get maximum payment amount
     *
     * @return float
     */
    public function getMaximumAmount(): float
    {
        return $this->config['maximum_amount'] ?? 1000000.00;
    }

    /**
     * Validate payment amount
     *
     * @param float $amount
     * @return void
     * @throws PaymentGatewayException
     */
    protected function validateAmount(float $amount): void
    {
        $minimum = $this->getMinimumAmount();
        $maximum = $this->getMaximumAmount();

        if ($amount < $minimum) {
            throw new PaymentGatewayException(
                "Amount must be at least {$minimum}"
            );
        }

        if ($amount > $maximum) {
            throw new PaymentGatewayException(
                "Amount cannot exceed {$maximum}"
            );
        }
    }

    /**
     * Check if test mode is enabled
     *
     * @return bool
     */
    public function isTestMode(): bool
    {
        return $this->config['test_mode'] ?? true;
    }

    /**
     * Get payment form URL
     *
     * @return string
     */
    public function getPaymentUrl(): string
    {
        return $this->config['payment_url'] ?? '';
    }

    /**
     * Get webhook URL
     *
     * @return string
     */
    public function getWebhookUrl(): string
    {
        return $this->config['webhook_url'] ?? '';
    }

    /**
     * Get return URL
     *
     * @return string
     */
    public function getReturnUrl(): string
    {
        return $this->config['return_url'] ?? '';
    }

    /**
     * Get cancel URL
     *
     * @return string
     */
    public function getCancelUrl(): string
    {
        return $this->config['cancel_url'] ?? '';
    }

    /**
     * Generate payment reference
     *
     * @param array $paymentData
     * @return string
     */
    public function generateReference(array $paymentData): string
    {
        $prefix = $this->config['reference_prefix'] ?? 'LISO';
        $timestamp = time();
        $random = Str::random(6);

        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Validate payment data
     *
     * @param array $paymentData
     * @return array
     */
    public function validatePaymentData(array $paymentData): array
    {
        $errors = [];

        // Check required fields
        $requiredFields = $this->getRequiredFields();
        foreach ($requiredFields as $field) {
            if (empty($paymentData[$field])) {
                $errors[$field] = "The {$field} field is required";
            }
        }

        // Validate amount
        if (isset($paymentData['amount'])) {
            try {
                $this->validateAmount($paymentData['amount']);
            } catch (PaymentGatewayException $e) {
                $errors['amount'] = $e->getMessage();
            }
        }

        // Validate currency
        if (isset($paymentData['currency']) && !$this->supportsCurrency($paymentData['currency'])) {
            $errors['currency'] = "Currency {$paymentData['currency']} is not supported";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'message' => empty($errors) ? 'Validation passed' : 'Validation failed',
        ];
    }

    /**
     * Create subscription/recurring payment
     *
     * @param array $subscriptionData
     * @return array
     */
    public function createSubscription(array $subscriptionData): array
    {
        throw new PaymentGatewayException('Subscriptions are not supported by this gateway');
    }

    /**
     * Cancel subscription
     *
     * @param string $subscriptionId
     * @return array
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        throw new PaymentGatewayException('Subscriptions are not supported by this gateway');
    }

    /**
     * Get subscription details
     *
     * @param string $subscriptionId
     * @return array
     */
    public function getSubscription(string $subscriptionId): array
    {
        throw new PaymentGatewayException('Subscriptions are not supported by this gateway');
    }

    /**
     * Update subscription
     *
     * @param string $subscriptionId
     * @param array $subscriptionData
     * @return array
     */
    public function updateSubscription(string $subscriptionId, array $subscriptionData): array
    {
        throw new PaymentGatewayException('Subscriptions are not supported by this gateway');
    }

    /**
     * Get transaction history
     *
     * @param array $filters
     * @return array
     */
    public function getTransactionHistory(array $filters = []): array
    {
        throw new PaymentGatewayException('Transaction history is not supported by this gateway');
    }

    /**
     * Get payment status
     *
     * @param string $transactionId
     * @return string
     */
    public function getPaymentStatus(string $transactionId): string
    {
        try {
            $verification = $this->verifyPayment($transactionId);
            return $verification['status'] ?? 'unknown';
        } catch (\Exception $e) {
            return 'error';
        }
    }

    /**
     * Check if payment is successful
     *
     * @param string $transactionId
     * @return bool
     */
    public function isPaymentSuccessful(string $transactionId): bool
    {
        $status = $this->getPaymentStatus($transactionId);
        return in_array($status, ['completed', 'success', 'paid', 'approved']);
    }

    /**
     * Check if payment is pending
     *
     * @param string $transactionId
     * @return bool
     */
    public function isPaymentPending(string $transactionId): bool
    {
        $status = $this->getPaymentStatus($transactionId);
        return in_array($status, ['pending', 'processing', 'waiting']);
    }

    /**
     * Check if payment is failed
     *
     * @param string $transactionId
     * @return bool
     */
    public function isPaymentFailed(string $transactionId): bool
    {
        $status = $this->getPaymentStatus($transactionId);
        return in_array($status, ['failed', 'error', 'declined', 'cancelled']);
    }

    /**
     * Get error message
     *
     * @param string $errorCode
     * @return string
     */
    public function getErrorMessage(string $errorCode): string
    {
        $errorMessages = $this->config['error_messages'] ?? [];
        return $errorMessages[$errorCode] ?? "Unknown error: {$errorCode}";
    }

    /**
     * Get gateway logo URL
     *
     * @return string
     */
    public function getLogoUrl(): string
    {
        return $this->config['logo_url'] ?? '';
    }

    /**
     * Get gateway documentation URL
     *
     * @return string
     */
    public function getDocumentationUrl(): string
    {
        return $this->config['documentation_url'] ?? '';
    }

    /**
     * Get gateway API endpoint
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        if ($this->isTestMode()) {
            return $this->config['test_api_endpoint'] ?? $this->config['api_endpoint'] ?? '';
        }
        return $this->config['api_endpoint'] ?? '';
    }

    /**
     * Get required fields for payment
     *
     * @return array
     */
    public function getRequiredFields(): array
    {
        return $this->config['required_fields'] ?? ['amount', 'currency', 'description'];
    }

    /**
     * Get optional fields for payment
     *
     * @return array
     */
    public function getOptionalFields(): array
    {
        return $this->config['optional_fields'] ?? ['reference', 'metadata', 'customer_email', 'customer_name'];
    }

    /**
     * Get payment methods supported by gateway
     *
     * @return array
     */
    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    /**
     * Check if payment method is supported
     *
     * @param string $method
     * @return bool
     */
    public function supportsPaymentMethod(string $method): bool
    {
        return in_array($method, $this->paymentMethods);
    }

    /**
     * Get gateway version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Get last error
     *
     * @return array|null
     */
    public function getLastError(): ?array
    {
        return $this->lastError;
    }

    /**
     * Set last error
     *
     * @param string $message
     * @param int $code
     * @param array $errors
     * @return void
     */
    protected function setLastError(string $message, int $code = 0, array $errors = []): void
    {
        $this->lastError = [
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
            'timestamp' => now()->toISOString(),
            'gateway' => $this->getName(),
        ];

        Log::error('Payment Gateway Error', $this->lastError);
    }

    /**
     * Clear last error
     *
     * @return void
     */
    public function clearLastError(): void
    {
        $this->lastError = null;
    }

    /**
     * Make HTTP request
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @return array
     * @throws PaymentGatewayException
     */
    protected function makeRequest(string $method, string $url, array $data = []): array
    {
        try {
            $response = $this->httpClient->{$method}($url, $data);

            if ($response->failed()) {
                throw new PaymentGatewayException(
                    "HTTP request failed with status: {$response->status()}",
                    ['response' => $response->json()]
                );
            }

            return $response->json();

        } catch (\Exception $e) {
            throw new PaymentGatewayException(
                "HTTP request failed: {$e->getMessage()}",
                [],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Log payment activity
     *
     * @param string $action
     * @param array $data
     * @param string $level
     * @return void
     */
    protected function logActivity(string $action, array $data = [], string $level = 'info'): void
    {
        $logData = [
            'gateway' => $this->getName(),
            'action' => $action,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];

        Log::log($level, "Payment Gateway Activity: {$action}", $logData);
    }
}
