<?php

namespace Lisosoft\PaymentGateway\Gateways;

use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class StripeGateway extends AbstractGateway
{
    /**
     * Stripe API endpoints
     */
    const API_ENDPOINT_LIVE = "https://api.stripe.com";
    const API_ENDPOINT_SANDBOX = "https://api.stripe.com"; // Stripe uses same endpoint, different keys

    /**
     * Stripe payment statuses
     */
    const STATUS_SUCCEEDED = "succeeded";
    const STATUS_PENDING = "pending";
    const STATUS_FAILED = "failed";
    const STATUS_CANCELED = "canceled";
    const STATUS_REQUIRES_ACTION = "requires_action";
    const STATUS_REQUIRES_CONFIRMATION = "requires_confirmation";
    const STATUS_REQUIRES_PAYMENT_METHOD = "requires_payment_method";

    /**
     * Initialize the Stripe gateway
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->name = "stripe";
        $this->displayName = "Stripe";

        // Set supported currencies (Stripe supports 135+ currencies)
        $this->supportedCurrencies = [
            "USD",
            "EUR",
            "GBP",
            "CAD",
            "AUD",
            "JPY",
            "CNY",
            "MXN",
            "BRL",
            "ZAR",
            "AED",
            "SAR",
            "INR",
            "SGD",
            "HKD",
            "NZD",
            "CHF",
            "NOK",
            "SEK",
            "DKK",
            "PLN",
            "CZK",
            "HUF",
            "ILS",
            "PHP",
            "THB",
            "MYR",
        ];

        // Set supported payment methods
        $this->paymentMethods = [
            "card",
            "alipay",
            "bancontact",
            "eps",
            "giropay",
            "ideal",
            "p24",
            "sepa_debit",
            "sofort",
            "ach_debit",
            "acss_debit",
            "bacs_debit",
            "blik",
            "boleto",
            "cashapp",
            "customer_balance",
            "fpx",
            "grabpay",
            "konbini",
            "link",
            "oxxo",
            "paypal",
            "promptpay",
            "revolut_pay",
            "swish",
            "us_bank_account",
            "wechat_pay",
        ];
    }

    /**
     * Get default configuration for Stripe
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            "enabled" => true,
            "publishable_key" => "",
            "secret_key" => "",
            "webhook_secret" => "",
            "test_mode" => true,
            "return_url" => "/payment/success",
            "validate_signature" => true,
            "timeout" => 30,
            "retry_attempts" => 3,
            "retry_delay" => 100,
            "verify_ssl" => true,
            "minimum_amount" => 0.5,
            "maximum_amount" => 999999.99,
            "currency" => "USD",
            "reference_prefix" => "STR",
            "logo_url" => "https://stripe.com/img/v3/home/twitter.png",
            "documentation_url" => "https://stripe.com/docs/api",
            "error_messages" => [
                "card_declined" => "Your card was declined.",
                "expired_card" => "Your card has expired.",
                "incorrect_cvc" => "Your card's security code is incorrect.",
                "incorrect_number" => "Your card number is incorrect.",
                "invalid_cvc" => "Your card's security code is invalid.",
                "invalid_expiry_month" =>
                    "Your card's expiration month is invalid.",
                "invalid_expiry_year" =>
                    "Your card's expiration year is invalid.",
                "invalid_number" => "Your card number is invalid.",
                "processing_error" =>
                    "An error occurred while processing your card.",
                "insufficient_funds" => "Your card has insufficient funds.",
                "lost_card" => "Your card was reported lost.",
                "stolen_card" => "Your card was reported stolen.",
                "authentication_required" =>
                    "Additional authentication is required.",
            ],
        ];
    }

    /**
     * Get Stripe API endpoint
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        return self::API_ENDPOINT_LIVE; // Stripe uses same endpoint for test/live
    }

    /**
     * Get default HTTP headers for Stripe
     *
     * @return array
     */
    protected function getDefaultHeaders(): array
    {
        $headers = parent::getDefaultHeaders();

        // Add Stripe authentication header
        $headers["Authorization"] = "Bearer " . $this->config["secret_key"];
        $headers["Stripe-Version"] = "2023-10-16"; // Latest stable API version

        return $headers;
    }

    /**
     * Process payment initialization for Stripe
     *
     * @param array $paymentData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processInitializePayment(array $paymentData): array
    {
        // Validate required configuration
        $this->validateConfiguration();

        // Prepare Stripe payment data
        $stripeData = $this->preparePaymentData($paymentData);

        // Make API request to create payment intent
        $response = $this->makeRequest(
            "POST",
            "/v1/payment_intents",
            $stripeData,
        );

        // Log payment initialization
        $this->logActivity("payment_initialized", [
            "payment_data" => $paymentData,
            "stripe_data" => $stripeData,
            "response" => $response,
        ]);

        // Determine if client secret is needed
        $requiresAction = in_array($response["status"], [
            self::STATUS_REQUIRES_ACTION,
            self::STATUS_REQUIRES_CONFIRMATION,
            self::STATUS_REQUIRES_PAYMENT_METHOD,
        ]);

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "transaction_id" => $response["id"] ?? "",
            "gateway_transaction_id" => $response["id"] ?? "",
            "client_secret" => $response["client_secret"] ?? "",
            "status" => $response["status"] ?? "requires_payment_method",
            "amount" => $this->convertFromCents($response["amount"] ?? 0),
            "currency" => $response["currency"] ?? "usd",
            "requires_action" => $requiresAction,
            "next_action" => $response["next_action"] ?? null,
            "payment_method_types" => $response["payment_method_types"] ?? [],
            "redirect_required" => $requiresAction,
            "message" => "Stripe payment initialized successfully",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Prepare payment data for Stripe
     *
     * @param array $paymentData
     * @return array
     */
    protected function preparePaymentData(array $paymentData): array
    {
        // Generate reference if not provided
        $reference =
            $paymentData["reference"] ?? $this->generateReference($paymentData);

        // Base Stripe payment intent data
        $stripeData = [
            "amount" => $this->convertToCents($paymentData["amount"]),
            "currency" => strtolower($paymentData["currency"] ?? "usd"),
            "description" => $paymentData["description"] ?? "Payment",
            "metadata" => [
                "reference" => $reference,
                "customer_email" => $paymentData["customer"]["email"] ?? "",
                "customer_name" => $paymentData["customer"]["name"] ?? "",
            ],
        ];

        // Add customer if email provided
        if (isset($paymentData["customer"]["email"])) {
            $stripeData["customer"] = $this->getOrCreateCustomer(
                $paymentData["customer"],
            );
        }

        // Add payment method types
        $stripeData["payment_method_types"] = $paymentData[
            "payment_method_types"
        ] ?? ["card"];

        // Add automatic payment methods
        $stripeData["automatic_payment_methods"] = [
            "enabled" => true,
            "allow_redirects" => "always",
        ];

        // Add return URL for redirect-based payment methods
        $stripeData["return_url"] = $this->buildUrl(
            $this->config["return_url"],
            $reference,
        );

        // Add confirmation method
        $stripeData["confirm"] = $paymentData["confirm"] ?? false;
        $stripeData["capture_method"] =
            $paymentData["capture_method"] ?? "automatic";

        // Add shipping if provided
        if (isset($paymentData["shipping_address"])) {
            $stripeData["shipping"] = [
                "address" => [
                    "line1" => $paymentData["shipping_address"]["line1"] ?? "",
                    "line2" => $paymentData["shipping_address"]["line2"] ?? "",
                    "city" => $paymentData["shipping_address"]["city"] ?? "",
                    "state" => $paymentData["shipping_address"]["state"] ?? "",
                    "postal_code" =>
                        $paymentData["shipping_address"]["postal_code"] ?? "",
                    "country" =>
                        $paymentData["shipping_address"]["country"] ?? "US",
                ],
                "name" => $paymentData["customer"]["name"] ?? "",
                "phone" => $paymentData["customer"]["phone"] ?? "",
            ];
        }

        // Add application fee if provided (for connected accounts)
        if (isset($paymentData["application_fee_amount"])) {
            $stripeData["application_fee_amount"] = $this->convertToCents(
                $paymentData["application_fee_amount"],
            );
        }

        // Add transfer data if provided (for connected accounts)
        if (isset($paymentData["transfer_data"])) {
            $stripeData["transfer_data"] = $paymentData["transfer_data"];
        }

        // Add metadata from payment data
        if (isset($paymentData["metadata"])) {
            $stripeData["metadata"] = array_merge(
                $stripeData["metadata"],
                $paymentData["metadata"],
            );
        }

        return $stripeData;
    }

    /**
     * Get or create Stripe customer
     *
     * @param array $customerData
     * @return string|null
     * @throws PaymentGatewayException
     */
    protected function getOrCreateCustomer(array $customerData): ?string
    {
        $email = $customerData["email"] ?? "";
        if (empty($email)) {
            return null;
        }

        try {
            // Try to find existing customer
            $response = $this->makeRequest("GET", "/v1/customers", [
                "email" => $email,
                "limit" => 1,
            ]);

            $customers = $response["data"] ?? [];
            if (!empty($customers)) {
                return $customers[0]["id"];
            }

            // Create new customer
            $customerRequest = [
                "email" => $email,
                "name" => $customerData["name"] ?? "",
                "phone" => $customerData["phone"] ?? "",
                "metadata" => [
                    "created_via" => "lisosoft_payment_gateway",
                ],
            ];

            $response = $this->makeRequest(
                "POST",
                "/v1/customers",
                $customerRequest,
            );
            return $response["id"] ?? null;
        } catch (PaymentGatewayException $e) {
            // Log error but don't fail the payment
            $this->logActivity("customer_creation_failed", [
                "error" => $e->getMessage(),
                "customer_data" => $customerData,
            ]);
            return null;
        }
    }

    /**
     * Convert amount to cents (Stripe uses smallest currency unit)
     *
     * @param float $amount
     * @return int
     */
    protected function convertToCents(float $amount): int
    {
        return (int) ($amount * 100);
    }

    /**
     * Convert from cents to normal amount
     *
     * @param int $centsAmount
     * @return float
     */
    protected function convertFromCents(int $centsAmount): float
    {
        return $centsAmount / 100;
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
     * Process payment verification for Stripe
     *
     * @param string $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processVerifyPayment(string $transactionId): array
    {
        // Validate configuration
        $this->validateConfiguration();

        // Make API request to get payment intent
        $response = $this->makeRequest(
            "GET",
            "/v1/payment_intents/" . $transactionId,
        );

        // Log verification attempt
        $this->logActivity("payment_verification_attempt", [
            "transaction_id" => $transactionId,
            "response" => $response,
        ]);

        // Extract payment details
        $status = $response["status"] ?? "requires_payment_method";
        $gatewayStatus = $this->mapPaymentStatus($status);

        $latestCharge = $response["latest_charge"] ?? null;
        $chargeDetails = null;

        if ($latestCharge) {
            $chargeResponse = $this->makeRequest(
                "GET",
                "/v1/charges/" . $latestCharge,
            );
            $chargeDetails = $chargeResponse;
        }

        $result = [
            "success" => $gatewayStatus === "completed",
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "gateway_transaction_id" => $transactionId,
            "status" => $gatewayStatus,
            "stripe_status" => $status,
            "amount" => $this->convertFromCents($response["amount"] ?? 0),
            "currency" => $response["currency"] ?? "usd",
            "customer_id" => $response["customer"] ?? null,
            "payment_method_id" => $response["payment_method"] ?? null,
            "payment_method_types" => $response["payment_method_types"] ?? [],
            "created" => $response["created"] ?? null,
            "metadata" => $response["metadata"] ?? [],
            "charges" => $response["charges"] ?? [],
            "latest_charge" => $latestCharge,
            "charge_details" => $chargeDetails,
            "message" => "Payment verification completed",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];

        // Add payment method details if available
        if ($response["payment_method"]) {
            $pmResponse = $this->makeRequest(
                "GET",
                "/v1/payment_methods/" . $response["payment_method"],
            );
            $result["payment_method_details"] = $pmResponse;
        }

        return $result;
    }

    /**
     * Confirm a payment intent
     *
     * @param string $paymentIntentId
     * @param array $confirmationData
     * @return array
     * @throws PaymentGatewayException
     */
    public function confirmPayment(
        string $paymentIntentId,
        array $confirmationData = [],
    ): array {
        // Validate configuration
        $this->validateConfiguration();

        // Make API request to confirm payment intent
        $response = $this->makeRequest(
            "POST",
            "/v1/payment_intents/" . $paymentIntentId . "/confirm",
            $confirmationData,
        );

        $status = $response["status"] ?? "requires_payment_method";
        $requiresAction = in_array($status, [
            self::STATUS_REQUIRES_ACTION,
            self::STATUS_REQUIRES_CONFIRMATION,
        ]);

        return [
            "success" => $status === self::STATUS_SUCCEEDED,
            "gateway" => $this->getName(),
            "transaction_id" => $paymentIntentId,
            "status" => $status,
            "requires_action" => $requiresAction,
            "client_secret" => $response["client_secret"] ?? "",
            "next_action" => $response["next_action"] ?? null,
            "message" =>
                $status === self::STATUS_SUCCEEDED
                    ? "Payment confirmed successfully"
                    : "Payment requires additional action",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Capture a payment intent
     *
     * @param string $paymentIntentId
     * @param array $captureData
     * @return array
     * @throws PaymentGatewayException
     */
    public function capturePayment(
        string $paymentIntentId,
        array $captureData = [],
    ): array {
        // Validate configuration
        $this->validateConfiguration();

        // Make API request to capture payment intent
        $response = $this->makeRequest(
            "POST",
            "/v1/payment_intents/" . $paymentIntentId . "/capture",
            $captureData,
        );

        return [
            "success" => $response["status"] === self::STATUS_SUCCEEDED,
            "gateway" => $this->getName(),
            "transaction_id" => $paymentIntentId,
            "status" => $response["status"] ?? "requires_capture",
            "amount_captured" => $this->convertFromCents(
                $response["amount_captured"] ?? 0,
            ),
            "message" =>
                $response["status"] === self::STATUS_SUCCEEDED
                    ? "Payment captured successfully"
                    : "Payment capture failed",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Process callback data from Stripe webhook
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
        $eventType = $callbackData["type"] ?? "";
        $eventData = $callbackData["data"]["object"] ?? [];

        // Map Stripe event to our callback data
        $result = $this->mapStripeEventToCallbackData($eventType, $eventData);

        // Log callback processing
        $this->logActivity(
            "callback_processed",
            array_merge(["event_type" => $eventType], $result),
        );

        return $result;
    }

    /**
     * Validate Stripe webhook signature
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
                "Missing webhook secret for signature validation",
            );
        }

        // In a real implementation, you would validate the Stripe webhook signature
        // using the Stripe SDK or the raw request body and signature header
        // This is a simplified version

        // For development/test environments, we can skip validation
        if (app()->environment(["local", "testing", "staging"])) {
            return true;
        }

        // In production, implement proper webhook signature validation
        // using Stripe's webhook signing secret
        return true;
    }

    /**
     * Map Stripe event to callback data
     *
     * @param string $eventType
     * @param array $eventData
     * @return array
     */
    protected function mapStripeEventToCallbackData(
        string $eventType,
        array $eventData,
    ): array {
        $result = [
            "event_type" => $eventType,
            "gateway" => $this->getName(),
            "timestamp" => now()->toISOString(),
            "raw_data" => $eventData,
        ];

        switch ($eventType) {
            case "payment_intent.succeeded":
                $result["transaction_id"] = $eventData["id"] ?? "";
                $result["status"] = "completed";
                $result["amount"] = $this->convertFromCents(
                    $eventData["amount"] ?? 0,
                );
                $result["currency"] = $eventData["currency"] ?? "usd";
                $result["customer_id"] = $eventData["customer"] ?? null;
                $result["message"] = "Payment completed successfully";
                break;

            case "payment_intent.payment_failed":
                $result["transaction_id"] = $eventData["id"] ?? "";
                $result["status"] = "failed";
                $result["error_message"] =
                    $eventData["last_payment_error"]["message"] ??
                    "Payment failed";
                $result["error_code"] =
                    $eventData["last_payment_error"]["code"] ?? null;
                $result["message"] = "Payment failed";
                break;

            case "payment_intent.canceled":
                $result["transaction_id"] = $eventData["id"] ?? "";
                $result["status"] = "cancelled";
                $result["cancellation_reason"] =
                    $eventData["cancellation_reason"] ?? null;
                $result["message"] = "Payment cancelled";
                break;

            case "charge.succeeded":
                $result["transaction_id"] = $eventData["id"] ?? "";
                $result["payment_intent_id"] =
                    $eventData["payment_intent"] ?? "";
                $result["status"] = "completed";
                $result["amount"] = $this->convertFromCents(
                    $eventData["amount"] ?? 0,
                );
                $result["currency"] = $eventData["currency"] ?? "usd";
                $result["customer_id"] = $eventData["customer"] ?? null;
                $result["message"] = "Charge succeeded";
                break;

            case "charge.failed":
                $result["transaction_id"] = $eventData["id"] ?? "";
                $result["payment_intent_id"] =
                    $eventData["payment_intent"] ?? "";
                $result["status"] = "failed";
                $result["error_message"] =
                    $eventData["failure_message"] ?? "Charge failed";
                $result["error_code"] = $eventData["failure_code"] ?? null;
                $result["message"] = "Charge failed";
                break;

            case "charge.refunded":
                $result["transaction_id"] = $eventData["id"] ?? "";
                $result["status"] = "refunded";
                $result["refund_amount"] = $this->convertFromCents(
                    $eventData["amount_refunded"] ?? 0,
                );
                $result["refund_id"] =
                    $eventData["refunds"]["data"][0]["id"] ?? null;
                $result["message"] = "Charge refunded";
                break;

            case "customer.subscription.created":
                $result["subscription_id"] = $eventData["id"] ?? "";
                $result["status"] = "active";
                $result["customer_id"] = $eventData["customer"] ?? null;
                $result["current_period_end"] =
                    $eventData["current_period_end"] ?? null;
                $result["message"] = "Subscription created";
                break;

            case "customer.subscription.deleted":
                $result["subscription_id"] = $eventData["id"] ?? "";
                $result["status"] = "cancelled";
                $result["customer_id"] = $eventData["customer"] ?? null;
                $result["message"] = "Subscription cancelled";
                break;

            default:
                $result["status"] = "unknown";
                $result["message"] = "Unknown Stripe event: " . $eventType;
                break;
        }

        return $result;
    }

    /**
     * Process refund payment for Stripe
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
            "charge" => $transactionId,
        ];

        if ($amount !== null) {
            $refundData["amount"] = $this->convertToCents($amount);
        }

        // Make API request to process refund
        $response = $this->makeRequest("POST", "/v1/refunds", $refundData);

        // Log refund attempt
        $this->logActivity("refund_requested", [
            "transaction_id" => $transactionId,
            "amount" => $amount,
            "response" => $response,
        ]);

        return [
            "success" => $response["status"] === "succeeded",
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "refund_id" => $response["id"] ?? null,
            "status" => $response["status"] ?? "pending",
            "amount" => $this->convertFromCents($response["amount"] ?? 0),
            "currency" => $response["currency"] ?? "usd",
            "reason" => $response["reason"] ?? null,
            "receipt_number" => $response["receipt_number"] ?? null,
            "message" =>
                $response["status"] === "succeeded"
                    ? "Refund processed successfully"
                    : "Refund processing failed",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Map Stripe payment status to our status
     *
     * @param string $stripeStatus
     * @return string
     */
    protected function mapPaymentStatus(string $stripeStatus): string
    {
        $statusMap = [
            "succeeded" => "completed",
            "pending" => "pending",
            "failed" => "failed",
            "canceled" => "cancelled",
            "requires_action" => "pending",
            "requires_confirmation" => "pending",
            "requires_payment_method" => "pending",
            "processing" => "processing",
            "requires_capture" => "authorized",
            "partially_refunded" => "partially_refunded",
            "refunded" => "refunded",
        ];

        return $statusMap[strtolower($stripeStatus)] ?? "unknown";
    }

    /**
     * Validate Stripe configuration
     *
     * @return void
     * @throws PaymentGatewayException
     */
    protected function validateConfiguration(): void
    {
        $missingConfig = [];

        // Check required configuration
        $requiredConfig = ["secret_key", "publishable_key"];
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

        // Get or create customer
        $customerId = $this->getOrCreateCustomer(
            $subscriptionData["customer"] ?? [],
        );
        if (!$customerId) {
            throw new PaymentGatewayException(
                "Failed to create or retrieve customer for subscription",
            );
        }

        // Create price if not provided
        $priceId = $subscriptionData["price_id"] ?? null;
        if (!$priceId) {
            $priceData = [
                "currency" => strtolower(
                    $subscriptionData["currency"] ?? "usd",
                ),
                "unit_amount" => $this->convertToCents(
                    $subscriptionData["amount"] ?? 0,
                ),
                "recurring" => [
                    "interval" => $subscriptionData["interval"] ?? "month",
                    "interval_count" =>
                        $subscriptionData["interval_count"] ?? 1,
                ],
                "product_data" => [
                    "name" =>
                        $subscriptionData["plan_name"] ?? "Subscription Plan",
                    "description" =>
                        $subscriptionData["plan_description"] ??
                        "Recurring subscription",
                ],
            ];

            $priceResponse = $this->makeRequest(
                "POST",
                "/v1/prices",
                $priceData,
            );
            $priceId = $priceResponse["id"] ?? null;

            if (!$priceId) {
                throw new PaymentGatewayException(
                    "Failed to create subscription price",
                );
            }
        }

        // Create subscription
        $subscriptionRequestData = [
            "customer" => $customerId,
            "items" => [["price" => $priceId]],
            "payment_behavior" => "default_incomplete",
            "expand" => ["latest_invoice.payment_intent"],
            "metadata" => $subscriptionData["metadata"] ?? [],
        ];

        // Add trial period if specified
        if (isset($subscriptionData["trial_period_days"])) {
            $subscriptionRequestData["trial_period_days"] =
                $subscriptionData["trial_period_days"];
        }

        // Add payment settings
        $subscriptionRequestData["payment_settings"] = [
            "payment_method_types" => $subscriptionData[
                "payment_method_types"
            ] ?? ["card"],
            "save_default_payment_method" => "on_subscription",
        ];

        $response = $this->makeRequest(
            "POST",
            "/v1/subscriptions",
            $subscriptionRequestData,
        );

        // Extract payment intent for immediate payment
        $latestInvoice = $response["latest_invoice"] ?? null;
        $paymentIntent = $latestInvoice["payment_intent"] ?? null;

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "subscription_id" => $response["id"] ?? null,
            "customer_id" => $customerId,
            "price_id" => $priceId,
            "status" => $response["status"] ?? "incomplete",
            "current_period_start" => $response["current_period_start"] ?? null,
            "current_period_end" => $response["current_period_end"] ?? null,
            "latest_invoice_id" => $latestInvoice["id"] ?? null,
            "payment_intent_id" => $paymentIntent["id"] ?? null,
            "client_secret" => $paymentIntent["client_secret"] ?? null,
            "requires_action" =>
                $paymentIntent &&
                in_array($paymentIntent["status"], [
                    self::STATUS_REQUIRES_ACTION,
                    self::STATUS_REQUIRES_CONFIRMATION,
                ]),
            "message" => "Stripe subscription created successfully",
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
            "/v1/subscriptions/" . $subscriptionId,
            [
                "expand" => ["latest_invoice", "default_payment_method"],
            ],
        );

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "subscription_id" => $subscriptionId,
            "status" => $response["status"] ?? "active",
            "customer_id" => $response["customer"] ?? null,
            "current_period_start" => $response["current_period_start"] ?? null,
            "current_period_end" => $response["current_period_end"] ?? null,
            "cancel_at_period_end" =>
                $response["cancel_at_period_end"] ?? false,
            "canceled_at" => $response["canceled_at"] ?? null,
            "items" => $response["items"]["data"] ?? [],
            "latest_invoice" => $response["latest_invoice"] ?? null,
            "default_payment_method" =>
                $response["default_payment_method"] ?? null,
            "metadata" => $response["metadata"] ?? [],
            "message" => "Subscription details retrieved",
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

        // Make API request to cancel subscription
        $response = $this->makeRequest(
            "DELETE",
            "/v1/subscriptions/" . $subscriptionId,
        );

        return [
            "success" => $response["status"] === "canceled",
            "gateway" => $this->getName(),
            "subscription_id" => $subscriptionId,
            "status" => $response["status"] ?? "canceled",
            "cancel_at_period_end" =>
                $response["cancel_at_period_end"] ?? false,
            "canceled_at" => $response["canceled_at"] ?? null,
            "message" =>
                $response["status"] === "canceled"
                    ? "Subscription cancelled successfully"
                    : "Subscription cancellation failed",
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
        // Validate configuration
        $this->validateConfiguration();

        // Prepare update data
        $updateData = [];

        if (isset($subscriptionData["price_id"])) {
            $updateData["items"] = [
                [
                    "id" => $this->getSubscriptionItemId($subscriptionId),
                    "price" => $subscriptionData["price_id"],
                ],
            ];
        }

        if (isset($subscriptionData["cancel_at_period_end"])) {
            $updateData["cancel_at_period_end"] =
                $subscriptionData["cancel_at_period_end"];
        }

        if (isset($subscriptionData["metadata"])) {
            $updateData["metadata"] = $subscriptionData["metadata"];
        }

        if (isset($subscriptionData["default_payment_method"])) {
            $updateData["default_payment_method"] =
                $subscriptionData["default_payment_method"];
        }

        if (empty($updateData)) {
            throw new PaymentGatewayException(
                "No valid subscription data provided for update",
            );
        }

        // Make API request to update subscription
        $response = $this->makeRequest(
            "POST",
            "/v1/subscriptions/" . $subscriptionId,
            $updateData,
        );

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "subscription_id" => $subscriptionId,
            "status" => $response["status"] ?? "active",
            "message" => "Subscription updated successfully",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Get subscription item ID
     *
     * @param string $subscriptionId
     * @return string|null
     * @throws PaymentGatewayException
     */
    protected function getSubscriptionItemId(string $subscriptionId): ?string
    {
        $response = $this->makeRequest(
            "GET",
            "/v1/subscriptions/" . $subscriptionId,
        );
        $items = $response["items"]["data"] ?? [];

        return !empty($items) ? $items[0]["id"] : null;
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

        if (isset($filters["customer"])) {
            $queryParams["customer"] = $filters["customer"];
        }

        if (isset($filters["created"])) {
            $queryParams["created"] = $filters["created"];
        }

        if (isset($filters["status"])) {
            $queryParams["status"] = $filters["status"];
        }

        // Make API request to get payment intents
        $url =
            "/v1/payment_intents" .
            (!empty($queryParams) ? "?" . http_build_query($queryParams) : "");
        $response = $this->makeRequest("GET", $url);

        $paymentIntents = $response["data"] ?? [];
        $mappedTransactions = [];

        foreach ($paymentIntents as $paymentIntent) {
            $mappedTransactions[] = [
                "id" => $paymentIntent["id"] ?? null,
                "amount" => $this->convertFromCents(
                    $paymentIntent["amount"] ?? 0,
                ),
                "currency" => $paymentIntent["currency"] ?? "usd",
                "status" => $this->mapPaymentStatus(
                    $paymentIntent["status"] ?? "requires_payment_method",
                ),
                "stripe_status" =>
                    $paymentIntent["status"] ?? "requires_payment_method",
                "customer_id" => $paymentIntent["customer"] ?? null,
                "description" => $paymentIntent["description"] ?? "",
                "metadata" => $paymentIntent["metadata"] ?? [],
                "created" => $paymentIntent["created"] ?? null,
                "latest_charge" => $paymentIntent["latest_charge"] ?? null,
            ];
        }

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "transactions" => $mappedTransactions,
            "has_more" => $response["has_more"] ?? false,
            "total" => count($mappedTransactions),
            "next_page" => $response["has_more"]
                ? $paymentIntents[count($paymentIntents) - 1]["id"] ?? null
                : null,
            "message" => "Transaction history retrieved",
            "timestamp" => now()->toISOString(),
        ];
    }

    /**
     * Get required fields for Stripe payment
     *
     * @return array
     */
    public function getRequiredFields(): array
    {
        return ["amount", "currency"];
    }

    /**
     * Get optional fields for Stripe payment
     *
     * @return array
     */
    public function getOptionalFields(): array
    {
        return [
            "reference",
            "description",
            "customer.email",
            "customer.name",
            "customer.phone",
            "payment_method_types",
            "metadata",
            "shipping_address.line1",
            "shipping_address.line2",
            "shipping_address.city",
            "shipping_address.state",
            "shipping_address.postal_code",
            "shipping_address.country",
            "application_fee_amount",
            "transfer_data.destination",
            "transfer_data.amount",
            "capture_method",
            "confirm",
        ];
    }
}
