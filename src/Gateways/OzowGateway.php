<?php

namespace Lisosoft\PaymentGateway\Gateways;

use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OzowGateway extends AbstractGateway
{
    /**
     * Ozow API endpoints
     */
    const API_ENDPOINT_LIVE = "https://api.ozow.com";
    const API_ENDPOINT_SANDBOX = "https://api-sandbox.ozow.com";

    /**
     * Ozow payment statuses
     */
    const STATUS_COMPLETE = "Complete";
    const STATUS_PENDING = "Pending";
    const STATUS_CANCELLED = "Cancelled";
    const STATUS_ABANDONED = "Abandoned";
    const STATUS_ERROR = "Error";

    /**
     * Initialize the Ozow gateway
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->name = "ozow";
        $this->displayName = "Ozow";

        // Set supported currencies (Ozow primarily supports ZAR)
        $this->supportedCurrencies = ["ZAR"];

        // Set supported payment methods
        $this->paymentMethods = [
            "eft",
            "credit_card",
            "debit_card",
            "instant_eft",
            "scan_to_pay",
            "ozow_wallet",
        ];
    }

    /**
     * Get default configuration for Ozow
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            "enabled" => true,
            "site_code" => "",
            "private_key" => "",
            "api_key" => "",
            "test_mode" => true,
            "callback_url" => "/payment/callback/ozow",
            "error_url" => "/payment/error",
            "validate_signature" => true,
            "timeout" => 30,
            "retry_attempts" => 3,
            "retry_delay" => 100,
            "verify_ssl" => true,
            "minimum_amount" => 5.00,
            "maximum_amount" => 100000.00,
            "currency" => "ZAR",
            "reference_prefix" => "OZ",
            "logo_url" => "https://ozow.com/images/ozow-logo.svg",
            "documentation_url" => "https://developers.ozow.com",
            "error_messages" => [
                "001" => "Payment cancelled by user",
                "002" => "Payment declined",
                "003" => "Transaction expired",
                "004" => "Insufficient funds",
                "005" => "Invalid bank details",
                "006" => "Technical error",
                "007" => "Duplicate transaction",
                "008" => "Invalid merchant configuration",
                "009" => "Invalid payment data",
                "010" => "Payment method not supported",
            ],
        ];
    }

    /**
     * Get Ozow API endpoint based on test mode
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        return $this->isTestMode() ? self::API_ENDPOINT_SANDBOX : self::API_ENDPOINT_LIVE;
    }

    /**
     * Get default HTTP headers for Ozow
     *
     * @return array
     */
    protected function getDefaultHeaders(): array
    {
        $headers = parent::getDefaultHeaders();

        // Add Ozow authentication header
        $headers["ApiKey"] = $this->config["api_key"];
        $headers["Accept"] = "application/json";
        $headers["Content-Type"] = "application/json";

        return $headers;
    }

    /**
     * Process payment initialization for Ozow
     *
     * @param array $paymentData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processInitializePayment(array $paymentData): array
    {
        // Validate required configuration
        $this->validateConfiguration();

        // Prepare Ozow payment data
        $ozowData = $this->preparePaymentData($paymentData);

        // Generate signature
        $ozowData["hash"] = $this->generateSignature($ozowData);

        // Log payment initialization
        $this->logActivity("payment_initialized", [
            "payment_data" => $paymentData,
            "ozow_data" => $ozowData,
        ]);

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "transaction_id" => $ozowData["transactionReference"],
            "payment_url" => $this->getApiEndpoint() . "/payment/request",
            "payment_data" => $ozowData,
            "method" => "POST",
            "redirect_required" => true,
            "message" => "Ozow payment initialized successfully",
            "timestamp" => now()->toISOString(),
        ];
    }

    /**
     * Prepare payment data for Ozow
     *
     * @param array $paymentData
     * @return array
     */
    protected function preparePaymentData(array $paymentData): array
    {
        // Generate reference if not provided
        $reference = $paymentData["reference"] ?? $this->generateReference($paymentData);

        // Base Ozow parameters
        $ozowData = [
            "siteCode" => $this->config["site_code"],
            "countryCode" => "ZA",
            "currencyCode" => strtoupper($paymentData["currency"] ?? "ZAR"),
            "amount" => number_format($paymentData["amount"], 2, ".", ""),
            "transactionReference" => $reference,
            "bankReference" => $reference,
            "cancelUrl" => $this->buildUrl($this->config["error_url"], $reference),
            "errorUrl" => $this->buildUrl($this->config["error_url"], $reference),
            "successUrl" => $this->buildUrl($this->config["callback_url"], $reference),
            "notifyUrl" => $this->buildUrl($this->config["callback_url"], $reference),
            "isTest" => $this->isTestMode() ? "true" : "false",
        ];

        // Add customer details
        if (isset($paymentData["customer"])) {
            $ozowData["customer"] = [
                "emailAddress" => $paymentData["customer"]["email"] ?? "",
                "firstName" => $paymentData["customer"]["first_name"] ?? $paymentData["customer"]["name"] ?? "",
                "lastName" => $paymentData["customer"]["last_name"] ?? "",
                "mobileNumber" => $paymentData["customer"]["phone"] ?? "",
            ];
        }

        // Add optional parameters
        $optionalParams = [
            "optional1" => $paymentData["optional1"] ?? "",
            "optional2" => $paymentData["optional2"] ?? "",
            "optional3" => $paymentData["optional3"] ?? "",
            "optional4" => $paymentData["optional4"] ?? "",
            "optional5" => $paymentData["optional5"] ?? "",
            "merchantUserId" => $paymentData["merchant_user_id"] ?? "",
            "merchantInvoice" => $paymentData["merchant_invoice"] ?? "",
            "customerMessage" => $paymentData["customer_message"] ?? "",
            "paymentMethod" => $paymentData["payment_method"] ?? "",
            "sendEmail" => $paymentData["send_email"] ?? "true",
            "emailMessage" => $paymentData["email_message"] ?? "",
        ];

        // Filter out empty optional parameters
        foreach ($optionalParams as $key => $value) {
            if (!empty($value)) {
                $ozowData[$key] = $value;
            }
        }

        return $ozowData;
    }

    /**
     * Generate Ozow signature
     *
     * @param array $data
     * @return string
     */
    protected function generateSignature(array $data): string
    {
        // Remove hash and empty values
        unset($data["hash"]);
        $data = array_filter($data, function ($value) {
            return $value !== "" && $value !== null;
        });

        // Sort data alphabetically by key
        ksort($data);

        // Build parameter string
        $paramString = "";
        foreach ($data as $key => $value) {
            $paramString .= $key . "=" . $value . "&";
        }

        // Remove last '&'
        $paramString = rtrim($paramString, "&");

        // Add private key
        $paramString .= $this->config["private_key"];

        // Generate SHA512 hash
        return hash("sha512", $paramString);
    }

    /**
     * Process payment verification for Ozow
     *
     * @param string $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processVerifyPayment(string $transactionId): array
    {
        // Validate configuration
        $this->validateConfiguration();

        // Ozow doesn't have a direct verification API
        // We need to check the transaction status through webhook/callback
        // For now, we'll return a pending status and rely on webhooks

        $this->logActivity("payment_verification_attempt", [
            "transaction_id" => $transactionId,
        ]);

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "status" => "pending",
            "message" => "Payment verification initiated. Status will be updated via webhook.",
            "timestamp" => now()->toISOString(),
            "note" => "Ozow requires webhook/callback for final payment status",
        ];
    }

    /**
     * Process callback data from Ozow
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
        $transactionId = $callbackData["TransactionReference"] ?? "";
        $status = $callbackData["Status"] ?? "";
        $amount = $callbackData["Amount"] ?? 0;
        $currency = $callbackData["CurrencyCode"] ?? "ZAR";

        // Map Ozow status to our status
        $gatewayStatus = $this->mapPaymentStatus($status);

        // Prepare response
        $response = [
            "success" => $gatewayStatus === "completed",
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "status" => $gatewayStatus,
            "ozow_status" => $status,
            "amount" => (float) $amount,
            "currency" => $currency,
            "raw_data" => $callbackData,
            "timestamp" => now()->toISOString(),
        ];

        // Add additional details if available
        if (isset($callbackData["BankReference"])) {
            $response["bank_reference"] = $callbackData["BankReference"];
        }

        if (isset($callbackData["PaymentMethod"])) {
            $response["payment_method"] = $callbackData["PaymentMethod"];
        }

        if (isset($callbackData["Customer"])) {
            $response["customer"] = $callbackData["Customer"];
        }

        // Log callback processing
        $this->logActivity("callback_processed", $response);

        return $response;
    }

    /**
     * Validate callback signature for Ozow
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
        $receivedSignature = $callbackData["Hash"] ?? "";

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
     * Process refund payment for Ozow
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

        // Ozow refunds are typically manual through their dashboard
        // This is a placeholder for potential API integration

        $this->logActivity("refund_requested", [
            "transaction_id" => $transactionId,
            "amount" => $amount,
        ]);

        return [
            "success" => false,
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "status" => "manual_refund_required",
            "message" => "Refunds for Ozow must be processed manually through the Ozow dashboard",
            "timestamp" => now()->toISOString(),
            "note" => "Contact Ozow support or use their merchant dashboard to process refunds",
        ];
    }

    /**
     * Map Ozow payment status to our status
     *
     * @param string $ozowStatus
     * @return string
     */
    protected function mapPaymentStatus(string $ozowStatus): string
    {
        $statusMap = [
            "Complete" => "completed",
            "Pending" => "pending",
            "Cancelled" => "cancelled",
            "Abandoned" => "cancelled",
            "Error" => "failed",
            "Failed" => "failed",
            "Expired" => "expired",
        ];

        return $statusMap[$ozowStatus] ?? "unknown";
    }

    /**
     * Build URL with reference parameter
     *
     * @param string $url
     * @param string $reference
     * @return string
     */
    protected function buildUrl(string $url, string $reference): string
    {
        // If URL is absolute, return as is
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        // Add reference as query parameter
        $separator = strpos($url, "?") === false ? "?" : "&";
        return $url . $separator . "reference=" . urlencode($reference);
    }

    /**
     * Validate Ozow configuration
     *
     * @return void
     * @throws PaymentGatewayException
     */
    protected function validateConfiguration(): void
    {
        $missingConfig = [];

        // Check required configuration
        $requiredConfig = ["site_code", "private_key", "api_key"];
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
        // Ozow doesn't support subscriptions natively
        // This would need to be implemented with recurring payments

        throw new PaymentGatewayException(
            "Subscriptions are not supported by Ozow. Consider using recurring payments with manual initiation.",
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
            "Subscriptions are not supported by Ozow",
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
            "Subscriptions are not supported by Ozow",
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
            "Subscriptions are not supported by Ozow",
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
        // Ozow doesn't have a transaction history API
        // This would need to be tracked in your own database

        throw new PaymentGatewayException(
            "Transaction history is not available via Ozow API. Track transactions in your application database.",
            ["filters" => $filters]
        );
    }

    /**
     * Get required fields for Ozow payment
     *
     * @return array
     */
    public function getRequiredFields(): array
    {
        return [
            "amount",
            "currency",
            "customer.email",
        ];
    }

    /**
     * Get optional fields for Ozow payment
     *
     * @return array
     */
    public function getOptionalFields(): array
    {
        return [
            "reference",
            "customer.first_name",
            "customer.last_name",
            "customer.name",
            "customer.phone",
            "payment_method",
            "optional1",
            "optional2",
            "optional3",
            "optional4",
            "optional5",
            "merchant_user_id",
            "merchant_invoice",
            "customer_message",
            "send_email",
            "email_message",
        ];
    }
}
