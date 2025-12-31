<?php

namespace Lisosoft\PaymentGateway\Gateways;

use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SnapScanGateway extends AbstractGateway
{
    /**
     * SnapScan API endpoints
     */
    const API_ENDPOINT_LIVE = 'https://pos.snapscan.io';
    const API_ENDPOINT_SANDBOX = 'https://pos-staging.snapscan.io';

    /**
     * SnapScan payment statuses
     */
    const STATUS_INITIATED = 'INITIATED';
    const STATUS_PENDING = 'PENDING';
    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_FAILED = 'FAILED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_EXPIRED = 'EXPIRED';

    /**
     * Initialize the SnapScan gateway
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->name = 'snapscan';
        $this->displayName = 'SnapScan';

        // Set supported currencies (SnapScan primarily supports ZAR)
        $this->supportedCurrencies = ['ZAR'];

        // Set supported payment methods
        $this->paymentMethods = [
            'snapscan_qr',
            'card',
            'bank_transfer',
            'snapscan_wallet',
        ];
    }

    /**
     * Get default configuration for SnapScan
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
            'callback_url' => '/payment/callback/snapscan',
            'return_url' => '/payment/success',
            'cancel_url' => '/payment/cancel',
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 100,
            'verify_ssl' => true,
            'minimum_amount' => 1.00,
            'maximum_amount' => 10000.00,
            'currency' => 'ZAR',
            'reference_prefix' => 'SS',
            'logo_url' => 'https://www.snapscan.co.za/images/logo.png',
            'documentation_url' => 'https://developer.snapscan.co.za',
            'error_messages' => [
                'SS001' => 'Invalid merchant credentials',
                'SS002' => 'Insufficient funds',
                'SS003' => 'Transaction declined',
                'SS004' => 'Invalid QR code',
                'SS005' => 'Transaction timeout',
                'SS006' => 'Duplicate transaction',
                'SS007' => 'Amount exceeds limit',
                'SS008' => 'Service temporarily unavailable',
                'SS009' => 'Invalid payment reference',
                'SS010' => 'User cancelled payment',
                'SS011' => 'Session expired',
                'SS012' => 'Invalid callback URL',
                'SS013' => 'Payment method not supported',
                'SS014' => 'QR code generation failed',
                'SS015' => 'Technical error',
            ],
        ];
    }

    /**
     * Get SnapScan API endpoint based on test mode
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        $baseUrl = $this->isTestMode() ? self::API_ENDPOINT_SANDBOX : self::API_ENDPOINT_LIVE;
        return $baseUrl . '/merchant/api/v1';
    }

    /**
     * Process payment initialization for SnapScan
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

            // Make API request to SnapScan
            $response = $this->makeRequest('POST', '/payments', $payload);

            if (!$response['success']) {
                throw new PaymentGatewayException(
                    'SnapScan payment initialization failed: ' . ($response['message'] ?? 'Unknown error'),
                    ['response' => $response, 'payload' => $payload]
                );
            }

            // Extract QR code URL and transaction ID
            $qrCodeUrl = $response['data']['qr_code_url'] ?? null;
            $transactionId = $response['data']['id'] ?? $reference;
            $deepLink = $response['data']['deep_link'] ?? null;

            // Return payment initialization response
            return [
                'success' => true,
                'gateway' => $this->name,
                'reference' => $reference,
                'transaction_id' => $transactionId,
                'payment_url' => $qrCodeUrl,
                'qr_code_url' => $qrCodeUrl,
                'qr_code_data' => $response['data']['qr_code_data'] ?? null,
                'deep_link' => $deepLink,
                'redirect_required' => false, // QR code doesn't require redirect
                'expires_at' => $response['data']['expires_at'] ?? Carbon::now()->addMinutes(10)->toISOString(),
                'message' => 'SnapScan payment initialized successfully. Scan QR code to complete payment.',
                'data' => $response['data'],
            ];

        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), 'SNAPSCAN_INIT_ERROR');
            throw new PaymentGatewayException(
                'Failed to initialize SnapScan payment: ' . $e->getMessage(),
                ['payment_data' => $paymentData],
                0,
                $e
            );
        }
    }

    /**
     * Process payment verification for SnapScan
     *
     * @param string $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processVerifyPayment(string $transactionId): array
    {
        try {
            // Make API request to check payment status
            $response = $this->makeRequest('GET', '/payments/' . $transactionId);

            if (!$response['success']) {
                throw new PaymentGatewayException(
                    'SnapScan payment verification failed: ' . ($response['message'] ?? 'Unknown error'),
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
            $this->setLastError($e->getMessage(), 'SNAPSCAN_VERIFY_ERROR');
            throw new PaymentGatewayException(
                'Failed to verify SnapScan payment: ' . $e->getMessage(),
                ['transaction_id' => $transactionId],
                0,
                $e
            );
        }
    }

    /**
     * Process callback data for SnapScan
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
                'error_code' => 'SS012',
            ];
        }

        $transactionId = $callbackData['id'] ?? $callbackData['transaction_id'] ?? null;
        $status = $callbackData['status'] ?? null;
        $amount = $callbackData['amount'] ?? null;
        $currency = $callbackData['currency'] ?? 'ZAR';
        $reference = $callbackData['reference'] ?? null;

        if (!$transactionId || !$status) {
            return [
                'success' => false,
                'message' => 'Missing required callback parameters',
                'error_code' => 'SS009',
            ];
        }

        // Map SnapScan status to our internal status
        $internalStatus = $this->mapSnapScanStatus($status);

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
     * Process refund for SnapScan payment
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
                'id' => $transactionId,
                'amount' => $amount,
                'reason' => 'Customer request',
                'reference' => 'REF-' . $transactionId . '-' . Str::random(6),
            ];

            // Make API request to process refund
            $response = $this->makeRequest('POST', '/refunds', $payload);

            if (!$response['success']) {
                throw new PaymentGatewayException(
                    'SnapScan refund failed: ' . ($response['message'] ?? 'Unknown error'),
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
            $this->setLastError($e->getMessage(), 'SNAPSCAN_REFUND_ERROR');
            throw new PaymentGatewayException(
                'Failed to process SnapScan refund: ' . $e->getMessage(),
                ['transaction_id' => $transactionId, 'amount' => $amount],
                0,
                $e
            );
        }
    }

    /**
     * Prepare payment payload for SnapScan API
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
            'merchantId' => $this->config['merchant_id'],
            'reference' => $reference,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? $this->config['currency'],
            'description' => $paymentData['description'] ?? 'Payment',
            'customer' => [
                'email' => $customer['email'] ?? null,
                'name' => $customer['name'] ?? null,
                'mobile' => $customer['phone'] ?? null,
            ],
            'callbackUrl' => $this->config['callback_url'],
            'successUrl' => $paymentData['return_url'] ?? $this->config['return_url'],
            'cancelUrl' => $paymentData['cancel_url'] ?? $this->config['cancel_url'],
            'metadata' => $metadata,
            'expiryMinutes' => 10,
            'qrCodeSize' => 'medium',
        ];
    }

    /**
     * Validate callback signature for SnapScan
     *
     * @param array $callbackData
     * @return bool
     */
    protected function validateCallbackSignature(array $callbackData): bool
    {
        $signature = $callbackData['signature'] ?? null;
        $timestamp = $callbackData['timestamp'] ?? null;
        $transactionId = $callbackData['id'] ?? $callbackData['transaction_id'] ?? null;

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
     * Map SnapScan status to internal status
     *
     * @param string $snapScanStatus
     * @return string
     */
    protected function mapSnapScanStatus(string $snapScanStatus): string
    {
        $statusMap = [
            'INITIATED' => self::STATUS_INITIATED,
            'PENDING' => self::STATUS_PENDING,
            'SUCCESS' => self::STATUS_SUCCESS,
            'FAILED' => self::STATUS_FAILED,
            'CANCELLED' => self::STATUS_CANCELLED,
            'EXPIRED' => self::STATUS_EXPIRED,
        ];

        return $statusMap[$snapScanStatus] ?? self::STATUS_PENDING;
    }

    /**
     * Get status message for SnapScan status
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
            'customer.phone',
            'metadata',
            'return_url',
            'cancel_url',
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
                'merchantId' => $this->config['merchant_id'],
                'reference' => $reference,
                'amount' => $subscriptionData['amount'],
                'currency' => $subscriptionData['currency'] ?? $this->config['currency'],
                'description' => $subscriptionData['description'] ?? 'Subscription',
                'customer' => [
                    'email' => $subscriptionData['customer']['email'] ?? null,
                    'name' => $subscriptionData['customer']['name'] ?? null,
                    'mobile' => $subscriptionData['customer']['phone'] ?? null,
                ],
                'frequency' => $subscriptionData['frequency'] ?? 'monthly',
                'startDate' => $subscriptionData['start_date'] ?? Carbon::now()->addDay()->toDateString(),
                'endDate' => $subscriptionData['end_date'] ?? null,
                'cycles' => $subscriptionData['cycles'] ?? null,
                'metadata' => $subscriptionData['metadata'] ?? [],
            ];

            // Make API request to create subscription
            $response = $this->makeRequest('POST', '/subscriptions', $payload);

            if (!$response['success']) {
                throw new PaymentGatewayException(
                    'SnapScan subscription creation failed: ' . ($response['message'] ?? 'Unknown error'),
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
            $this->setLastError($e->getMessage(), 'SNAPSCAN_SUBSCRIPTION_ERROR');
            throw new PaymentGatewayException(
                'Failed to create SnapScan subscription: ' . $e->getMessage(),
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
                    'SnapScan subscription cancellation failed: ' . ($response['message'] ?? 'Unknown error'),
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
            $this->setLastError($e->getMessage(), 'SNAPSCAN_CANCEL_SUBSCRIPTION_ERROR');
            throw new PaymentGatewayException(
                'Failed to cancel SnapScan subscription: ' . $e->getMessage(),
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
                    'Failed to get SnapScan subscription: ' . ($response['message'] ?? 'Unknown error'),
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
            $this->setLastError($e->getMessage(), 'SNAPSCAN_GET_SUBSCRIPTION_ERROR');
            throw new PaymentGatewayException(
                'Failed to get SnapScan subscription: ' . $e->getMessage(),
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
                    'SnapScan subscription update failed: ' . ($response['message'] ?? 'Unknown error'),
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
            $this->setLastError($e->getMessage(), 'SNAPSCAN_UPDATE_SUBSCRIPTION_ERROR');
            throw new PaymentGatewayException(
                'Failed to update SnapScan subscription: ' . $e->getMessage(),
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
     * Make HTTP request to SnapScan API
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

        // Add SnapScan specific headers
        $headers['X-Merchant-Id'] = $this->config['merchant_id'];
        $headers['X-Api-Key'] = $this->config['api_key'];
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
     * Get default headers for SnapScan API
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
     * Log activity for SnapScan gateway
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
