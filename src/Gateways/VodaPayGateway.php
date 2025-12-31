<?php

namespace Lisosoft\PaymentGateway\Gateways;

use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VodaPayGateway extends AbstractGateway
{
    /**
     * VodaPay API endpoints
     */
    const API_ENDPOINT_LIVE = 'https://api.vodapay.co.za';
    const API_ENDPOINT_SANDBOX = 'https://sandbox-api.vodapay.co.za';

    /**
     * VodaPay payment statuses
     */
    const STATUS_INITIATED = 'INITIATED';
    const STATUS_PENDING = 'PENDING';
    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_FAILED = 'FAILED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_EXPIRED = 'EXPIRED';

    /**
     * Initialize the VodaPay gateway
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->name = 'vodapay';
        $this->displayName = 'VodaPay';

        // Set supported currencies (VodaPay primarily supports ZAR)
        $this->supportedCurrencies = ['ZAR'];

        // Set supported payment methods
        $this->paymentMethods = [
            'vodapay_wallet',
            'vodacom_mobile_money',
            'vodacom_airtime',
        ];
    }

    /**
     * Get default configuration for VodaPay
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'merchant_id' => '',
            'api_key' => '',
            'api_secret' => '',
            'test_mode' => true,
            'callback_url' => '/payment/callback/vodapay',
            'return_url' => '/payment/success',
            'cancel_url' => '/payment/cancel',
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 100,
            'verify_ssl' => true,
            'minimum_amount' => 1.00,
            'maximum_amount' => 5000.00,
            'currency' => 'ZAR',
            'reference_prefix' => 'VP',
            'logo_url' => 'https://www.vodacom.co.za/images/vodapay-logo.png',
            'documentation_url' => 'https://developer.vodacom.co.za/vodapay',
            'error_messages' => [
                'VP001' => 'Invalid merchant credentials',
                'VP002' => 'Insufficient funds in wallet',
                'VP003' => 'Transaction declined',
                'VP004' => 'Invalid mobile number',
                'VP005' => 'Transaction timeout',
                'VP006' => 'Duplicate transaction',
                'VP007' => 'Amount exceeds limit',
                'VP008' => 'Service temporarily unavailable',
                'VP009' => 'Invalid payment reference',
                'VP010' => 'User cancelled payment',
                'VP011' => 'Session expired',
                'VP012' => 'Invalid callback URL',
                'VP013' => 'Payment method not supported',
                'VP014' => 'Account verification failed',
                'VP015' => 'Technical error',
            ],
        ];
    }

    /**
     * Get VodaPay API endpoint based on test mode
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        $baseUrl = $this->isTestMode() ? self::API_ENDPOINT_SANDBOX : self::API_ENDPOINT_LIVE;
        return $baseUrl . '/v1/payments';
    }

    /**
     * Process payment initialization for VodaPay
     *
     * @param array $paymentData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processInitializePayment(array $paymentData): array
    {
        try {
            // Validate payment data
            $this->validatePaymentData($paymentData);

            // Generate unique reference
            $reference = $this->generateReference($paymentData);

            // Prepare API request payload
            $payload = $this->preparePaymentPayload($reference, $paymentData);

            // Make API request to VodaPay
            $response = $this->makeRequest('POST', '/initiate', $payload);

            if (!$response['success']) {
                throw new PaymentGatewayException(
                    'VodaPay payment initialization failed: ' . ($response['message'] ?? 'Unknown error'),
                    ['response' => $response, 'payload' => $payload]
                );
            }

            // Extract payment URL and transaction ID
            $paymentUrl = $response['data']['payment_url'] ?? null;
            $transactionId = $response['data']['transaction_id'] ?? $reference;

            // Return payment initialization response
            return [
                'success' => true,
                'gateway' => $this->name,
                'reference' => $reference,
                'transaction_id' => $transactionId,
                'payment_url' => $paymentUrl,
                'redirect_required' => true,
                'redirect_method' => 'GET',
                'qr_code_url' => $response['data']['qr_code_url'] ?? null,
                'deep_link' => $response['data']['deep_link'] ?? null,
                'expires_at' => $response['data']['expires_at'] ?? Carbon::now()->addMinutes(15)->toISOString(),
                'message' => 'VodaPay payment initialized successfully. Redirect to complete payment.',
                'data' => $response['data'],
            ];

        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), 'VODAPAY_INIT_ERROR');
            throw new PaymentGatewayException(
                'Failed to initialize VodaPay payment: ' . $e->getMessage(),
                ['payment_data' => $paymentData],
                0,
                $e
            );
        }
    }

    /**
     * Process payment verification for VodaPay
     *
     * @param string $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processVerifyPayment(string $transactionId): array
    {
        try {
            // Make API request to check payment status
            $response = $this->makeRequest('GET', '/status/' . $transactionId);

            if (!$response['success']) {
                throw new PaymentGatewayException(
                    'VodaPay payment verification failed: ' . ($response['message'] ?? 'Unknown error'),
                    ['response' => $response, 'transaction_id' => $transactionId]
                );
            }

            $status = $response['data']['status'] ?? self::STATUS_PENDING;
            $amount = $response['data']['amount'] ?? null;
            $currency = $response['data']['currency'] ?? 'ZAR';

            return [
                'success' => true,
                'gateway' => $this->name,
                'transaction_id' => $transactionId,
                'status' => $status,
                'amount' => $amount,
                'currency' => $currency,
                'verified_at' => now()->toISOString(),
                'message' => $this->getStatusMessage($status),
                'data' => $response['data'],
            ];

        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), 'VODAPAY_VERIFY_ERROR');
            throw new PaymentGatewayException(
                'Failed to verify VodaPay payment: ' . $e->getMessage(),
                ['transaction_id' => $transactionId],
                0,
                $e
            );
        }
    }

    /**
     * Process callback data for VodaPay
     *
     * @param array $callbackData
     * @return array
     */
    protected function processCallbackData(array $callbackData): array
    {
        // Validate callback signature
        if (!$this->validateCallbackSignature($callbackData)) {
            return [
                'success' => false,
                'message' => 'Invalid callback signature',
                'error_code' => 'VP012',
            ];
        }

        $transactionId = $callbackData['transaction_id'] ?? null;
        $status = $callbackData['status'] ?? null;
        $amount = $callbackData['amount'] ?? null;
        $currency = $callbackData['currency'] ?? 'ZAR';
        $reference = $callbackData['reference'] ?? null;

        if (!$transactionId || !$status) {
            return [
                'success' => false,
                'message' => 'Missing required callback parameters',
                'error_code' => 'VP009',
            ];
        }

        // Map VodaPay status to our internal status
        $internalStatus = $this->mapVodaPayStatus($status);

        return [
            'success' => $internalStatus === self::STATUS_SUCCESS,
            'gateway' => $this->name,
            'reference' => $reference,
            'transaction_id' => $transactionId,
            'status' => $internalStatus,
            'amount' => $amount,
            'currency' => $currency,
            'callback_received_at' => now()->toISOString(),
            'message' => $this->getStatusMessage($status),
            'data' => $callbackData,
        ];
    }

    /**
     * Process refund for VodaPay payment
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processRefundPayment(string $transactionId, ?float $amount = null): array
    {
        try {
            // Prepare refund payload
            $payload = [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'reason' => 'Customer request',
                'reference' => 'REF-' . $transactionId . '-' . Str::random(6),
            ];

            // Make API request to process refund
            $response = $this->makeRequest('POST', '/refund', $payload);

            if (!$response['success']) {
                throw new PaymentGatewayException(
                    'VodaPay refund failed: ' . ($response['message'] ?? 'Unknown error'),
                    ['response' => $response, 'payload' => $payload]
                );
            }

            return [
                'success' => true,
                'gateway' => $this->name,
                'transaction_id' => $transactionId,
                'refund_id' => $response['data']['refund_id'] ?? null,
                'refund_reference' => $payload['reference'],
                'amount' => $amount,
                'status' => $response['data']['status'] ?? 'pending',
                'message' => 'Refund processed successfully',
                'estimated_completion' => Carbon::now()->addHours(24)->toISOString(),
                'data' => $response['data'],
            ];

        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), 'VODAPAY_REFUND_ERROR');
            throw new PaymentGatewayException(
                'Failed to process VodaPay refund: ' . $e->getMessage(),
                ['transaction_id' => $transactionId, 'amount' => $amount],
                0,
                $e
            );
        }
    }

    /**
     * Prepare payment payload for VodaPay API
     *
     * @param string $reference
     * @param array $paymentData
     * @return array
     */
    protected function preparePaymentPayload(string $reference, array $paymentData): array
    {
        $customer = $paymentData['customer'] ?? [];
        $metadata = $paymentData['metadata'] ?? [];

        return [
            'merchant_id' => $this->config['merchant_id'],
            'reference' => $reference,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? $this->config['currency'],
            'description' => $paymentData['description'] ?? 'Payment',
            'customer' => [
                'msisdn' => $customer['phone'] ?? null,
                'email' => $customer['email'] ?? null,
                'name' => $customer['name'] ?? null,
            ],
            'callback_url' => $this->config['callback_url'],
            'return_url' => $paymentData['return_url'] ?? $this->config['return_url'],
            'cancel_url' => $paymentData['cancel_url'] ?? $this->config['cancel_url'],
            'metadata' => $metadata,
            'payment_method' => $paymentData['payment_method'] ?? 'vodapay_wallet',
            'expiry_minutes' => 15,
        ];
    }

    /**
     * Validate callback signature for VodaPay
     *
     * @param array $callbackData
     * @return bool
     */
    protected function validateCallbackSignature(array $callbackData): bool
    {
        $signature = $callbackData['signature'] ?? null;
        $timestamp = $callbackData['timestamp'] ?? null;
        $transactionId = $callbackData['transaction_id'] ?? null;

        if (!$signature || !$timestamp || !$transactionId) {
            return false;
        }

        // Generate expected signature
        $apiSecret = $this->config['api_secret'] ?? '';
        $dataToSign = $transactionId . $timestamp . $apiSecret;
        $expectedSignature = hash_hmac('sha256', $dataToSign, $apiSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Map VodaPay status to internal status
     *
     * @param string $vodaPayStatus
     * @return string
     */
    protected function mapVodaPayStatus(string $vodaPayStatus): string
    {
        $statusMap = [
            'INITIATED' => self::STATUS_INITIATED,
            'PENDING' => self::STATUS_PENDING,
            'SUCCESS' => self::STATUS_SUCCESS,
            'FAILED' => self::STATUS_FAILED,
            'CANCELLED' => self::STATUS_CANCELLED,
            'EXPIRED' => self::STATUS_EXPIRED,
        ];

        return $statusMap[$vodaPayStatus] ?? self::STATUS_PENDING;
    }

    /**
     * Get status message for VodaPay status
     *
     * @param string $status
     * @return string
     */
    protected function getStatusMessage(string $status): string
    {
        $messages = [
            self::STATUS_INITIATED => 'Payment initiated',
            self::STATUS_PENDING => 'Payment is pending',
            self::STATUS_SUCCESS => 'Payment completed successfully',
            self::STATUS_FAILED => 'Payment failed',
            self::STATUS_CANCELLED => 'Payment was cancelled',
            self::STATUS_EXPIRED => 'Payment session expired',
        ];

        return $messages[$status] ?? 'Unknown payment status';
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
            $result = $this->verifyPayment($transactionId);
            return $result['status'] ?? self::STATUS_PENDING;
        } catch (\Exception $e) {
            return self::STATUS_PENDING;
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
        return $status === self::STATUS_SUCCESS;
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
        return in_array($status, [self::STATUS_INITIATED, self::STATUS_PENDING]);
    }

    /**
     * Check if payment failed
     *
     * @param string $transactionId
     * @return bool
     */
    public function isPaymentFailed(string $transactionId): bool
    {
        $status = $this->getPaymentStatus($transactionId);
        return in_array($status, [self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_EXPIRED]);
    }

    /**
     * Get error message for error code
     *
     * @param string $errorCode
     * @return string
     */
    public function getErrorMessage(string $errorCode): string
    {
        $errorMessages = $this->config['error_messages'] ?? [];
        return $errorMessages[$errorCode] ?? 'Unknown error occurred';
    }

    /**
     * Get required fields for payment initialization
     *
     * @return array
     */
    public function getRequiredFields(): array
    {
        return [
            'amount',
            'currency',
            'customer.phone', // MSISDN is required for VodaPay
        ];
    }

    /**
     * Get optional fields for payment initialization
     *
     * @return array
     */
    public function getOptionalFields(): array
    {
        return [
            'description',
            'customer.email',
            'customer.name',
            'metadata',
            'return_url',
            'cancel_url',
            'payment_method',
        ];
    }

    /**
     * Create subscription for recurring payments
     *
     * @param array $subscriptionData
     * @return array
     * @throws PaymentGatewayException
     */
    public function createSubscription(array $subscriptionData): array
    {
        try {
            // Prepare subscription payload
            $reference = $this->generateReference($subscriptionData);
            $payload = [
                'merchant_id' => $this->config['merchant_id'],
                'reference' => $reference,
                'amount' => $subscriptionData['amount'],
                'currency' => $subscriptionData['currency'] ?? $this->config['currency'],
                'description' => $subscriptionData['description'] ?? 'Subscription',
                'customer' => [
                    'msisdn' => $subscriptionData['customer']['phone'] ?? null,
                    'email' => $subscriptionData['customer']['email'] ?? null,
                    'name' => $subscriptionData['customer']['name'] ?? null,
                ],
                'frequency' => $subscriptionData['frequency'] ?? 'monthly',
                'start_date' => $subscriptionData['start_date'] ?? Carbon::now()->addDay()->toDateString(),
                'end_date' => $subscriptionData['end_date'] ?? null,
                'cycles' => $subscriptionData['cycles'] ?? null,
                'metadata' => $subscriptionData['metadata'] ?? [],
            ];

            // Make API request to create subscription
            $response = $this->makeRequest('POST', '/subscriptions', $payload);

            if (!$response['success']) {
                throw new PaymentGatewayException(
                    'VodaPay subscription creation failed: ' . ($response['message'] ?? 'Unknown error'),
                    ['response' => $response, 'payload' => $payload]
                );
            }

            return [
                'success' => true,
                'gateway' => $this->name,
                'reference' => $reference,
                'subscription_id' => $response['data']['subscription_id'] ?? null,
                'status' => $response['data']['status'] ?? 'active',
                'message' => 'Subscription created successfully',
                'data' => $response['data'],
            ];

        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), 'VODAPAY_SUBSCRIPTION_ERROR');
            throw new PaymentGatewayException(
                'Failed to create VodaPay subscription: ' . $e->getMessage(),
                ['subscription_data' => $subscriptionData],
                0,
                $e
            );
        }
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
        try {
            // Make API request to cancel subscription
            $response = $this->makeRequest('POST', '/subscriptions/' . $subscriptionId . '/cancel');

            if (!$response['success']) {
                throw new PaymentGatewayException(
                    'VodaPay subscription cancellation failed: ' . ($response['message'] ?? 'Unknown error'),
                    ['response' => $response, 'subscription_id' => $subscriptionId]
                );
            }

            return [
                'success' => true,
                'gateway' => $this->name,
                'subscription_id' => $subscriptionId,
                'status' => 'cancelled',
                'cancelled_at' => now()->toISOString(),
                'message' => 'Subscription cancelled successfully',
                'data' => $response['data'],
            ];

        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), 'VODAPAY_CANCEL_SUBSCRIPTION_ERROR');
            throw new PaymentGatewayException(
                'Failed to cancel VodaPay subscription: ' . $e->getMessage(),
                ['subscription_id' => $subscriptionId],
                0,
                $e
            );
        }
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
        try {
            // Make API request to get subscription details
            $response = $this->makeRequest('GET', '/subscriptions/' . $subscriptionId);

            if (!$response['success']) {
                throw new PaymentGatewayException(
                    'Failed to get VodaPay subscription: ' . ($response['message'] ?? 'Unknown error'),
                    ['response' => $response, 'subscription_id' => $subscriptionId]
                );
            }

            return [
                'success' => true,
                'gateway' => $this->name,
                'subscription_id' => $subscriptionId,
                'status' => $response['data']['status'] ?? 'unknown',
                'details' => $response['data'],
                'message' => 'Subscription details retrieved successfully',
                'data' => $response['data'],
            ];

        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), 'VODAPAY_GET_SUBSCRIPTION_ERROR');
            throw new PaymentGatewayException(
                'Failed to get VodaPay subscription: ' . $e->getMessage(),
                ['subscription_id' => $subscriptionId],
                0,
                $e
            );
        }
    }

    /**
     * Update subscription
     *
     * @param string $subscriptionId
     * @param array $subscriptionData
     * @return array
     * @throws PaymentGatewayException
     */
    public function updateSubscription(string $subscriptionId, array $subscriptionData): array
    {
        try {
            // Make API request to update subscription
            $response = $this->makeRequest('PUT', '/subscriptions/' . $subscriptionId, $subscriptionData);

            if (!$response['success']) {
                throw new PaymentGatewayException(
                    'VodaPay subscription update failed: ' . ($response['message'] ?? 'Unknown error'),
                    ['response' => $response, 'subscription_id' => $subscriptionId, 'subscription_data' => $subscriptionData]
                );
            }

            return [
                'success' => true,
                'gateway' => $this->name,
                'subscription_id' => $subscriptionId,
                'status' => $response['data']['status'] ?? 'active',
                'message' => 'Subscription updated successfully',
                'data' => $response['data'],
            ];

        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), 'VODAPAY_UPDATE_SUBSCRIPTION_ERROR');
            throw new PaymentGatewayException(
                'Failed to update VodaPay subscription: ' . $e->getMessage(),
                ['subscription_id' => $subscriptionId, 'subscription_data' => $subscriptionData],
                0,
                $e
            );
        }
    }

    /**
     * Get transaction history
     *
     * @param array $filters
     * @return array
     */
    public function getTransactionHistory(array $filters = []): array
    {
        try {
            // Prepare query parameters
            $queryParams = [];
            if (isset($filters['start_date'])) {
                $queryParams['start_date'] = $filters['start_date'];
            }
            if (isset($filters['end_date'])) {
                $queryParams['end_date'] = $filters['end_date'];
            }
            if (isset($filters['status'])) {
                $queryParams['status'] = $filters['status'];
            }
            if (isset($filters['page'])) {
                $queryParams['page'] = $filters['page'];
            }
            if (isset($filters['per_page'])) {
                $queryParams['per_page'] = $filters['per_page'];
            }

            // Make API request to get transaction history
            $response = $this->makeRequest('GET', '/transactions', $queryParams);

            if (!$response['success']) {
                return [
                    'transactions' => [],
                    'total' => 0,
                    'page' => $filters['page'] ?? 1,
                    'per_page' => $filters['per_page'] ?? 20,
                    'has_more' => false,
                    'error' => $response['message'] ?? 'Failed to fetch transactions',
                ];
            }

            return [
                'transactions' => $response['data']['transactions'] ?? [],
                'total' => $response['data']['total'] ?? 0,
                'page' => $response['data']['page'] ?? ($filters['page'] ?? 1),
                'per_page' => $response['data']['per_page'] ?? ($filters['per_page'] ?? 20),
                'has_more' => $response['data']['has_more'] ?? false,
                'data' => $response['data'],
            ];

        } catch (\Exception $e) {
            return [
                'transactions' => [],
                'total' => 0,
                'page' => $filters['page'] ?? 1,
                'per_page' => $filters['per_page'] ?? 20,
                'has_more' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make HTTP request to VodaPay API
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @return array
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->getApiEndpoint() . $endpoint;
        $headers = $this->getDefaultHeaders();

        // Add VodaPay specific headers
        $headers['X-Merchant-ID'] = $this->config['merchant_id'];
        $headers['X-API-Key'] = $this->config['api_key'];
        $headers['X-Timestamp'] = time();

        // Generate signature for request
        $signatureData = $method . $endpoint . json_encode($data) . $headers['X-Timestamp'];
        $headers['X-Signature'] = hash_hmac('sha256', $signatureData, $this->config['api_secret']);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($this->config['timeout'] ?? 30)
                ->{$method}($url, $data);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'status_code' => $response->status(),
                    'message' => 'API request failed',
                    'error' => $response->body(),
                ];
            }

            $responseData = $response->json();

            return [
                'success' => true,
                'status_code' => $response->status(),
                'message' => $responseData['message'] ?? 'Request successful',
                'data' => $responseData['data'] ?? $responseData,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Request failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get default headers for VodaPay API
     *
     * @return array
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'Lisosoft-Payment-Gateway/1.0',
        ];
    }

    /**
     * Log activity for VodaPay gateway
     *
     * @param string $action
     * @param array $data
     * @return void
     */
    protected function logActivity(string $action, array $data = []): void
    {
        // In a real implementation, this would log to database or file
        $logData = array_merge([
            'gateway' => $this->name,
            'action' => $action,
            'timestamp' => now()->toISOString(),
            'merchant_id' => $this->config['merchant_id'] ?? null,
        ], $data);

        // For now, we'll just return without actual logging
        // You would implement actual logging here
    }
}
