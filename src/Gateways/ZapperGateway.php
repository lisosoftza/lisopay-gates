<?php

namespace Lisosoft\PaymentGateway\Gateways;

use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ZapperGateway extends AbstractGateway
{
    /**
     * Zapper API endpoints
     */
    const API_ENDPOINT_LIVE = "https://api.zapper.com";
    const API_ENDPOINT_SANDBOX = "https://api-sandbox.zapper.com";

    /**
     * Zapper payment statuses
     */
    const STATUS_SUCCESS = "Success";
    const STATUS_PENDING = "Pending";
    const STATUS_FAILED = "Failed";
    const STATUS_CANCELLED = "Cancelled";
    const STATUS_EXPIRED = "Expired";

    /**
     * Initialize the Zapper gateway
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->name = "zapper";
        $this->displayName = "Zapper";

        // Set supported currencies (Zapper primarily supports ZAR)
        $this->supportedCurrencies = ["ZAR"];

        // Set supported payment methods
        $this->paymentMethods = [
            "zapper_qr",
            "card",
            "eft",
            "instant_eft",
        ];
    }

    /**
     * Get default configuration for Zapper
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            "enabled" => true,
            "merchant_id" => "",
            "site_id" => "",
            "api_key" => "",
            "test_mode" => true,
            "callback_url" => "/payment/callback/zapper",
            "validate_signature" => true,
            "timeout" => 30,
            "retry_attempts" => 3,
            "retry_delay" => 100,
            "verify_ssl" => true,
            "minimum_amount" => 1.00,
            "maximum_amount" => 50000.00,
            "currency" => "ZAR",
            "reference_prefix" => "ZAP",
            "logo_url" => "https://zapper.com/images/zapper-logo.png",
            "documentation_url" => "https://developer.zapper.com",
            "error_messages" => [
                "invalid_merchant" => "Invalid merchant configuration",
                "invalid_amount" => "Invalid payment amount",
                "payment_expired" => "Payment QR code expired",
                "payment_cancelled" => "Payment cancelled by user",
                "insufficient_funds" => "Insufficient funds",
                "transaction_declined" => "Transaction declined",
                "technical_error" => "Technical error occurred",
                "duplicate_transaction" => "Duplicate transaction detected",
            ],
        ];
    }

    /**
     * Get Zapper API endpoint based on test mode
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        return $this->isTestMode() ? self::API_ENDPOINT_SANDBOX : self::API_ENDPOINT_LIVE;
    }

    /**
     * Get default HTTP headers for Zapper
     *
     * @return array
     */
    protected function getDefaultHeaders(): array
    {
        $headers = parent::getDefaultHeaders();

        // Add Zapper authentication header
        $headers["Api-Key"] = $this->config["api_key"];
        $headers["Merchant-Id"] = $this->config["merchant_id"];
        $headers["Site-Id"] = $this->config["site_id"];

        return $headers;
    }

    /**
     * Process payment initialization for Zapper
     *
     * @param array $paymentData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processInitializePayment(array $paymentData): array
    {
        // Validate required configuration
        $this->validateConfiguration();

        // Prepare Zapper payment data
        $zapperData = $this->preparePaymentData($paymentData);

        // Make API request to create payment
        $response = $this->makeRequest("POST", "/v1/payments", $zapperData);

        // Log payment initialization
        $this->logActivity("payment_initialized", [
            "payment_data" => $paymentData,
            "zapper_data" => $zapperData,
            "response" => $response,
        ]);

        return [
            "success" => $response["success"] ?? false,
            "gateway" => $this->getName(),
            "transaction_id" => $response["paymentReference"] ?? $zapperData["reference"],
            "gateway_transaction_id" => $response["paymentId"] ?? null,
            "payment_url" => $response["paymentUrl"] ?? "",
            "qr_code_url" => $response["qrCodeUrl"] ?? "",
            "qr_code_data" => $response["qrCodeData"] ?? "",
            "expires_at" => $response["expiresAt"] ?? null,
            "status" => "pending",
            "redirect_required" => false, // Zapper uses QR codes, no redirect
            "message" => $response["message"] ?? "Zapper payment initialized successfully",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Prepare payment data for Zapper
     *
     * @param array $paymentData
     * @return array
     */
    protected function preparePaymentData(array $paymentData): array
    {
        // Generate reference if not provided
        $reference = $paymentData["reference"] ?? $this->generateReference($paymentData);

        // Base Zapper parameters
        $zapperData = [
            "merchantId" => $this->config["merchant_id"],
            "siteId" => $this->config["site_id"],
            "amount" => number_format($paymentData["amount"], 2, ".", ""),
            "currency" => strtoupper($paymentData["currency"] ?? "ZAR"),
            "reference" => $reference,
            "description" => $paymentData["description"] ?? "Payment",
            "callbackUrl" => $this->buildCallbackUrl($reference),
            "successUrl" => $this->buildSuccessUrl($reference),
            "cancelUrl" => $this->buildCancelUrl($reference),
            "isTest" => $this->isTestMode(),
        ];

        // Add customer details
        if (isset($paymentData["customer"])) {
            $zapperData["customer"] = [
                "email" => $paymentData["customer"]["email"] ?? "",
                "firstName" => $paymentData["customer"]["first_name"] ?? $paymentData["customer"]["name"] ?? "",
                "lastName" => $paymentData["customer"]["last_name"] ?? "",
                "mobile" => $paymentData["customer"]["phone"] ?? "",
            ];
        }

        // Add optional parameters
        $optionalParams = [
            "metadata" => $paymentData["metadata"] ?? [],
            "items" => $paymentData["items"] ?? [],
            "shippingAddress" => $paymentData["shipping_address"] ?? null,
            "billingAddress" => $paymentData["billing_address"] ?? null,
            "expiryMinutes" => $paymentData["expiry_minutes"] ?? 30,
            "allowedPaymentMethods" => $paymentData["allowed_payment_methods"] ?? ["zapper_qr", "card", "eft"],
            "sendEmailReceipt" => $paymentData["send_email_receipt"] ?? true,
            "sendSmsReceipt" => $paymentData["send_sms_receipt"] ?? false,
        ];

        // Filter out empty optional parameters
        foreach ($optionalParams as $key => $value) {
            if (!empty($value)) {
                $zapperData[$key] = $value;
            }
        }

        return $zapperData;
    }

    /**
     * Build callback URL with reference
     *
     * @param string $reference
     * @return string
     */
    protected function buildCallbackUrl(string $reference): string
    {
        $callbackUrl = $this->config["callback_url"] ?? "/payment/callback/zapper";

        // If URL is absolute, return as is
        if (filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            return $callbackUrl;
        }

        // Add reference as query parameter
        $separator = strpos($callbackUrl, "?") === false ? "?" : "&";
        return $callbackUrl . $separator . "reference=" . urlencode($reference);
    }

    /**
     * Build success URL with reference
     *
     * @param string $reference
     * @return string
     */
    protected function buildSuccessUrl(string $reference): string
    {
        $successUrl = config("payment-gateway.transaction.return_url", "/payment/success");

        // Add reference as query parameter
        $separator = strpos($successUrl, "?") === false ? "?" : "&";
        return $successUrl . $separator . "reference=" . urlencode($reference);
    }

    /**
     * Build cancel URL with reference
     *
     * @param string $reference
     * @return string
     */
    protected function buildCancelUrl(string $reference): string
    {
        $cancelUrl = config("payment-gateway.transaction.cancel_url", "/payment/cancel");

        // Add reference as query parameter
        $separator = strpos($cancelUrl, "?") === false ? "?" : "&";
        return $cancelUrl . $separator . "reference=" . urlencode($reference);
    }

    /**
     * Process payment verification for Zapper
     *
     * @param string $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processVerifyPayment(string $transactionId): array
    {
        // Validate configuration
        $this->validateConfiguration();

        // Make API request to get payment status
        $response = $this->makeRequest("GET", "/v1/payments/" . $transactionId);

        // Log verification attempt
        $this->logActivity("payment_verification_attempt", [
            "transaction_id" => $transactionId,
            "response" => $response,
        ]);

        // Extract payment details
        $status = $response["status"] ?? "Pending";
        $gatewayStatus = $this->mapPaymentStatus($status);

        return [
            "success" => $gatewayStatus === "completed",
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "gateway_transaction_id" => $response["paymentId"] ?? null,
            "status" => $gatewayStatus,
            "zapper_status" => $status,
            "amount" => (float) ($response["amount"] ?? 0),
            "currency" => $response["currency"] ?? "ZAR",
            "payment_method" => $response["paymentMethod"] ?? null,
            "paid_at" => $response["paidAt"] ?? null,
            "created_at" => $response["createdAt"] ?? null,
            "expires_at" => $response["expiresAt"] ?? null,
            "customer" => $response["customer"] ?? [],
            "metadata" => $response["metadata"] ?? [],
            "message" => $response["message"] ?? "Payment verification completed",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Process callback data from Zapper webhook
     *
     * @param array $callbackData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processCallbackData(array $callbackData): array
    {
        // Validate callback signature
        $this->validateCallbackSignature($callbackData);

        // Extract payment details
        $transactionId = $callbackData["reference"] ?? "";
        $status = $callbackData["status"] ?? "";
        $gatewayStatus = $this->mapPaymentStatus($status);

        // Prepare response
        $response = [
            "success" => $gatewayStatus === "completed",
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "gateway_transaction_id" => $callbackData["paymentId"] ?? null,
            "status" => $gatewayStatus,
            "zapper_status" => $status,
            "amount" => (float) ($callbackData["amount"] ?? 0),
            "currency" => $callbackData["currency"] ?? "ZAR",
            "payment_method" => $callbackData["paymentMethod"] ?? null,
            "paid_at" => $callbackData["paidAt"] ?? null,
            "customer" => $callbackData["customer"] ?? [],
            "metadata" => $callbackData["metadata"] ?? [],
            "raw_data" => $callbackData,
            "timestamp" => now()->toISOString(),
        ];

        // Add error details if payment failed
        if ($gatewayStatus === "failed") {
            $response["error_message"] = $callbackData["errorMessage"] ?? "Payment failed";
            $response["error_code"] = $callbackData["errorCode"] ?? null;
        }

        // Log callback processing
        $this->logActivity("callback_processed", $response);

        return $response;
    }

    /**
     * Validate callback signature for Zapper
     *
     * @param array $callbackData
     * @return bool
     * @throws PaymentGatewayException
     */
    protected function validateCallbackSignature(array $callbackData): bool
    {
        if (!($this->config["validate_signature"] ?? true)) {
            return true;
        }

        // Get signature from callback data
        $receivedSignature = $callbackData["signature"] ?? "";

        if (empty($receivedSignature)) {
            throw PaymentGatewayException::invalidSignature(
                $this->getName(),
                "Missing signature in callback data"
            );
        }

        // Generate expected signature
        $expectedSignature = $this->generateSignature($callbackData);

        // Compare signatures
        if (!hash_equals($expectedSignature, $receivedSignature)) {
            throw PaymentGatewayException::invalidSignature(
                $this->getName(),
                "Signature verification failed"
            );
        }

        return true;
    }

    /**
     * Generate Zapper signature
     *
     * @param array $data
     * @return string
     */
    protected function generateSignature(array $data): string
    {
        // Remove signature and empty values
        unset($data["signature"]);
        $data = array_filter($data, function ($value) {
            return $value !== "" && $value !== null;
        });

        // Sort data alphabetically by key
        ksort($data);

        // Build parameter string
        $paramString = "";
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $paramString .= $key . "=" . $value . "&";
        }

        // Remove last '&'
        $paramString = rtrim($paramString, "&");

        // Add API key
        $paramString .= $this->config["api_key"];

        // Generate SHA256 hash
        return hash("sha256", $paramString);
    }

    /**
     * Process refund payment for Zapper
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

        // Prepare refund data
        $refundData = [
            "paymentId" => $transactionId,
            "amount" => $amount !== null ? number_format($amount, 2, ".", "") : null,
            "reason" => "Refund requested",
        ];

        // Make API request to process refund
        $response = $this->makeRequest("POST", "/v1/refunds", $refundData);

        // Log refund attempt
        $this->logActivity("refund_requested", [
            "transaction_id" => $transactionId,
            "amount" => $amount,
            "response" => $response,
        ]);

        return [
            "success" => $response["success"] ?? false,
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "refund_id" => $response["refundId"] ?? null,
            "refund_reference" => $response["refundReference"] ?? null,
            "amount" => (float) ($response["amount"] ?? $amount),
            "currency" => $response["currency"] ?? "ZAR",
            "status" => $response["status"] ?? "pending",
            "message" => $response["message"] ?? "Refund processed successfully",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Map Zapper payment status to our status
     *
     * @param string $zapperStatus
     * @return string
     */
    protected function mapPaymentStatus(string $zapperStatus): string
    {
        $statusMap = [
            "Success" => "completed",
            "Pending" => "pending",
            "Failed" => "failed",
            "Cancelled" => "cancelled",
            "Expired" => "expired",
            "Refunded" => "refunded",
            "PartiallyRefunded" => "partially_refunded",
        ];

        return $statusMap[$zapperStatus] ?? "unknown";
    }

    /**
     * Validate Zapper configuration
     *
     * @return void
     * @throws PaymentGatewayException
     */
    protected function validateConfiguration(): void
    {
        $missingConfig = [];

        // Check required configuration
        $requiredConfig = ["merchant_id", "site_id", "api_key"];
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
        // Zapper doesn't support native subscriptions
        // This would need to be implemented with recurring payments

        throw new PaymentGatewayException(
            "Subscriptions are not natively supported by Zapper. Consider using recurring payments with manual initiation.",
            ["subscription_data" => $subscriptionData]
        );
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
        throw new PaymentGatewayException(
            "Subscriptions are not supported by Zapper",
            ["subscription_id" => $subscriptionId]
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
        throw new PaymentGatewayException(
            "Subscriptions are not supported by Zapper",
            ["subscription_id" => $subscriptionId]
        );
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
        throw new PaymentGatewayException(
            "Subscriptions are not supported by Zapper",
            ["subscription_id" => $subscriptionId]
        );
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
        // Validate configuration
        $this->validateConfiguration();

        // Build query parameters
        $queryParams = [];

        if (isset($filters["limit"])) {
            $queryParams["limit"] = $filters["limit"];
        }

        if (isset($filters["offset"])) {
            $queryParams["offset"] = $filters["offset"];
        }

        if (isset($filters["startDate"])) {
            $queryParams["startDate"] = $filters["startDate"];
        }

        if (isset($filters["endDate"])) {
            $queryParams["endDate"] = $filters["endDate"];
        }

        if (isset($filters["status"])) {
            $queryParams["status"] = $filters["status"];
        }

        if (isset($filters["customerEmail"])) {
            $queryParams["customerEmail"] = $filters["customerEmail"];
        }

        // Make API request to get transactions
        $url = "/v1/payments" . (!empty($queryParams) ? "?" . http_build_query($queryParams) : "");
        $response = $this->makeRequest("GET", $url);

        $payments = $response["payments"] ?? [];
        $mappedTransactions = [];

        foreach ($payments as $payment) {
            $mappedTransactions[] = [
                "id" => $payment["paymentId"] ?? null,
                "reference" => $payment["reference"] ?? null,
                "amount" => (float) ($payment["amount"] ?? 0),
                "currency" => $payment["currency"] ?? "ZAR",
                "status" => $this->mapPaymentStatus($payment["status"] ?? "Pending"),
                "zapper_status" => $payment["status"] ?? "Pending",
                "customer_email" => $payment["customer"]["email"] ?? null,
                "payment_method" => $payment["paymentMethod"] ?? null,
                "created_at" => $payment["createdAt"] ?? null,
                "paid_at" => $payment["paidAt"] ?? null,
                "metadata" => $payment["metadata"] ?? [],
            ];
        }

        return [
            "success" => $response["success"] ?? false,
            "gateway" => $this->getName(),
            "transactions" => $mappedTransactions,
            "total" => $response["total"] ?? count($mappedTransactions),
            "limit" => $response["limit"] ?? 50,
            "offset" => $response["offset"] ?? 0,
            "message" => $response["message"] ?? "Transaction history retrieved",
            "timestamp" => now()->toISOString(),
        ];
    }

    /**
     * Get required fields for Zapper payment
     *
     * @return array
     */
    public function getRequiredFields(): array
    {
        return [
            "amount",
            "currency",
            "description",
        ];
    }

    /**
     * Get optional fields for Zapper payment
     *
     * @return array
     */
    public function getOptionalFields(): array
    {
        return [
            "reference",
            "customer.email",
            "customer.first_name",
            "customer.last_name",
            "customer.name",
            "customer.phone",
            "metadata",
            "items",
            "shipping_address",
            "billing_address",
            "expiry_minutes",
            "allowed_payment_methods",
            "send_email_receipt",
            "send_sms_receipt",
        ];
    }
}
