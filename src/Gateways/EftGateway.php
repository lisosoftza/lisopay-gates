<?php

namespace Lisosoft\PaymentGateway\Gateways;

use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EftGateway extends AbstractGateway
{
    /**
     * EFT payment statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_VERIFIED = 'verified';
    const STATUS_COMPLETED = 'completed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Initialize the EFT gateway
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->name = 'eft';
        $this->displayName = 'EFT/Bank Transfer';

        // Set supported currencies (EFT supports multiple currencies)
        $this->supportedCurrencies = ['ZAR', 'USD', 'EUR', 'GBP'];

        // Set supported payment methods
        $this->paymentMethods = [
            'bank_transfer',
            'direct_deposit',
            'wire_transfer',
        ];
    }

    /**
     * Get default configuration for EFT
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'bank_name' => 'Standard Bank',
            'account_name' => '',
            'account_number' => '',
            'branch_code' => '',
            'swift_code' => '',
            'reference_prefix' => 'LISO',
            'payment_window_hours' => 24,
            'verification_required' => true,
            'auto_verify' => false,
            'notify_on_payment' => true,
            'notify_on_verification' => true,
            'minimum_amount' => 1.00,
            'maximum_amount' => 1000000.00,
            'currency' => 'ZAR',
            'logo_url' => 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png',
            'documentation_url' => '',
            'error_messages' => [
                '001' => 'Payment window expired',
                '002' => 'Invalid reference number',
                '003' => 'Payment amount mismatch',
                '004' => 'Bank account verification failed',
                '005' => 'Payment reference not found',
                '006' => 'Duplicate payment detected',
                '007' => 'Invalid bank details',
                '008' => 'Payment verification timeout',
                '009' => 'Insufficient payment amount',
                '010' => 'Payment already processed',
            ],
        ];
    }

    /**
     * Process payment initialization for EFT
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

            // Calculate expiry time
            $paymentWindowHours = $this->config['payment_window_hours'] ?? 24;
            $expiresAt = Carbon::now()->addHours($paymentWindowHours);

            // Get bank details
            $bankDetails = $this->getBankDetails();

            // Prepare payment instructions
            $paymentInstructions = $this->generatePaymentInstructions($reference, $paymentData, $bankDetails);

            // Create transaction record
            $transactionData = [
                'reference' => $reference,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? $this->config['currency'],
                'description' => $paymentData['description'] ?? 'EFT Payment',
                'customer_email' => $paymentData['customer']['email'] ?? null,
                'customer_name' => $paymentData['customer']['name'] ?? null,
                'expires_at' => $expiresAt,
                'bank_details' => $bankDetails,
                'payment_instructions' => $paymentInstructions,
                'metadata' => $paymentData['metadata'] ?? [],
            ];

            // Return payment initialization response
            return [
                'success' => true,
                'gateway' => $this->name,
                'reference' => $reference,
                'transaction_id' => $reference,
                'payment_url' => null, // No redirect URL for EFT
                'payment_instructions' => $paymentInstructions,
                'bank_details' => $bankDetails,
                'expires_at' => $expiresAt->toISOString(),
                'verification_required' => $this->config['verification_required'] ?? true,
                'message' => 'EFT payment initialized successfully. Please transfer funds using the provided bank details.',
                'data' => $transactionData,
            ];

        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), 'EFT_INIT_ERROR');
            throw new PaymentGatewayException(
                'Failed to initialize EFT payment: ' . $e->getMessage(),
                ['payment_data' => $paymentData],
                0,
                $e
            );
        }
    }

    /**
     * Process payment verification for EFT
     *
     * @param string $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processVerifyPayment(string $transactionId): array
    {
        try {
            // In a real implementation, this would check with bank API or database
            // For now, we'll simulate verification

            $status = $this->getPaymentStatus($transactionId);

            if ($status === self::STATUS_VERIFIED || $status === self::STATUS_COMPLETED) {
                return [
                    'success' => true,
                    'gateway' => $this->name,
                    'transaction_id' => $transactionId,
                    'status' => $status,
                    'verified_at' => now()->toISOString(),
                    'message' => 'Payment verified successfully',
                ];
            }

            // Check if payment is still pending
            if ($status === self::STATUS_PENDING) {
                return [
                    'success' => false,
                    'gateway' => $this->name,
                    'transaction_id' => $transactionId,
                    'status' => $status,
                    'message' => 'Payment is still pending verification',
                    'action_required' => 'manual_verification',
                ];
            }

            // Payment expired or cancelled
            return [
                'success' => false,
                'gateway' => $this->name,
                'transaction_id' => $transactionId,
                'status' => $status,
                'message' => 'Payment ' . $status,
            ];

        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), 'EFT_VERIFY_ERROR');
            throw new PaymentGatewayException(
                'Failed to verify EFT payment: ' . $e->getMessage(),
                ['transaction_id' => $transactionId],
                0,
                $e
            );
        }
    }

    /**
     * Process callback data for EFT
     *
     * @param array $callbackData
     * @return array
     */
    protected function processCallbackData(array $callbackData): array
    {
        // EFT typically doesn't have webhooks, but we can handle manual verification callbacks
        $reference = $callbackData['reference'] ?? null;
        $amount = $callbackData['amount'] ?? null;
        $verificationCode = $callbackData['verification_code'] ?? null;

        if (!$reference || !$amount) {
            return [
                'success' => false,
                'message' => 'Missing required parameters',
                'error_code' => '002',
            ];
        }

        // In a real implementation, this would verify with bank records
        // For now, we'll simulate successful verification with a code
        if ($verificationCode === 'VERIFY123') {
            return [
                'success' => true,
                'gateway' => $this->name,
                'reference' => $reference,
                'transaction_id' => $reference,
                'status' => self::STATUS_VERIFIED,
                'amount' => $amount,
                'verified_at' => now()->toISOString(),
                'message' => 'Payment verified successfully',
            ];
        }

        return [
            'success' => false,
            'gateway' => $this->name,
            'reference' => $reference,
            'transaction_id' => $reference,
            'status' => self::STATUS_PENDING,
            'message' => 'Verification failed. Please check the verification code.',
            'error_code' => '004',
        ];
    }

    /**
     * Process refund for EFT payment
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processRefundPayment(string $transactionId, ?float $amount = null): array
    {
        try {
            // EFT refunds require manual processing
            // This method would initiate a bank transfer refund

            $status = $this->getPaymentStatus($transactionId);

            if ($status !== self::STATUS_COMPLETED && $status !== self::STATUS_VERIFIED) {
                throw new PaymentGatewayException(
                    'Cannot refund payment with status: ' . $status,
                    ['transaction_id' => $transactionId, 'status' => $status]
                );
            }

            // Generate refund reference
            $refundReference = 'REF-' . $transactionId . '-' . Str::random(6);

            return [
                'success' => true,
                'gateway' => $this->name,
                'transaction_id' => $transactionId,
                'refund_id' => $refundReference,
                'refund_reference' => $refundReference,
                'amount' => $amount,
                'status' => 'pending',
                'message' => 'Refund initiated. Please allow 3-5 business days for processing.',
                'instructions' => 'Refund will be processed via bank transfer to the original payer.',
                'estimated_completion' => Carbon::now()->addDays(3)->toISOString(),
            ];

        } catch (\Exception $e) {
            $this->setLastError($e->getMessage(), 'EFT_REFUND_ERROR');
            throw new PaymentGatewayException(
                'Failed to process EFT refund: ' . $e->getMessage(),
                ['transaction_id' => $transactionId, 'amount' => $amount],
                0,
                $e
            );
        }
    }

    /**
     * Get bank details for payment instructions
     *
     * @return array
     */
    protected function getBankDetails(): array
    {
        return [
            'bank_name' => $this->config['bank_name'] ?? 'Standard Bank',
            'account_name' => $this->config['account_name'] ?? '',
            'account_number' => $this->config['account_number'] ?? '',
            'branch_code' => $this->config['branch_code'] ?? '',
            'swift_code' => $this->config['swift_code'] ?? '',
            'account_type' => 'Current Account',
            'branch_name' => $this->config['bank_name'] . ' Main Branch',
        ];
    }

    /**
     * Generate payment instructions
     *
     * @param string $reference
     * @param array $paymentData
     * @param array $bankDetails
     * @return array
     */
    protected function generatePaymentInstructions(string $reference, array $paymentData, array $bankDetails): array
    {
        $amount = $paymentData['amount'];
        $currency = $paymentData['currency'] ?? $this->config['currency'];
        $description = $paymentData['description'] ?? 'Payment';

        return [
            'step_1' => 'Log into your online banking',
            'step_2' => 'Add ' . $bankDetails['bank_name'] . ' as a beneficiary',
            'step_3' => 'Use the following beneficiary details:',
            'beneficiary_details' => [
                'account_holder' => $bankDetails['account_name'],
                'account_number' => $bankDetails['account_number'],
                'branch_code' => $bankDetails['branch_code'],
                'bank_name' => $bankDetails['bank_name'],
            ],
            'step_4' => 'Make payment with the following reference:',
            'payment_reference' => $reference,
            'step_5' => 'Transfer the exact amount:',
            'payment_amount' => [
                'amount' => $amount,
                'currency' => $currency,
                'formatted' => $currency . ' ' . number_format($amount, 2),
            ],
            'step_6' => 'Keep proof of payment for verification',
            'important_notes' => [
                'Payment must be made within ' . ($this->config['payment_window_hours'] ?? 24) . ' hours',
                'Use the exact reference number provided',
                'Transfer the exact amount specified',
                'International transfers may take longer',
                'Contact support if you encounter any issues',
            ],
        ];
    }

    /**
     * Get payment status
     *
     * @param string $transactionId
     * @return string
     */
    public function getPaymentStatus(string $transactionId): string
    {
        // In a real implementation, this would check database or bank API
        // For now, we'll simulate status based on transaction ID pattern

        // Check if transaction exists in database (simulated)
        $lastChar = substr($transactionId, -1);

        // Simple simulation based on last character
        switch ($lastChar) {
            case '1':
            case '2':
            case '3':
                return self::STATUS_COMPLETED;
            case '4':
            case '5':
            case '6':
                return self::STATUS_VERIFIED;
            case '7':
            case '8':
                return self::STATUS_PENDING;
            case '9':
                return self::STATUS_EXPIRED;
            case '0':
                return self::STATUS_CANCELLED;
            default:
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
        return in_array($status, [self::STATUS_COMPLETED, self::STATUS_VERIFIED]);
    }

    /**
     * Check if payment is pending
     *
     * @param string $transactionId
     * @return bool
     */
    public function isPaymentPending(string $transactionId): bool
    {
        return $this->getPaymentStatus($transactionId) === self::STATUS_PENDING;
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
        return in_array($status, [self::STATUS_EXPIRED, self::STATUS_CANCELLED]);
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
     * Get API endpoint (not applicable for EFT)
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        return ''; // EFT doesn't have an API endpoint
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
            'customer.email',
            'customer.name',
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
            'customer.phone',
            'customer.address',
            'metadata',
            'return_url',
            'cancel_url',
        ];
    }

    /**
     * Create subscription (not supported for EFT)
     *
     * @param array $subscriptionData
     * @return array
     * @throws PaymentGatewayException
     */
    public function createSubscription(array $subscriptionData): array
    {
        throw new PaymentGatewayException(
            'Subscriptions are not supported for EFT payments',
            ['subscription_data' => $subscriptionData]
        );
    }

    /**
     * Cancel subscription (not supported for EFT)
     *
     * @param string $subscriptionId
     * @return array
     * @throws PaymentGatewayException
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        throw new PaymentGatewayException(
            'Subscriptions are not supported for EFT payments',
            ['subscription_id' => $subscriptionId]
        );
    }

    /**
     * Get subscription (not supported for EFT)
     *
     * @param string $subscriptionId
     * @return array
     * @throws PaymentGatewayException
     */
    public function getSubscription(string $subscriptionId): array
    {
        throw new PaymentGatewayException(
            'Subscriptions are not supported for EFT payments',
            ['subscription_id' => $subscriptionId]
        );
    }

    /**
     * Update subscription (not supported for EFT)
     *
     * @param string $subscriptionId
     * @param array $subscriptionData
     * @return array
     * @throws PaymentGatewayException
     */
    public function updateSubscription(string $subscriptionId, array $subscriptionData): array
    {
        throw new PaymentGatewayException(
            'Subscriptions are not supported for EFT payments',
            ['subscription_id' => $subscriptionId, 'subscription_data' => $subscriptionData]
        );
    }

    /**
     * Get transaction history (simulated for EFT)
     *
     * @param array $filters
     * @return array
     */
    public function getTransactionHistory(array $filters = []): array
    {
        // In a real implementation, this would query the database
        // For now, return empty array
        return [
            'transactions' => [],
            'total' => 0,
            'page' => $filters['page'] ?? 1,
            'per_page' => $filters['per_page'] ?? 20,
            'has_more' => false,
        ];
    }

    /**
     * Validate callback signature (not applicable for EFT)
     *
     * @param array $callbackData
     * @return bool
     */
    protected function validateCallbackSignature(array $callbackData): bool
    {
        // EFT doesn't use webhook signatures
        return true;
    }

    /**
     * Log activity for EFT gateway
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
        ], $data);

        // For now, we'll just return without actual logging
        // You would implement actual logging here
    }
}
