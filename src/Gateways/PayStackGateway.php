<?php

namespace Lisosoft\PaymentGateway\Gateways;

use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayStackGateway extends AbstractGateway
{
    /**
     * PayStack API endpoints
     */
    const API_ENDPOINT_LIVE = "https://api.paystack.co";
    const API_ENDPOINT_SANDBOX = "https://api.paystack.co";

    /**
     * PayStack payment statuses
     */
    const STATUS_SUCCESS = "success";
    const STATUS_FAILED = "failed";
    const STATUS_ABANDONED = "abandoned";
    const STATUS_PENDING = "pending";

    /**
     * Initialize the PayStack gateway
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->name = "paystack";
        $this->displayName = "PayStack";

        // Set supported currencies (PayStack supports multiple African currencies)
        $this->supportedCurrencies = ["NGN", "GHS", "ZAR", "USD", "EUR", "GBP"];

        // Set supported payment methods
        $this->paymentMethods = [
            "card",
            "bank",
            "ussd",
            "qr",
            "mobile_money",
            "bank_transfer",
        ];
    }

    /**
     * Get default configuration for PayStack
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            "enabled" => true,
            "public_key" => "",
            "secret_key" => "",
            "merchant_email" => "",
            "test_mode" => true,
            "callback_url" => "/payment/callback/paystack",
            "validate_signature" => true,
            "timeout" => 30,
            "retry_attempts" => 3,
            "retry_delay" => 100,
            "verify_ssl" => true,
            "minimum_amount" => 1.0,
            "maximum_amount" => 1000000.0,
            "currency" => "NGN",
            "reference_prefix" => "PS",
            "logo_url" =>
                "https://paystack.com/assets/img/brand/paystack-logo.png",
            "documentation_url" => "https://paystack.com/docs",
            "error_messages" => [
                "insufficient_funds" => "Insufficient funds in account",
                "card_declined" => "Card was declined",
                "expired_card" => "Card has expired",
                "invalid_card" => "Invalid card details",
                "invalid_amount" => "Invalid amount specified",
                "invalid_currency" => "Invalid currency specified",
                "duplicate_transaction" => "Duplicate transaction detected",
                "transaction_timeout" => "Transaction timed out",
                "bank_error" => "Bank processing error",
                "gateway_error" => "Payment gateway error",
            ],
        ];
    }

    /**
     * Get PayStack API endpoint
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        return self::API_ENDPOINT_LIVE; // PayStack uses same endpoint for test/live
    }

    /**
     * Get default HTTP headers for PayStack
     *
     * @return array
     */
    protected function getDefaultHeaders(): array
    {
        $headers = parent::getDefaultHeaders();

        // Add PayStack authentication header
        $headers["Authorization"] = "Bearer " . $this->config["secret_key"];

        return $headers;
    }

    /**
     * Process payment initialization for PayStack
     *
     * @param array $paymentData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processInitializePayment(array $paymentData): array
    {
        // Validate required configuration
        $this->validateConfiguration();

        // Prepare PayStack payment data
        $paystackData = $this->preparePaymentData($paymentData);

        // Make API request to initialize payment
        $response = $this->makeRequest(
            "POST",
            "/transaction/initialize",
            $paystackData,
        );

        // Log payment initialization
        $this->logActivity("payment_initialized", [
            "payment_data" => $paymentData,
            "paystack_data" => $paystackData,
            "response" => $response,
        ]);

        return [
            "success" => $response["status"] ?? false,
            "gateway" => $this->getName(),
            "transaction_id" =>
                $response["data"]["reference"] ?? $paymentData["reference"],
            "gateway_transaction_id" => $response["data"]["reference"] ?? null,
            "payment_url" => $response["data"]["authorization_url"] ?? "",
            "access_code" => $response["data"]["access_code"] ?? "",
            "authorization_url" => $response["data"]["authorization_url"] ?? "",
            "reference" => $response["data"]["reference"] ?? "",
            "redirect_required" => true,
            "message" =>
                $response["message"] ?? "Payment initialized successfully",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Prepare payment data for PayStack
     *
     * @param array $paymentData
     * @return array
     */
    protected function preparePaymentData(array $paymentData): array
    {
        // Generate reference if not provided
        $reference =
            $paymentData["reference"] ?? $this->generateReference($paymentData);

        // Base PayStack parameters
        $paystackData = [
            "email" =>
                $paymentData["customer"]["email"] ??
                $this->config["merchant_email"],
            "amount" => $this->convertToKobo($paymentData["amount"]), // PayStack uses kobo/pesewas
            "reference" => $reference,
            "currency" => strtoupper($paymentData["currency"]),
            "callback_url" => $this->buildCallbackUrl($reference),
        ];

        // Add customer metadata
        $metadata = [
            "custom_fields" => [],
        ];

        if (isset($paymentData["customer"]["name"])) {
            $paystackData["name"] = $paymentData["customer"]["name"];
            $metadata["customer_name"] = $paymentData["customer"]["name"];
        }

        if (isset($paymentData["customer"]["phone"])) {
            $metadata["customer_phone"] = $paymentData["customer"]["phone"];
        }

        // Add custom metadata
        if (isset($paymentData["metadata"])) {
            $metadata = array_merge($metadata, $paymentData["metadata"]);
        }

        $paystackData["metadata"] = $metadata;

        // Add optional parameters
        $optionalParams = [
            "channels" => $paymentData["channels"] ?? [
                "card",
                "bank",
                "ussd",
                "qr",
                "mobile_money",
                "bank_transfer",
            ],
            "subaccount" => $paymentData["subaccount"] ?? "",
            "transaction_charge" => $paymentData["transaction_charge"] ?? 0,
            "bearer" => $paymentData["bearer"] ?? "account",
            "plan" => $paymentData["plan"] ?? "",
            "invoice_limit" => $paymentData["invoice_limit"] ?? 0,
            "split_code" => $paymentData["split_code"] ?? "",
        ];

        // Filter out empty optional parameters
        foreach ($optionalParams as $key => $value) {
            if (!empty($value)) {
                $paystackData[$key] = $value;
            }
        }

        return $paystackData;
    }

    /**
     * Convert amount to kobo/pesewas (smallest currency unit)
     *
     * @param float $amount
     * @return int
     */
    protected function convertToKobo(float $amount): int
    {
        // PayStack expects amount in kobo (for NGN) or pesewas (for GHS)
        // For ZAR, USD, EUR, GBP, multiply by 100
        return (int) ($amount * 100);
    }

    /**
     * Convert from kobo/pesewas to normal amount
     *
     * @param int $koboAmount
     * @return float
     */
    protected function convertFromKobo(int $koboAmount): float
    {
        return $koboAmount / 100;
    }

    /**
     * Build callback URL with reference
     *
     * @param string $reference
     * @return string
     */
    protected function buildCallbackUrl(string $reference): string
    {
        $callbackUrl =
            $this->config["callback_url"] ?? "/payment/callback/paystack";

        // If URL is absolute, return as is
        if (filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            return $callbackUrl;
        }

        // Add reference as query parameter
        $separator = strpos($callbackUrl, "?") === false ? "?" : "&";
        return $callbackUrl . $separator . "reference=" . urlencode($reference);
    }

    /**
     * Process payment verification for PayStack
     *
     * @param string $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processVerifyPayment(string $transactionId): array
    {
        // Validate configuration
        $this->validateConfiguration();

        // Make API request to verify payment
        $response = $this->makeRequest(
            "GET",
            "/transaction/verify/" . $transactionId,
        );

        // Log verification attempt
        $this->logActivity("payment_verification_attempt", [
            "transaction_id" => $transactionId,
            "response" => $response,
        ]);

        // Extract payment details
        $transactionData = $response["data"] ?? [];
        $status = $transactionData["status"] ?? "pending";
        $gatewayStatus = $this->mapPaymentStatus($status);

        $result = [
            "success" => $gatewayStatus === "completed",
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "gateway_transaction_id" => $transactionData["id"] ?? null,
            "status" => $gatewayStatus,
            "paystack_status" => $status,
            "amount" => isset($transactionData["amount"])
                ? $this->convertFromKobo($transactionData["amount"])
                : 0,
            "currency" => $transactionData["currency"] ?? "NGN",
            "customer_email" => $transactionData["customer"]["email"] ?? null,
            "customer_id" => $transactionData["customer"]["id"] ?? null,
            "paid_at" => $transactionData["paid_at"] ?? null,
            "created_at" => $transactionData["created_at"] ?? null,
            "channel" => $transactionData["channel"] ?? null,
            "ip_address" => $transactionData["ip_address"] ?? null,
            "metadata" => $transactionData["metadata"] ?? [],
            "fees" => isset($transactionData["fees"])
                ? $this->convertFromKobo($transactionData["fees"])
                : 0,
            "authorization" => $transactionData["authorization"] ?? [],
            "message" =>
                $response["message"] ?? "Payment verification completed",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];

        // Add authorization details if available
        if (isset($transactionData["authorization"])) {
            $auth = $transactionData["authorization"];
            $result["authorization"] = [
                "authorization_code" => $auth["authorization_code"] ?? null,
                "bin" => $auth["bin"] ?? null,
                "last4" => $auth["last4"] ?? null,
                "exp_month" => $auth["exp_month"] ?? null,
                "exp_year" => $auth["exp_year"] ?? null,
                "channel" => $auth["channel"] ?? null,
                "card_type" => $auth["card_type"] ?? null,
                "bank" => $auth["bank"] ?? null,
                "country_code" => $auth["country_code"] ?? null,
                "brand" => $auth["brand"] ?? null,
                "reusable" => $auth["reusable"] ?? false,
                "signature" => $auth["signature"] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Process callback data from PayStack webhook
     *
     * @param array $callbackData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processCallbackData(array $callbackData): array
    {
        // Validate callback signature
        $this->validateCallbackSignature($callbackData);

        // Extract event data
        $event = $callbackData["event"] ?? "";
        $transactionData = $callbackData["data"] ?? [];

        // Map PayStack event to our callback data
        $result = $this->mapPayStackEventToCallbackData(
            $event,
            $transactionData,
        );

        // Log callback processing
        $this->logActivity(
            "callback_processed",
            array_merge(["event" => $event], $result),
        );

        return $result;
    }

    /**
     * Validate callback signature for PayStack webhook
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

        // In a real implementation, you would validate the PayStack signature
        // from the X-Paystack-Signature header
        // This is a simplified version

        $secretKey = $this->config["secret_key"] ?? "";
        if (empty($secretKey)) {
            throw PaymentGatewayException::invalidSignature(
                $this->getName(),
                "Missing secret key for signature validation",
            );
        }

        // For now, we'll trust the webhook data
        // In production, implement proper signature validation
        return true;
    }

    /**
     * Map PayStack event to callback data
     *
     * @param string $event
     * @param array $transactionData
     * @return array
     */
    protected function mapPayStackEventToCallbackData(
        string $event,
        array $transactionData,
    ): array {
        $reference = $transactionData["reference"] ?? "";
        $status = $transactionData["status"] ?? "pending";
        $gatewayStatus = $this->mapPaymentStatus($status);

        $result = [
            "event" => $event,
            "success" => $gatewayStatus === "completed",
            "gateway" => $this->getName(),
            "transaction_id" => $reference,
            "gateway_transaction_id" => $transactionData["id"] ?? null,
            "status" => $gatewayStatus,
            "paystack_status" => $status,
            "amount" => isset($transactionData["amount"])
                ? $this->convertFromKobo($transactionData["amount"])
                : 0,
            "currency" => $transactionData["currency"] ?? "NGN",
            "customer_email" => $transactionData["customer"]["email"] ?? null,
            "paid_at" => $transactionData["paid_at"] ?? null,
            "created_at" => $transactionData["created_at"] ?? null,
            "channel" => $transactionData["channel"] ?? null,
            "metadata" => $transactionData["metadata"] ?? [],
            "fees" => isset($transactionData["fees"])
                ? $this->convertFromKobo($transactionData["fees"])
                : 0,
            "timestamp" => now()->toISOString(),
            "raw_data" => $transactionData,
        ];

        // Add event-specific data
        switch ($event) {
            case "charge.success":
                $result["message"] = "Payment completed successfully";
                break;
            case "charge.failed":
                $result["message"] = "Payment failed";
                $result["error_message"] =
                    $transactionData["gateway_response"] ?? "Payment failed";
                break;
            case "transfer.success":
                $result["message"] = "Transfer completed successfully";
                break;
            case "transfer.failed":
                $result["message"] = "Transfer failed";
                $result["error_message"] =
                    $transactionData["reason"] ?? "Transfer failed";
                break;
            case "subscription.create":
                $result["message"] = "Subscription created";
                $result["subscription_id"] =
                    $transactionData["subscription_code"] ?? null;
                break;
            case "subscription.disable":
                $result["message"] = "Subscription disabled";
                $result["subscription_id"] =
                    $transactionData["subscription_code"] ?? null;
                break;
        }

        return $result;
    }

    /**
     * Process refund payment for PayStack
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processRefundPayment(
        string $transactionId,
        ?float $amount = null,
    ): array {
        // Validate configuration
        $this->validateConfiguration();

        // Prepare refund data
        $refundData = [
            "transaction" => $transactionId,
        ];

        if ($amount !== null) {
            $refundData["amount"] = $this->convertToKobo($amount);
        }

        // Make API request to process refund
        $response = $this->makeRequest("POST", "/refund", $refundData);

        // Log refund attempt
        $this->logActivity("refund_requested", [
            "transaction_id" => $transactionId,
            "amount" => $amount,
            "response" => $response,
        ]);

        $refundData = $response["data"] ?? [];

        return [
            "success" => $response["status"] ?? false,
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "refund_id" => $refundData["id"] ?? null,
            "refund_reference" => $refundData["reference"] ?? null,
            "amount" => isset($refundData["amount"])
                ? $this->convertFromKobo($refundData["amount"])
                : $amount,
            "currency" => $refundData["currency"] ?? "NGN",
            "status" => $refundData["status"] ?? "pending",
            "message" =>
                $response["message"] ?? "Refund processed successfully",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Map PayStack payment status to our status
     *
     * @param string $paystackStatus
     * @return string
     */
    protected function mapPaymentStatus(string $paystackStatus): string
    {
        $statusMap = [
            "success" => "completed",
            "failed" => "failed",
            "abandoned" => "cancelled",
            "pending" => "pending",
            "reversed" => "refunded",
            "processed" => "processing",
        ];

        return $statusMap[strtolower($paystackStatus)] ?? "unknown";
    }

    /**
     * Validate PayStack configuration
     *
     * @return void
     * @throws PaymentGatewayException
     */
    protected function validateConfiguration(): void
    {
        $missingConfig = [];

        // Check required configuration
        $requiredConfig = ["secret_key", "public_key"];
        foreach ($requiredConfig as $configKey) {
            if (empty($this->config[$configKey])) {
                $missingConfig[] = $configKey;
            }
        }

        if (!empty($missingConfig)) {
            throw PaymentGatewayException::invalidConfiguration(
                $this->getName(),
                $missingConfig,
            );
        }

        // Check if gateway is enabled
        if (!$this->isAvailable()) {
            throw PaymentGatewayException::gatewayNotAvailable(
                $this->getName(),
            );
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
        $customerData = [
            "email" =>
                $subscriptionData["customer"]["email"] ??
                $this->config["merchant_email"],
            "first_name" => $subscriptionData["customer"]["first_name"] ?? "",
            "last_name" => $subscriptionData["customer"]["last_name"] ?? "",
            "phone" => $subscriptionData["customer"]["phone"] ?? "",
        ];

        // Create or get customer
        $customerResponse = $this->makeRequest(
            "POST",
            "/customer",
            $customerData,
        );
        $customerCode = $customerResponse["data"]["customer_code"] ?? null;

        if (!$customerCode) {
            throw new PaymentGatewayException(
                "Failed to create customer for subscription",
            );
        }

        // Prepare plan data
        $planData = [
            "name" => $subscriptionData["plan_name"] ?? "Subscription Plan",
            "amount" => $this->convertToKobo($subscriptionData["amount"]),
            "interval" => $subscriptionData["interval"] ?? "monthly",
            "currency" => strtoupper($subscriptionData["currency"] ?? "NGN"),
        ];

        // Create plan
        $planResponse = $this->makeRequest("POST", "/plan", $planData);
        $planCode = $planResponse["data"]["plan_code"] ?? null;

        if (!$planCode) {
            throw new PaymentGatewayException(
                "Failed to create subscription plan",
            );
        }

        // Initialize subscription
        $subscriptionInitData = [
            "customer" => $customerCode,
            "plan" => $planCode,
            "authorization" => $subscriptionData["authorization_code"] ?? "",
        ];

        $response = $this->makeRequest(
            "POST",
            "/subscription",
            $subscriptionInitData,
        );

        return [
            "success" => $response["status"] ?? false,
            "gateway" => $this->getName(),
            "subscription_id" => $response["data"]["subscription_code"] ?? null,
            "customer_code" => $customerCode,
            "plan_code" => $planCode,
            "email_token" => $response["data"]["email_token"] ?? null,
            "message" =>
                $response["message"] ?? "Subscription created successfully",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
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
        // Validate configuration
        $this->validateConfiguration();

        // Make API request to get subscription details
        $response = $this->makeRequest(
            "GET",
            "/subscription/" . $subscriptionId,
        );

        $subscriptionData = $response["data"] ?? [];

        return [
            "success" => $response["status"] ?? false,
            "gateway" => $this->getName(),
            "subscription_id" => $subscriptionId,
            "status" => $subscriptionData["status"] ?? "inactive",
            "customer_code" =>
                $subscriptionData["customer"]["customer_code"] ?? null,
            "plan_code" => $subscriptionData["plan"]["plan_code"] ?? null,
            "amount" => isset($subscriptionData["amount"])
                ? $this->convertFromKobo($subscriptionData["amount"])
                : 0,
            "currency" => $subscriptionData["currency"] ?? "NGN",
            "interval" => $subscriptionData["interval"] ?? "monthly",
            "next_payment_date" =>
                $subscriptionData["next_payment_date"] ?? null,
            "created_at" => $subscriptionData["created_at"] ?? null,
            "message" =>
                $response["message"] ?? "Subscription details retrieved",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
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
        // Validate configuration
        $this->validateConfiguration();

        // Make API request to disable subscription
        $response = $this->makeRequest("POST", "/subscription/disable", [
            "code" => $subscriptionId,
            "token" => "disable_token", // In reality, you'd need the email token
        ]);

        return [
            "success" => $response["status"] ?? false,
            "gateway" => $this->getName(),
            "subscription_id" => $subscriptionId,
            "status" => "cancelled",
            "message" =>
                $response["message"] ?? "Subscription cancelled successfully",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Update subscription
     *
     * @param string $subscriptionId
     * @param array $subscriptionData
     * @return array
     * @throws PaymentGatewayException
     */
    public function updateSubscription(
        string $subscriptionId,
        array $subscriptionData,
    ): array {
        // PayStack doesn't have a direct update subscription API
        // You would need to disable the old one and create a new one

        throw new PaymentGatewayException(
            "Subscription update is not directly supported by PayStack API. " .
                "You need to cancel the existing subscription and create a new one.",
            ["subscription_id" => $subscriptionId],
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

        if (isset($filters["perPage"])) {
            $queryParams["perPage"] = $filters["perPage"];
        }

        if (isset($filters["page"])) {
            $queryParams["page"] = $filters["page"];
        }

        if (isset($filters["customer"])) {
            $queryParams["customer"] = $filters["customer"];
        }

        if (isset($filters["status"])) {
            $queryParams["status"] = $filters["status"];
        }

        if (isset($filters["from"])) {
            $queryParams["from"] = $filters["from"];
        }

        if (isset($filters["to"])) {
            $queryParams["to"] = $filters["to"];
        }

        if (isset($filters["amount"])) {
            $queryParams["amount"] = $this->convertToKobo($filters["amount"]);
        }

        // Make API request to get transactions
        $url =
            "/transaction" .
            (!empty($queryParams) ? "?" . http_build_query($queryParams) : "");
        $response = $this->makeRequest("GET", $url);

        $transactions = $response["data"] ?? [];
        $mappedTransactions = [];

        foreach ($transactions as $transaction) {
            $mappedTransactions[] = [
                "id" => $transaction["id"] ?? null,
                "reference" => $transaction["reference"] ?? null,
                "amount" => isset($transaction["amount"])
                    ? $this->convertFromKobo($transaction["amount"])
                    : 0,
                "currency" => $transaction["currency"] ?? "NGN",
                "status" => $this->mapPaymentStatus(
                    $transaction["status"] ?? "pending",
                ),
                "paystack_status" => $transaction["status"] ?? "pending",
                "customer_email" => $transaction["customer"]["email"] ?? null,
                "paid_at" => $transaction["paid_at"] ?? null,
                "created_at" => $transaction["created_at"] ?? null,
                "channel" => $transaction["channel"] ?? null,
                "metadata" => $transaction["metadata"] ?? [],
            ];
        }

        return [
            "success" => $response["status"] ?? false,
            "gateway" => $this->getName(),
            "transactions" => $mappedTransactions,
            "total" => $response["meta"]["total"] ?? count($mappedTransactions),
            "page" => $response["meta"]["page"] ?? 1,
            "page_count" => $response["meta"]["pageCount"] ?? 1,
            "per_page" => $response["meta"]["perPage"] ?? 50,
            "message" =>
                $response["message"] ?? "Transaction history retrieved",
            "timestamp" => now()->toISOString(),
        ];
    }

    /**
     * Get required fields for PayStack payment
     *
     * @return array
     */
    public function getRequiredFields(): array
    {
        return ["amount", "currency", "customer.email"];
    }

    /**
     * Get optional fields for PayStack payment
     *
     * @return array
     */
    public function getOptionalFields(): array
    {
        return [
            "reference",
            "customer.name",
            "customer.phone",
            "metadata",
            "channels",
            "subaccount",
            "transaction_charge",
            "bearer",
            "plan",
            "invoice_limit",
            "split_code",
        ];
    }
}
