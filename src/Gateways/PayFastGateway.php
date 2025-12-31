<?php

namespace Lisosoft\PaymentGateway\Gateways;

use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayFastGateway extends AbstractGateway
{
    /**
     * PayFast API endpoints
     */
    const API_ENDPOINT_LIVE = 'https://www.payfast.co.za';
    const API_ENDPOINT_SANDBOX = 'https://sandbox.payfast.co.za';

    /**
     * PayFast payment statuses
     */
    const STATUS_COMPLETE = 'COMPLETE';
    const STATUS_PENDING = 'PENDING';
    const STATUS_FAILED = 'FAILED';
    const STATUS_CANCELLED = 'CANCELLED';

    /**
     * Initialize the PayFast gateway
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->name = 'payfast';
        $this->displayName = 'PayFast';

        // Set supported currencies (PayFast primarily supports ZAR)
        $this->supportedCurrencies = ['ZAR'];

        // Set supported payment methods
        $this->paymentMethods = [
            'credit_card',
            'debit_card',
            'eft',
            'instant_eft',
            'masterpass',
            'mobicred',
            'scan_to_pay',
        ];
    }

    /**
     * Get default configuration for PayFast
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'merchant_id' => '',
            'merchant_key' => '',
            'passphrase' => '',
            'test_mode' => true,
            'return_url' => '/payment/success',
            'cancel_url' => '/payment/cancel',
            'notify_url' => '/payment/webhook/payfast',
            'payment_url' => '/eng/process',
            'validate_signature' => true,
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 100,
            'verify_ssl' => true,
            'minimum_amount' => 1.00,
            'maximum_amount' => 100000.00,
            'currency' => 'ZAR',
            'reference_prefix' => 'PF',
            'logo_url' => 'https://www.payfast.co.za/images/logo.png',
            'documentation_url' => 'https://developers.payfast.co.za/documentation',
            'error_messages' => [
                '001' => 'Payment cancelled by user',
                '002' => 'Payment declined',
                '003' => 'Transaction expired',
                '004' => 'Insufficient funds',
                '005' => 'Invalid card details',
                '006' => 'Technical error',
                '007' => 'Duplicate transaction',
                '008' => 'Invalid merchant configuration',
                '009' => 'Invalid payment data',
                '010' => 'Payment method not supported',
            ],
        ];
    }

    /**
     * Get PayFast API endpoint based on test mode
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        $baseUrl = $this->isTestMode() ? self::API_ENDPOINT_SANDBOX : self::API_ENDPOINT_LIVE;
        return rtrim($baseUrl, '/');
    }

    /**
     * Get payment form URL
     *
     * @return string
     */
    public function getPaymentUrl(): string
    {
        return $this->getApiEndpoint() . $this->config['payment_url'];
    }

    /**
     * Get default HTTP headers for PayFast
     *
     * @return array
     */
    protected function getDefaultHeaders(): array
    {
        $headers = parent::getDefaultHeaders();

        // Add PayFast specific headers
        $headers['merchant-id'] = $this->config['merchant_id'];
        $headers['version'] = 'v1';

        return $headers;
    }

    /**
     * Process payment initialization for PayFast
     *
     * @param array $paymentData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processInitializePayment(array $paymentData): array
    {
        // Validate required configuration
        $this->validateConfiguration();

        // Prepare PayFast payment data
        $payfastData = $this->preparePaymentData($paymentData);

        // Generate signature
        $payfastData['signature'] = $this->generateSignature($payfastData);

        // Log payment initialization
        $this->logActivity('payment_initialized', [
            'payment_data' => $paymentData,
            'payfast_data' => $payfastData,
        ]);

        return [
            'success' => true,
            'gateway' => $this->getName(),
            'transaction_id' => $payfastData['m_payment_id'],
            'payment_url' => $this->getPaymentUrl(),
            'payment_data' => $payfastData,
            'method' => 'POST', // PayFast uses POST form submission
            'redirect_required' => true,
            'message' => 'Payment initialized successfully',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Prepare payment data for PayFast
     *
     * @param array $paymentData
     * @return array
     */
    protected function preparePaymentData(array $paymentData): array
    {
        // Generate unique payment ID if not provided
        $paymentId = $paymentData['reference'] ?? $this->generateReference($paymentData);

        // Base PayFast parameters
        $payfastData = [
            'merchant_id' => $this->config['merchant_id'],
            'merchant_key' => $this->config['merchant_key'],
            'return_url' => $this->buildUrl($this->config['return_url'], $paymentId),
            'cancel_url' => $this->buildUrl($this->config['cancel_url'], $paymentId),
            'notify_url' => $this->buildUrl($this->config['notify_url'], $paymentId),

            // Payment details
            'm_payment_id' => $paymentId,
            'amount' => number_format($paymentData['amount'], 2, '.', ''),
            'item_name' => $paymentData['description'] ?? 'Payment',
            'item_description' => $paymentData['item_description'] ?? $paymentData['description'] ?? 'Payment',

            // Customer details
            'name_first' => $paymentData['customer']['name_first'] ?? $paymentData['customer']['name'] ?? '',
            'name_last' => $paymentData['customer']['name_last'] ?? '',
            'email_address' => $paymentData['customer']['email'] ?? '',
            'cell_number' => $paymentData['customer']['phone'] ?? '',
        ];

        // Add optional parameters if provided
        $optionalParams = [
            'email_confirmation' => $paymentData['email_confirmation'] ?? 1,
            'confirmation_address' => $paymentData['confirmation_email'] ?? '',
            'payment_method' => $paymentData['payment_method'] ?? '',
            'subscription_type' => $paymentData['subscription_type'] ?? 0,
            'billing_date' => $paymentData['billing_date'] ?? '',
            'recurring_amount' => $paymentData['recurring_amount'] ?? '',
            'frequency' => $paymentData['frequency'] ?? '',
            'cycles' => $paymentData['cycles'] ?? 0,
            'custom_int1' => $paymentData['custom_int1'] ?? '',
            'custom_int2' => $paymentData['custom_int2'] ?? '',
            'custom_int3' => $paymentData['custom_int3'] ?? '',
            'custom_int4' => $paymentData['custom_int4'] ?? '',
            'custom_int5' => $paymentData['custom_int5'] ?? '',
            'custom_str1' => $paymentData['custom_str1'] ?? '',
            'custom_str2' => $paymentData['custom_str2'] ?? '',
            'custom_str3' => $paymentData['custom_str3'] ?? '',
            'custom_str4' => $paymentData['custom_str4'] ?? '',
            'custom_str5' => $paymentData['custom_str5'] ?? '',
        ];

        // Filter out empty optional parameters
        foreach ($optionalParams as $key => $value) {
            if (!empty($value)) {
                $payfastData[$key] = $value;
            }
        }

        // Add passphrase if configured
        if (!empty($this->config['passphrase'])) {
            $payfastData['passphrase'] = $this->config['passphrase'];
        }

        return $payfastData;
    }

    /**
     * Generate PayFast signature
     *
     * @param array $data
     * @return string
     */
    protected function generateSignature(array $data): string
    {
        // Remove signature and empty values
        unset($data['signature']);
        $data = array_filter($data, function ($value) {
            return $value !== '' && $value !== null;
        });

        // Sort data alphabetically by key
        ksort($data);

        // Build parameter string
        $paramString = '';
        foreach ($data as $key => $value) {
            $paramString .= $key . '=' . urlencode(trim($value)) . '&';
        }

        // Remove last '&'
        $paramString = rtrim($paramString, '&');

        // Add passphrase if configured
        if (!empty($this->config['passphrase'])) {
            $paramString .= '&passphrase=' . urlencode(trim($this->config['passphrase']));
        }

        // Generate MD5 hash
        return md5($paramString);
    }

    /**
     * Process payment verification for PayFast
     *
     * @param string $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processVerifyPayment(string $transactionId): array
    {
        // Validate configuration
        $this->validateConfiguration();

        // PayFast doesn't have a direct verification API for one-time payments
        // We need to check the transaction status through ITN (Instant Transaction Notification)
        // For now, we'll return a pending status and rely on webhooks

        $this->logActivity('payment_verification_attempt', [
            'transaction_id' => $transactionId,
        ]);

        return [
            'success' => true,
            'gateway' => $this->getName(),
            'transaction_id' => $transactionId,
            'status' => 'pending',
            'message' => 'Payment verification initiated. Status will be updated via webhook.',
            'timestamp' => now()->toISOString(),
            'note' => 'PayFast requires ITN (webhook) for final payment status',
        ];
    }

    /**
     * Process callback data from PayFast ITN
     *
     * @param array $callbackData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processCallbackData(array $callbackData): array
    {
        // Validate callback signature
        $this->validateCallbackSignature($callbackData);

        // Extract transaction details
        $transactionId = $callbackData['m_payment_id'] ?? '';
        $paymentStatus = $callbackData['payment_status'] ?? '';
        $amountGross = $callbackData['amount_gross'] ?? 0;
        $amountFee = $callbackData['amount_fee'] ?? 0;
        $amountNet = $callbackData['amount_net'] ?? 0;

        // Map PayFast status to our status
        $status = $this->mapPaymentStatus($paymentStatus);

        // Prepare response
        $response = [
            'success' => $status === 'completed',
            'gateway' => $this->getName(),
            'transaction_id' => $transactionId,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'amount_gross' => (float) $amountGross,
            'amount_fee' => (float) $amountFee,
            'amount_net' => (float) $amountNet,
            'raw_data' => $callbackData,
            'timestamp' => now()->toISOString(),
        ];

        // Add customer details if available
        if (isset($callbackData['name_first']) || isset($callbackData['name_last'])) {
            $response['customer'] = [
                'first_name' => $callbackData['name_first'] ?? '',
                'last_name' => $callbackData['name_last'] ?? '',
                'email' => $callbackData['email_address'] ?? '',
            ];
        }

        // Log callback processing
        $this->logActivity('callback_processed', $response);

        return $response;
    }

    /**
     * Validate callback signature for PayFast ITN
     *
     * @param array $callbackData
     * @return bool
     * @throws PaymentGatewayException
     */
    protected function validateCallbackSignature(array $callbackData): bool
    {
        if (!($this->config['validate_signature'] ?? true)) {
            return true;
        }

        // Get signature from callback data
        $receivedSignature = $callbackData['signature'] ?? '';

        if (empty($receivedSignature)) {
            throw PaymentGatewayException::invalidSignature(
                $this->getName(),
                'Missing signature in callback data'
            );
        }

        // Generate expected signature
        $expectedSignature = $this->generateSignature($callbackData);

        // Compare signatures
        if (!hash_equals($expectedSignature, $receivedSignature)) {
            throw PaymentGatewayException::invalidSignature(
                $this->getName(),
                'Signature verification failed'
            );
        }

        return true;
    }

    /**
     * Process refund payment for PayFast
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processRefundPayment(string $transactionId, ?float $amount = null): array
    {
        // Validate configuration
        $this->validateConfiguration();

        // PayFast refunds are typically manual through their dashboard
        // This is a placeholder for potential API integration

        $this->logActivity('refund_requested', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
        ]);

        return [
            'success' => false,
            'gateway' => $this->getName(),
            'transaction_id' => $transactionId,
            'status' => 'manual_refund_required',
            'message' => 'Refunds for PayFast must be processed manually through the PayFast dashboard',
            'timestamp' => now()->toISOString(),
            'note' => 'Contact PayFast support or use their merchant dashboard to process refunds',
        ];
    }

    /**
     * Map PayFast payment status to our status
     *
     * @param string $payfastStatus
     * @return string
     */
    protected function mapPaymentStatus(string $payfastStatus): string
    {
        $statusMap = [
            'COMPLETE' => 'completed',
            'PENDING' => 'pending',
            'FAILED' => 'failed',
            'CANCELLED' => 'cancelled',
        ];

        return $statusMap[strtoupper($payfastStatus)] ?? 'unknown';
    }

    /**
     * Build URL with transaction ID parameter
     *
     * @param string $url
     * @param string $transactionId
     * @return string
     */
    protected function buildUrl(string $url, string $transactionId): string
    {
        // If URL is absolute, return as is
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        // Add transaction ID as query parameter
        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . 'transaction_id=' . urlencode($transactionId);
    }

    /**
     * Validate PayFast configuration
     *
     * @return void
     * @throws PaymentGatewayException
     */
    protected function validateConfiguration(): void
    {
        $missingConfig = [];

        // Check required configuration
        $requiredConfig = ['merchant_id', 'merchant_key'];
        foreach ($requiredConfig as $configKey) {
            if (empty($this->config[$configKey])) {
                $missingConfig[] = $configKey;
            }
        }

        if (!empty($missingConfig)) {
            throw PaymentGatewayException::invalidConfiguration(
                $this->getName(),
                $missingConfig
            );
        }

        // Check if gateway is enabled
        if (!$this->isAvailable()) {
            throw PaymentGatewayException::gatewayNotAvailable($this->getName());
        }
    }

    /**
     * Create subscription/recurring payment
     *
     * @param array $subscriptionData
     * @return array
     * @throws PaymentGatewayException
     */
    public function createSubscription(array $subscriptionData): array
    {
        // Validate configuration
        $this->validateConfiguration();

        // Prepare subscription data
        $paymentData = array_merge($subscriptionData, [
            'subscription_type' => 1, // 1 for subscription
            'frequency' => $subscriptionData['frequency'] ?? 3, // 3 = monthly
            'cycles' => $subscriptionData['cycles'] ?? 0, // 0 = infinite
            'billing_date' => $subscriptionData['billing_date'] ?? date('Y-m-d'),
        ]);

        // Initialize subscription payment
        return $this->initializePayment($paymentData);
    }

    /**
     * Get subscription details
     *
     * @param string $subscriptionId
     * @return array
     * @throws PaymentGatewayException
     */
    public function getSubscription(string $subscriptionId): array
    {
        // PayFast doesn't have a subscription query API
        // This would need to be tracked in your own database

        throw new PaymentGatewayException(
            'Subscription query is not available via PayFast API. Track subscriptions in your application database.',
            ['subscription_id' => $subscriptionId]
        );
    }

    /**
     * Cancel subscription
     *
     * @param string $subscriptionId
     * @return array
     * @throws PaymentGatewayException
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        // PayFast subscriptions can be cancelled via their dashboard
        // This is a placeholder for potential API integration

        $this->logActivity('subscription_cancellation_requested', [
            'subscription_id' => $subscriptionId,
        ]);

        return [
            'success' => false,
            'gateway' => $this->getName(),
            'subscription_id' => $subscriptionId,
            'status' => 'manual_cancellation_required',
            'message' => 'Subscription cancellations must be processed manually through the PayFast dashboard',
            'timestamp' => now()->toISOString(),
            'note' => 'Contact PayFast support or use their merchant dashboard to cancel subscriptions',
        ];
    }

    /**
     * Get transaction history
     *
     * @param array $filters
     * @return array
     * @throws PaymentGatewayException
     */
    public function getTransactionHistory(array $filters = []): array
    {
        // PayFast doesn't have a transaction history API
        // This would need to be tracked in your own database

        throw new PaymentGatewayException(
            'Transaction history is not available via PayFast API. Track transactions in your application database.',
            ['filters' => $filters]
        );
    }

    /**
     * Get required fields for PayFast payment
     *
     * @return array
     */
    public function getRequiredFields(): array
    {
        return [
            'amount',
            'description',
            'customer.email',
        ];
    }

    /**
     * Get optional fields for PayFast payment
     *
     * @return array
     */
    public function getOptionalFields(): array
    {
        return [
            'reference',
            'customer.name',
            'customer.name_first',
            'customer.name_last',
            'customer.phone',
            'item_description',
            'payment_method',
            'subscription_type',
            'billing_date',
            'recurring_amount',
            'frequency',
            'cycles',
            'custom_int1',
            'custom_int2',
            'custom_int3',
            'custom_int4',
            'custom_int5',
            'custom_str1',
            'custom_str2',
            'custom_str3',
            'custom_str4',
            'custom_str5',
        ];
    }
}
