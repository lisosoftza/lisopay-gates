<?php

namespace Lisosoft\PaymentGateway\Gateways;

use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CryptoGateway extends AbstractGateway
{
    /**
     * Crypto API endpoints (using Coinbase Commerce as default)
     */
    const API_ENDPOINT_LIVE = "https://api.commerce.coinbase.com";
    const API_ENDPOINT_SANDBOX = "https://api.commerce.coinbase.com";

    /**
     * Crypto payment statuses
     */
    const STATUS_NEW = "NEW";
    const STATUS_PENDING = "PENDING";
    const STATUS_COMPLETED = "COMPLETED";
    const STATUS_EXPIRED = "EXPIRED";
    const STATUS_UNRESOLVED = "UNRESOLVED";
    const STATUS_RESOLVED = "RESOLVED";

    /**
     * Initialize the Crypto gateway
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->name = "crypto";
        $this->displayName = "Cryptocurrency";

        // Set supported cryptocurrencies
        $this->supportedCurrencies = ["BTC", "ETH", "USDT", "USDC", "LTC", "BCH", "DOGE", "XRP"];

        // Set supported payment methods (cryptocurrencies)
        $this->paymentMethods = [
            "bitcoin",
            "ethereum",
            "tether",
            "usd_coin",
            "litecoin",
            "bitcoin_cash",
            "dogecoin",
            "ripple",
        ];
    }

    /**
     * Get default configuration for Crypto
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            "enabled" => true,
            "provider" => "coinbase", // coinbase, binance, etc.
            "api_key" => "",
            "api_secret" => "",
            "webhook_secret" => "",
            "test_mode" => true,
            "validate_signature" => true,
            "timeout" => 30,
            "retry_attempts" => 3,
            "retry_delay" => 100,
            "verify_ssl" => true,
            "minimum_amount" => 1.00,
            "maximum_amount" => 100000.00,
            "currency" => "USD",
            "reference_prefix" => "CRYPTO",
            "logo_url" => "https://crypto.com/images/crypto-logo.png",
            "documentation_url" => "https://commerce.coinbase.com/docs",
            "error_messages" => [
                "invalid_api_key" => "Invalid API key",
                "insufficient_funds" => "Insufficient cryptocurrency balance",
                "network_congestion" => "Network congestion, transaction may be delayed",
                "invalid_address" => "Invalid cryptocurrency address",
                "transaction_timeout" => "Transaction timed out",
                "wallet_error" => "Wallet error occurred",
                "exchange_rate_error" => "Exchange rate error",
                "unsupported_currency" => "Unsupported cryptocurrency",
            ],
        ];
    }

    /**
     * Get Crypto API endpoint
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        return self::API_ENDPOINT_LIVE; // Coinbase uses same endpoint for test/live
    }

    /**
     * Get default HTTP headers for Crypto
     *
     * @return array
     */
    protected function getDefaultHeaders(): array
    {
        $headers = parent::getDefaultHeaders();

        // Add Crypto authentication header
        $headers["X-CC-Api-Key"] = $this->config["api_key"];
        $headers["X-CC-Version"] = "2018-03-22";

        return $headers;
    }

    /**
     * Process payment initialization for Crypto
     *
     * @param array $paymentData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processInitializePayment(array $paymentData): array
    {
        // Validate required configuration
        $this->validateConfiguration();

        // Prepare Crypto payment data
        $cryptoData = $this->preparePaymentData($paymentData);

        // Make API request to create charge
        $response = $this->makeRequest("POST", "/charges", $cryptoData);

        // Log payment initialization
        $this->logActivity("payment_initialized", [
            "payment_data" => $paymentData,
            "crypto_data" => $cryptoData,
            "response" => $response,
        ]);

        $chargeData = $response["data"] ?? [];

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "transaction_id" => $chargeData["id"] ?? "",
            "gateway_transaction_id" => $chargeData["id"] ?? "",
            "payment_url" => $chargeData["hosted_url"] ?? "",
            "addresses" => $chargeData["addresses"] ?? [],
            "pricing" => $chargeData["pricing"] ?? [],
            "expires_at" => $chargeData["expires_at"] ?? null,
            "status" => strtolower($chargeData["timeline"][0]["status"] ?? "new"),
            "redirect_required" => true,
            "message" => "Cryptocurrency payment initialized successfully",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Prepare payment data for Crypto
     *
     * @param array $paymentData
     * @return array
     */
    protected function preparePaymentData(array $paymentData): array
    {
        // Generate reference if not provided
        $reference = $paymentData["reference"] ?? $this->generateReference($paymentData);

        // Base Crypto parameters
        $cryptoData = [
            "name" => $paymentData["description"] ?? "Payment",
            "description" => $paymentData["item_description"] ?? $paymentData["description"] ?? "Payment",
            "pricing_type" => "fixed_price",
            "local_price" => [
                "amount" => number_format($paymentData["amount"], 2, ".", ""),
                "currency" => strtoupper($paymentData["currency"] ?? "USD"),
            ],
            "metadata" => [
                "reference" => $reference,
                "customer_email" => $paymentData["customer"]["email"] ?? "",
                "customer_name" => $paymentData["customer"]["name"] ?? "",
            ],
        ];

        // Add customer information
        if (isset($paymentData["customer"])) {
            $cryptoData["metadata"]["customer"] = json_encode($paymentData["customer"]);
        }

        // Add redirect URLs
        $cryptoData["redirect_url"] = $this->buildUrl(
            config("payment-gateway.transaction.return_url", "/payment/success"),
            $reference
        );
        $cryptoData["cancel_url"] = $this->buildUrl(
            config("payment-gateway.transaction.cancel_url", "/payment/cancel"),
            $reference
        );

        // Add webhook URL if configured
        $webhookUrl = config("payment-gateway.gateways.crypto.webhook_url");
        if ($webhookUrl) {
            $cryptoData["webhook_url"] = $this->buildUrl($webhookUrl, $reference);
        }

        // Add requested currencies if specified
        if (isset($paymentData["requested_currencies"])) {
            $cryptoData["requested_info"] = ["currencies"];
        }

        // Add metadata from payment data
        if (isset($paymentData["metadata"])) {
            $cryptoData["metadata"] = array_merge($cryptoData["metadata"], $paymentData["metadata"]);
        }

        return $cryptoData;
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
     * Process payment verification for Crypto
     *
     * @param string $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processVerifyPayment(string $transactionId): array
    {
        // Validate configuration
        $this->validateConfiguration();

        // Make API request to get charge details
        $response = $this->makeRequest("GET", "/charges/" . $transactionId);

        // Log verification attempt
        $this->logActivity("payment_verification_attempt", [
            "transaction_id" => $transactionId,
            "response" => $response,
        ]);

        $chargeData = $response["data"] ?? [];
        $timeline = $chargeData["timeline"] ?? [];
        $latestStatus = end($timeline);
        $status = $latestStatus["status"] ?? "NEW";
        $gatewayStatus = $this->mapPaymentStatus($status);

        $result = [
            "success" => $gatewayStatus === "completed",
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "gateway_transaction_id" => $transactionId,
            "status" => $gatewayStatus,
            "crypto_status" => $status,
            "amount" => $chargeData["pricing"]["local"]["amount"] ?? 0,
            "currency" => $chargeData["pricing"]["local"]["currency"] ?? "USD",
            "crypto_amount" => $chargeData["pricing"]["crypto"]["amount"] ?? 0,
            "crypto_currency" => $chargeData["pricing"]["crypto"]["currency"] ?? "BTC",
            "addresses" => $chargeData["addresses"] ?? [],
            "timeline" => $timeline,
            "expires_at" => $chargeData["expires_at"] ?? null,
            "created_at" => $chargeData["created_at"] ?? null,
            "metadata" => $chargeData["metadata"] ?? [],
            "message" => "Payment verification completed",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];

        // Add payment details if available
        if (isset($chargeData["payments"])) {
            $result["payments"] = $chargeData["payments"];
            $result["total_paid"] = $chargeData["payments"][0]["value"]["crypto"]["amount"] ?? 0;
            $result["total_paid_currency"] = $chargeData["payments"][0]["value"]["crypto"]["currency"] ?? "BTC";
        }

        return $result;
    }

    /**
     * Process callback data from Crypto webhook
     *
     * @param array $callbackData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processCallbackData(array $callbackData): array
    {
        // Validate webhook signature
        $this->validateWebhookSignature($callbackData);

        // Extract event data
        $event = $callbackData["event"] ?? [];
        $chargeData = $event["data"] ?? [];

        // Map Crypto event to our callback data
        $result = $this->mapCryptoEventToCallbackData($event, $chargeData);

        // Log callback processing
        $this->logActivity("callback_processed", array_merge(["event_type" => $event["type"] ?? ""], $result));

        return $result;
    }

    /**
     * Validate Crypto webhook signature
     *
     * @param array $callbackData
     * @return bool
     * @throws PaymentGatewayException
     */
    protected function validateWebhookSignature(array $callbackData): bool
    {
        if (!($this->config["validate_signature"] ?? true)) {
            return true;
        }

        $webhookSecret = $this->config["webhook_secret"] ?? "";
        if (empty($webhookSecret)) {
            throw PaymentGatewayException::invalidSignature(
                $this->getName(),
                "Missing webhook secret for signature validation"
            );
        }

        // In a real implementation, you would validate the Crypto webhook signature
        // using the raw request body and signature header
        // This is a simplified version

        // For development/test environments, we can skip validation
        if (app()->environment(["local", "testing", "staging"])) {
            return true;
        }

        // In production, implement proper webhook signature validation
        // using the webhook signing secret
        return true;
    }

    /**
     * Map Crypto event to callback data
     *
     * @param array $event
     * @param array $chargeData
     * @return array
     */
    protected function mapCryptoEventToCallbackData(array $event, array $chargeData): array
    {
        $result = [
            "event_type" => $event["type"] ?? "",
            "gateway" => $this->getName(),
            "timestamp" => now()->toISOString(),
            "raw_data" => $chargeData,
        ];

        $eventType = $event["type"] ?? "";
        $chargeId = $chargeData["id"] ?? "";
        $timeline = $chargeData["timeline"] ?? [];
        $latestStatus = end($timeline);
        $status = $latestStatus["status"] ?? "NEW";
        $gatewayStatus = $this->mapPaymentStatus($status);

        switch ($eventType) {
            case "charge:created":
                $result["transaction_id"] = $chargeId;
                $result["status"] = "created";
                $result["message"] = "Cryptocurrency charge created";
                break;

            case "charge:confirmed":
                $result["transaction_id"] = $chargeId;
                $result["status"] = "completed";
                $result["amount"] = $chargeData["pricing"]["local"]["amount"] ?? 0;
                $result["currency"] = $chargeData["pricing"]["local"]["currency"] ?? "USD";
                $result["crypto_amount"] = $chargeData["pricing"]["crypto"]["amount"] ?? 0;
                $result["crypto_currency"] = $chargeData["pricing"]["crypto"]["currency"] ?? "BTC";
                $result["message"] = "Cryptocurrency payment confirmed";
                break;

            case "charge:failed":
                $result["transaction_id"] = $chargeId;
                $result["status"] = "failed";
                $result["error_message"] = "Cryptocurrency payment failed";
                $result["message"] = "Cryptocurrency payment failed";
                break;

            case "charge:delayed":
                $result["transaction_id"] = $chargeId;
                $result["status"] = "pending";
                $result["message"] = "Cryptocurrency payment delayed";
                break;

            case "charge:pending":
                $result["transaction_id"] = $chargeId;
                $result["status"] = "pending";
                $result["message"] = "Cryptocurrency payment pending";
                break;

            case "charge:resolved":
                $result["transaction_id"] = $chargeId;
                $result["status"] = "resolved";
                $result["message"] = "Cryptocurrency payment resolved";
                break;

            default:
                $result["transaction_id"] = $chargeId;
                $result["status"] = $gatewayStatus;
                $result["message"] = "Cryptocurrency event: " . $eventType;
                break;
        }

        // Add payment details if available
        if (isset($chargeData["payments"])) {
            $result["payments"] = $chargeData["payments"];
            $result["total_paid"] = $chargeData["payments"][0]["value"]["crypto"]["amount"] ?? 0;
            $result["total_paid_currency"] = $chargeData["payments"][0]["value"]["crypto"]["currency"] ?? "BTC";
        }

        return $result;
    }

    /**
     * Process refund payment for Crypto
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processRefundPayment(string $transactionId, ?float $amount = null): array
    {
        // Cryptocurrency transactions are generally non-refundable
        // Refunds would need to be processed manually by sending cryptocurrency back

        $this->logActivity("refund_requested", [
            "transaction_id" => $transactionId,
            "amount" => $amount,
        ]);

        return [
            "success" => false,
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "status" => "manual_refund_required",
            "message" => "Cryptocurrency refunds must be processed manually by sending cryptocurrency back to the original sender",
            "timestamp" => now()->toISOString(),
            "note" => "Contact the customer to obtain their cryptocurrency wallet address for refund",
        ];
    }

    /**
     * Map Crypto payment status to our status
     *
     * @param string $cryptoStatus
     * @return string
     */
    protected function mapPaymentStatus(string $cryptoStatus): string
    {
        $statusMap = [
            "NEW" => "created",
            "PENDING" => "pending",
            "COMPLETED" => "completed",
            "EXPIRED" => "expired",
            "UNRESOLVED" => "failed",
            "RESOLVED" => "resolved",
            "OVERPAID" => "completed",
            "UNDERPAID" => "pending",
            "DELAYED" => "pending",
            "MULTIPLE" => "pending",
        ];

        return $statusMap[strtoupper($cryptoStatus)] ?? "unknown";
    }

    /**
     * Validate Crypto configuration
     *
     * @return void
     * @throws PaymentGatewayException
     */
    protected function validateConfiguration(): void
    {
        $missingConfig = [];

        // Check required configuration
        $requiredConfig = ["api_key"];
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
        // Cryptocurrency doesn't support native subscriptions
        // This would need to be implemented with recurring invoices

        throw new PaymentGatewayException(
            "Subscriptions are not natively supported for cryptocurrency payments. Consider using recurring invoices.",
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
            "Subscriptions are not supported for cryptocurrency payments",
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
            "Subscriptions are not supported for cryptocurrency payments",
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
            "Subscriptions are not supported for cryptocurrency payments",
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

        if (isset($filters["starting_after"])) {
            $queryParams["starting_after"] = $filters["starting_after"];
        }

        if (isset($filters["ending_before"])) {
            $queryParams["ending_before"] = $filters["ending_before"];
        }

        if (isset($filters["order"])) {
            $queryParams["order"] = $filters["order"];
        }

        // Make API request to get charges
        $url = "/charges" . (!empty($queryParams) ? "?" . http_build_query($queryParams) : "");
        $response = $this->makeRequest("GET", $url);

        $charges = $response["data"] ?? [];
        $mappedTransactions = [];

        foreach ($charges as $charge) {
            $timeline = $charge["timeline"] ?? [];
            $latestStatus = end($timeline);
            $status = $latestStatus["status"] ?? "NEW";

            $mappedTransactions[] = [
                "id" => $charge["id"] ?? null,
                "reference" => $charge["metadata"]["reference"] ?? null,
                "amount" => $charge["pricing"]["local"]["amount"] ?? 0,
                "currency" => $charge["pricing"]["local"]["currency"] ?? "USD",
                "crypto_amount" => $charge["pricing"]["crypto"]["amount"] ?? 0,
                "crypto_currency" => $charge["pricing"]["crypto"]["currency"] ?? "BTC",
                "status" => $this->mapPaymentStatus($status),
                "crypto_status" => $status,
                "customer_email" => $charge["metadata"]["customer_email"] ?? null,
                "created_at" => $charge["created_at"] ?? null,
                "expires_at" => $charge["expires_at"] ?? null,
                "addresses" => $charge["addresses"] ?? [],
                "metadata" => $charge["metadata"] ?? [],
            ];
        }

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "transactions" => $mappedTransactions,
            "pagination" => $response["pagination"] ?? [],
            "total" => count($mappedTransactions),
            "message" => "Transaction history retrieved",
            "timestamp" => now()->toISOString(),
        ];
    }

    /**
     * Get exchange rate for cryptocurrency
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param float $amount
     * @return array
     * @throws PaymentGatewayException
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency, float $amount = 1.0): array
    {
        // This is a simplified implementation
        // In production, you would call a cryptocurrency exchange rate API

        $exchangeRates = [
            "USD_BTC" => 0.000025, // 1 USD = 0.000025 BTC (example rate)
            "USD_ETH" => 0.0004,   // 1 USD = 0.0004 ETH (example rate)
            "USD_USDT" => 1.0,     // 1 USD = 1 USDT
            "USD_USDC" => 1.0,     // 1 USD = 1 USDC
        ];

        $rateKey = strtoupper($fromCurrency . "_" . $toCurrency);
        $rate = $exchangeRates[$rateKey] ?? 0;

        return [
            "success" => $rate > 0,
            "from_currency" => $fromCurrency,
            "to_currency" => $toCurrency,
            "amount" => $amount,
            "exchange_rate" => $rate,
            "converted_amount" => $amount * $rate,
            "timestamp" => now()->toISOString(),
        ];
    }

    /**
     * Get required fields for Crypto payment
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
     * Get optional fields for Crypto payment
     *
     * @return array
     */
    public function getOptionalFields(): array
    {
        return [
            "reference",
            "customer.email",
            "customer.name",
            "item_description",
            "metadata",
            "requested_currencies",
            "redirect_url",
            "cancel_url",
            "webhook_url",
        ];
    }
}
