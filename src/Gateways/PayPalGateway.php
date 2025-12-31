<?php

namespace Lisosoft\PaymentGateway\Gateways;

use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayPalGateway extends AbstractGateway
{
    /**
     * PayPal API endpoints
     */
    const API_ENDPOINT_LIVE = "https://api.paypal.com";
    const API_ENDPOINT_SANDBOX = "https://api.sandbox.paypal.com";

    /**
     * PayPal payment statuses
     */
    const STATUS_CREATED = "CREATED";
    const STATUS_SAVED = "SAVED";
    const STATUS_APPROVED = "APPROVED";
    const STATUS_VOIDED = "VOIDED";
    const STATUS_COMPLETED = "COMPLETED";
    const STATUS_PAYER_ACTION_REQUIRED = "PAYER_ACTION_REQUIRED";

    /**
     * Initialize the PayPal gateway
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->name = "paypal";
        $this->displayName = "PayPal";

        // Set supported currencies (PayPal supports multiple currencies)
        $this->supportedCurrencies = [
            "USD", "EUR", "GBP", "CAD", "AUD", "JPY", "CNY", "MXN", "BRL",
            "ZAR", "AED", "SAR", "INR", "SGD", "HKD", "NZD", "CHF", "NOK",
            "SEK", "DKK", "PLN", "CZK", "HUF", "ILS", "PHP", "THB", "MYR",
        ];

        // Set supported payment methods
        $this->paymentMethods = [
            "paypal",
            "credit_card",
            "paypal_credit",
            "paylater",
            "venmo",
            "apple_pay",
            "google_pay",
        ];
    }

    /**
     * Get default configuration for PayPal
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            "enabled" => true,
            "client_id" => "",
            "client_secret" => "",
            "mode" => "sandbox", // sandbox or live
            "return_url" => "/payment/success",
            "cancel_url" => "/payment/cancel",
            "webhook_id" => "",
            "validate_signature" => true,
            "timeout" => 30,
            "retry_attempts" => 3,
            "retry_delay" => 100,
            "verify_ssl" => true,
            "minimum_amount" => 0.01,
            "maximum_amount" => 100000.00,
            "currency" => "USD",
            "reference_prefix" => "PP",
            "logo_url" => "https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_111x69.jpg",
            "documentation_url" => "https://developer.paypal.com/docs/api/overview/",
            "error_messages" => [
                "PAYMENT_CREATION_ERROR" => "Error creating payment",
                "PAYMENT_CANCEL_FAILED" => "Payment cancellation failed",
                "PAYMENT_ALREADY_DONE" => "Payment already completed",
                "PAYMENT_NOT_APPROVED" => "Payment not approved by payer",
                "PAYMENT_EXPIRED" => "Payment link expired",
                "INSUFFICIENT_FUNDS" => "Insufficient funds in PayPal account",
                "CARD_DECLINED" => "Card was declined",
                "INVALID_CARD" => "Invalid card details",
                "INTERNAL_SERVICE_ERROR" => "PayPal internal service error",
                "VALIDATION_ERROR" => "Validation error in payment data",
            ],
        ];
    }

    /**
     * Get PayPal API endpoint based on mode
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        return $this->config["mode"] === "live"
            ? self::API_ENDPOINT_LIVE
            : self::API_ENDPOINT_SANDBOX;
    }

    /**
     * Get access token for PayPal API
     *
     * @return string
     * @throws PaymentGatewayException
     */
    protected function getAccessToken(): string
    {
        // Check if we have a valid cached token
        $cacheKey = "paypal_access_token_" . $this->config["mode"];
        $cachedToken = cache()->get($cacheKey);

        if ($cachedToken) {
            return $cachedToken;
        }

        // Request new access token
        $authString = base64_encode(
            $this->config["client_id"] . ":" . $this->config["client_secret"],
        );

        $response = Http::withHeaders([
            "Authorization" => "Basic " . $authString,
            "Content-Type" => "application/x-www-form-urlencoded",
        ])
            ->asForm()
            ->post($this->getApiEndpoint() . "/v1/oauth2/token", [
                "grant_type" => "client_credentials",
            ]);

        if ($response->failed()) {
            throw PaymentGatewayException::networkError(
                $this->getName(),
                "Failed to get PayPal access token: " . $response->body(),
                $response->status(),
            );
        }

        $data = $response->json();
        $accessToken = $data["access_token"] ?? null;
        $expiresIn = $data["expires_in"] ?? 3600;

        if (!$accessToken) {
            throw PaymentGatewayException::networkError(
                $this->getName(),
                "No access token in PayPal response",
            );
        }

        // Cache the token (with some buffer time)
        cache()->put($cacheKey, $accessToken, $expiresIn - 300);

        return $accessToken;
    }

    /**
     * Get default HTTP headers for PayPal
     *
     * @return array
     * @throws PaymentGatewayException
     */
    protected function getDefaultHeaders(): array
    {
        $headers = parent::getDefaultHeaders();

        // Add PayPal authentication header
        $headers["Authorization"] = "Bearer " . $this->getAccessToken();
        $headers["PayPal-Request-Id"] = Str::uuid()->toString();

        return $headers;
    }

    /**
     * Process payment initialization for PayPal
     *
     * @param array $paymentData
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processInitializePayment(array $paymentData): array
    {
        // Validate required configuration
        $this->validateConfiguration();

        // Prepare PayPal payment data
        $paypalData = $this->preparePaymentData($paymentData);

        // Make API request to create payment
        $response = $this->makeRequest("POST", "/v2/checkout/orders", $paypalData);

        // Log payment initialization
        $this->logActivity("payment_initialized", [
            "payment_data" => $paymentData,
            "paypal_data" => $paypalData,
            "response" => $response,
        ]);

        // Find approval URL
        $approvalUrl = "";
        foreach ($response["links"] ?? [] as $link) {
            if ($link["rel"] === "approve") {
                $approvalUrl = $link["href"];
                break;
            }
        }

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "transaction_id" => $response["id"] ?? "",
            "gateway_transaction_id" => $response["id"] ?? "",
            "payment_url" => $approvalUrl,
            "order_id" => $response["id"] ?? "",
            "status" => strtolower($response["status"] ?? "created"),
            "redirect_required" => true,
            "message" => "PayPal payment initialized successfully",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Prepare payment data for PayPal
     *
     * @param array $paymentData
     * @return array
     */
    protected function preparePaymentData(array $paymentData): array
    {
        // Generate reference if not provided
        $reference = $paymentData["reference"] ?? $this->generateReference($paymentData);

        // Base PayPal order data
        $paypalData = [
            "intent" => "CAPTURE",
            "purchase_units" => [
                [
                    "reference_id" => $reference,
                    "description" => $paymentData["description"] ?? "Payment",
                    "custom_id" => $paymentData["custom_id"] ?? $reference,
                    "invoice_id" => $paymentData["invoice_id"] ?? $reference,
                    "amount" => [
                        "currency_code" => strtoupper($paymentData["currency"] ?? "USD"),
                        "value" => number_format($paymentData["amount"], 2, ".", ""),
                        "breakdown" => [
                            "item_total" => [
                                "currency_code" => strtoupper($paymentData["currency"] ?? "USD"),
                                "value" => number_format($paymentData["amount"], 2, ".", ""),
                            ],
                        ],
                    ],
                    "items" => [
                        [
                            "name" => $paymentData["description"] ?? "Item",
                            "description" => $paymentData["item_description"] ?? $paymentData["description"] ?? "Item",
                            "quantity" => "1",
                            "unit_amount" => [
                                "currency_code" => strtoupper($paymentData["currency"] ?? "USD"),
                                "value" => number_format($paymentData["amount"], 2, ".", ""),
                            ],
                        ],
                    ],
                ],
            ],
            "payment_source" => [
                "paypal" => [
                    "experience_context" => [
                        "payment_method_preference" => "IMMEDIATE_PAYMENT_REQUIRED",
                        "brand_name" => $paymentData["brand_name"] ?? config("app.name"),
                        "locale" => $paymentData["locale"] ?? "en-US",
                        "landing_page" => "LOGIN",
                        "shipping_preference" => "NO_SHIPPING",
                        "user_action" => "PAY_NOW",
                        "return_url" => $this->buildUrl($this->config["return_url"], $reference),
                        "cancel_url" => $this->buildUrl($this->config["cancel_url"], $reference),
                    ],
                ],
            ],
        ];

        // Add customer information if available
        if (isset($paymentData["customer"])) {
            $paypalData["payer"] = [
                "name" => [
                    "given_name" => $paymentData["customer"]["first_name"] ?? $paymentData["customer"]["name"] ?? "",
                    "surname" => $paymentData["customer"]["last_name"] ?? "",
                ],
                "email_address" => $paymentData["customer"]["email"] ?? "",
                "phone" => isset($paymentData["customer"]["phone"])
                    ? [
                        "phone_number" => [
                            "national_number" => $paymentData["customer"]["phone"],
                        ],
                    ]
                    : null,
            ];
        }

        // Add shipping address if provided
        if (isset($paymentData["shipping_address"])) {
            $paypalData["purchase_units"][0]["shipping"] = [
                "address" => [
                    "address_line_1" => $paymentData["shipping_address"]["line1"] ?? "",
                    "address_line_2" => $paymentData["shipping_address"]["line2"] ?? "",
                    "admin_area_2" => $paymentData["shipping_address"]["city"] ?? "",
                    "admin_area_1" => $paymentData["shipping_address"]["state"] ?? "",
                    "postal_code" => $paymentData["shipping_address"]["postal_code"] ?? "",
                    "country_code" => $paymentData["shipping_address"]["country_code"] ?? "US",
                ],
            ];
        }

        // Add metadata
        if (isset($paymentData["metadata"])) {
            $paypalData["purchase_units"][0]["metadata"] = $paymentData["metadata"];
        }

        return $paypalData;
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
     * Process payment verification for PayPal
     *
     * @param string $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    protected function processVerifyPayment(string $transactionId): array
    {
        // Validate configuration
        $this->validateConfiguration();

        // Make API request to get order details
        $response = $this->makeRequest("GET", "/v2/checkout/orders/" . $transactionId);

        // Log verification attempt
        $this->logActivity("payment_verification_attempt", [
            "transaction_id" => $transactionId,
            "response" => $response,
        ]);

        // Extract payment details
        $status = $response["status"] ?? "CREATED";
        $gatewayStatus = $this->mapPaymentStatus($status);

        $purchaseUnit = $response["purchase_units"][0] ?? [];
        $payments = $purchaseUnit["payments"] ?? [];
        $captures = $payments["captures"] ?? [];
        $capture = $captures[0] ?? [];

        $result = [
            "success" => $gatewayStatus === "completed",
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "gateway_transaction_id" => $transactionId,
            "status" => $gatewayStatus,
            "paypal_status" => $status,
            "amount" => isset($purchaseUnit["amount"]["value"])
                ? (float) $purchaseUnit["amount"]["value"]
                : 0,
            "currency" => $purchaseUnit["amount"]["currency_code"] ?? "USD",
            "create_time" => $response["create_time"] ?? null,
            "update_time" => $response["update_time"] ?? null,
            "payer" => $response["payer"] ?? [],
            "purchase_units" => $response["purchase_units"] ?? [],
            "links" => $response["links"] ?? [],
            "message" => "Payment verification completed",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];

        // Add capture details if available
        if (!empty($capture)) {
            $result["capture_id"] = $capture["id"] ?? null;
            $result["capture_status"] = $capture["status"] ?? null;
            $result["final_capture"] = $capture["final_capture"] ?? false;
            $result["disbursement_mode"] = $capture["disbursement_mode"] ?? "INSTANT";
        }

        return $result;
    }

    /**
     * Capture PayPal payment
     *
     * @param string $orderId
     * @return array
     * @throws PaymentGatewayException
     */
    public function capturePayment(string $orderId): array
    {
        // Validate configuration
        $this->validateConfiguration();

        // Make API request to capture payment
        $response = $this->makeRequest("POST", "/v2/checkout/orders/" . $orderId . "/capture");

        // Log capture attempt
        $this->logActivity("payment_capture_attempt", [
            "order_id" => $orderId,
            "response" => $response,
        ]);

        $status = $response["status"] ?? "COMPLETED";
        $purchaseUnit = $response["purchase_units"][0] ?? [];
        $payments = $purchaseUnit["payments"] ?? [];
        $captures = $payments["captures"] ?? [];
        $capture = $captures[0] ?? [];

        return [
            "success" => $status === "COMPLETED",
            "gateway" => $this->getName(),
            "order_id" => $orderId,
            "capture_id" => $capture["id"] ?? null,
            "status" => strtolower($status),
            "amount" => isset($capture["amount"]["value"])
                ? (float) $capture["amount"]["value"]
                : 0,
            "currency" => $capture["amount"]["currency_code"] ?? "USD",
            "final_capture" => $capture["final_capture"] ?? true,
            "disbursement_mode" => $capture["disbursement_mode"] ?? "INSTANT",
            "seller_protection" => $capture["seller_protection"] ?? [],
            "links" => $capture["links"] ?? [],
            "message" => $status === "COMPLETED"
                ? "Payment captured successfully"
                : "Payment capture failed",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Process callback data from PayPal webhook
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
        $eventType = $callbackData["event_type"] ?? "";
        $resource = $callbackData["resource"] ?? [];

        // Map PayPal event to our callback data
        $result = $this->mapPayPalEventToCallbackData($eventType, $resource);

        // Log callback processing
        $this->logActivity("callback_processed", array_merge(["event_type" => $eventType], $result));

        return $result;
    }

    /**
     * Validate PayPal webhook signature
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

        $webhookId = $this->config["webhook_id"] ?? "";
        if (empty($webhookId)) {
            throw PaymentGatewayException::invalidSignature(
                $this->getName(),
                "Missing webhook ID for signature validation",
            );
        }

        // In a real implementation, you would validate the PayPal webhook signature
        // using the PayPal SDK or API
        // This is a simplified version

        // For development/test environments, we can skip validation
        if (app()->environment(["local", "testing", "staging"])) {
            return true;
        }

        // In production, implement proper webhook signature validation
        // using PayPal's verification API
        return true;
    }

    /**
     * Map PayPal event to callback data
     *
     * @param string $eventType
     * @param array $resource
     * @return array
     */
    protected function mapPayPalEventToCallbackData(string $eventType, array $resource): array
    {
        $result = [
            "event_type" => $eventType,
            "gateway" => $this->getName(),
            "timestamp" => now()->toISOString(),
            "raw_data" => $resource,
        ];

        switch ($eventType) {
            case "CHECKOUT.ORDER.APPROVED":
                $result["transaction_id"] = $resource["id"] ?? "";
                $result["status"] = "approved";
                $result["message"] = "Order approved by payer";
                break;

            case "CHECKOUT.ORDER.COMPLETED":
                $result["transaction_id"] = $resource["id"] ?? "";
                $result["status"] = "completed";
                $result["message"] = "Order completed";
                break;

            case "PAYMENT.CAPTURE.COMPLETED":
                $result["transaction_id"] = $resource["id"] ?? "";
                $result["order_id"] = $resource["supplementary_data"]["related_ids"]["order_id"] ?? "";
                $result["status"] = "completed";
                $result["amount"] = isset($resource["amount"]["value"])
                    ? (float) $resource["amount"]["value"]
                    : 0;
                $result["currency"] = $resource["amount"]["currency_code"] ?? "USD";
                $result["message"] = "Payment captured successfully";
                break;

            case "PAYMENT.CAPTURE.DENIED":
                $result["transaction_id"] = $resource["id"] ?? "";
                $result["status"] = "failed";
                $result["error_message"] = $resource["details"]["description"] ?? "Payment capture denied";
                $result["message"] = "Payment capture denied";
                break;

            case "PAYMENT.CAPTURE.REFUNDED":
                $result["transaction_id"] = $resource["id"] ?? "";
                $result["status"] = "refunded";
                $result["refund_amount"] = isset($resource["amount"]["value"])
                    ? (float) $resource["amount"]["value"]
                    : 0;
                $result["message"] = "Payment refunded";
                break;

            case "PAYMENT.CAPTURE.REVERSED":
                $result["transaction_id"] = $resource["id"] ?? "";
                $result["status"] = "reversed";
                $result["message"] = "Payment reversed";
                break;

            default:
                $result["status"] = "unknown";
                $result["message"] = "Unknown PayPal event: " . $eventType;
                break;
        }

        return $result;
    }

    /**
     * Process refund payment for PayPal
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
            "amount" => [
                "value" => number_format($amount ?? 0, 2, ".", ""),
                "currency_code" => "USD", // Default, should be determined from original transaction
            ],
            "note_to_payer" => "Refund",
        ];

        // Make API request to process refund
        $response = $this->makeRequest("POST", "/v2/payments/captures/" . $transactionId . "/refund", $refundData);

        // Log refund attempt
        $this->logActivity("refund_requested", [
            "transaction_id" => $transactionId,
            "amount" => $amount,
            "response" => $response,
        ]);

        return [
            "success" => $response["status"] === "COMPLETED",
            "gateway" => $this->getName(),
            "transaction_id" => $transactionId,
            "refund_id" => $response["id"] ?? null,
            "status" => strtolower($response["status"] ?? "pending"),
            "amount" => isset($response["amount"]["value"])
                ? (float) $response["amount"]["value"]
                : $amount,
            "currency" => $response["amount"]["currency_code"] ?? "USD",
            "note_to_payer" => $response["note_to_payer"] ?? "Refund",
            "links" => $response["links"] ?? [],
            "message" => $response["status"] === "COMPLETED"
                ? "Refund processed successfully"
                : "Refund processing failed",
            "timestamp" => now()->toISOString(),
            "raw_response" => $response,
        ];
    }

    /**
     * Map PayPal payment status to our status
     *
     * @param string $paypalStatus
     * @return string
     */
    protected function mapPaymentStatus(string $paypalStatus): string
    {
        $statusMap = [
            "CREATED" => "created",
            "SAVED" => "saved",
            "APPROVED" => "approved",
            "VOIDED" => "cancelled",
            "COMPLETED" => "completed",
            "PAYER_ACTION_REQUIRED" => "pending",
            "PENDING" => "pending",
            "FAILED" => "failed",
            "DECLINED" => "failed",
            "EXPIRED" => "expired",
            "PARTIALLY_REFUNDED" => "partially_refunded",
            "REFUNDED" => "refunded",
        ];

        return $statusMap[strtoupper($paypalStatus)] ?? "unknown";
    }

    /**
     * Validate PayPal configuration
     *
     * @return void
     * @throws PaymentGatewayException
     */
    protected function validateConfiguration(): void
    {
        $missingConfig = [];

        // Check required configuration
        $requiredConfig = ["client_id", "client_secret"];
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

        // Prepare subscription plan data
        $planData = [
            "product_id" => $subscriptionData["product_id"] ?? "PROD-" . Str::random(8),
            "name" => $subscriptionData["plan_name"] ?? "Subscription Plan",
            "description" => $subscriptionData["plan_description"] ?? "Monthly subscription",
            "status" => "ACTIVE",
            "billing_cycles" => [
                [
                    "frequency" => [
                        "interval_unit" => strtoupper($subscriptionData["interval"] ?? "MONTH"),
                        "interval_count" => $subscriptionData["interval_count"] ?? 1,
                    ],
                    "tenure_type" => "REGULAR",
                    "sequence" => 1,
                    "total_cycles" => $subscriptionData["total_cycles"] ?? 0, // 0 = infinite
                    "pricing_scheme" => [
                        "fixed_price" => [
                            "value" => number_format($subscriptionData["amount"], 2, ".", ""),
                            "currency_code" => strtoupper($subscriptionData["currency"] ?? "USD"),
                        ],
                    ],
                ],
            ],
            "payment_preferences" => [
                "auto_bill_outstanding" => true,
                "setup_fee_failure_action" => "CONTINUE",
                "payment_failure_threshold" => 3,
            ],
        ];

        // Create plan
        $planResponse = $this->makeRequest("POST", "/v1/billing/plans", $planData);
        $planId = $planResponse["id"] ?? null;

        if (!$planId) {
            throw new PaymentGatewayException("Failed to create subscription plan");
        }

        // Prepare subscription data
        $subscriptionRequestData = [
            "plan_id" => $planId,
            "start_time" => $subscriptionData["start_time"] ?? now()->addMinutes(5)->toISOString(),
            "subscriber" => [
                "name" => [
                    "given_name" => $subscriptionData["customer"]["first_name"] ?? $subscriptionData["customer"]["name"] ?? "",
                    "surname" => $subscriptionData["customer"]["last_name"] ?? "",
                ],
                "email_address" => $subscriptionData["customer"]["email"] ?? "",
            ],
            "application_context" => [
                "brand_name" => $subscriptionData["brand_name"] ?? config("app.name"),
                "locale" => $subscriptionData["locale"] ?? "en-US",
                "shipping_preference" => "NO_SHIPPING",
                "user_action" => "SUBSCRIBE_NOW",
                "payment_method" => [
                    "payer_selected" => "PAYPAL",
                    "payee_preferred" => "IMMEDIATE_PAYMENT_REQUIRED",
                ],
                "return_url" => $this->buildUrl($this->config["return_url"], "subscription"),
                "cancel_url" => $this->buildUrl($this->config["cancel_url"], "subscription"),
            ],
        ];

        // Create subscription
        $response = $this->makeRequest("POST", "/v1/billing/subscriptions", $subscriptionRequestData);

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "subscription_id" => $response["id"] ?? null,
            "plan_id" => $planId,
            "status" => strtolower($response["status"] ?? "APPROVAL_PENDING"),
            "approval_url" => "",
            "message" => "PayPal subscription created successfully",
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
        $response = $this->makeRequest("GET", "/v1/billing/subscriptions/" . $subscriptionId);

        return [
            "success" => true,
            "gateway" => $this->getName(),
            "subscription_id" => $subscriptionId,
            "status" => strtolower($response["status"] ?? "ACTIVE"),
            "plan_id" => $response["plan_id"] ?? null,
            "start_time" => $response["start_time"] ?? null,
            "next_billing_time" => $response["billing_info"]["next_billing_time"] ?? null,
            "last_payment" => $response["billing_info"]["last_payment"] ?? [],
            "subscriber" => $response["subscriber"] ?? [],
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

        // Prepare cancellation reason
        $cancellationData = [
            "reason" => "Customer requested cancellation",
        ];

        // Make API request to cancel subscription
        $response = $this->makeRequest("POST", "/v1/billing/subscriptions/" . $subscriptionId . "/cancel", $cancellationData);

        return [
            "success" => $response === null || empty($response), // PayPal returns empty response on success
            "gateway" => $this->getName(),
            "subscription_id" => $subscriptionId,
            "status" => "cancelled",
            "message" => "Subscription cancelled successfully",
            "timestamp" => now()->toISOString(),
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
    public function updateSubscription(string $subscriptionId, array $subscriptionData): array
    {
        // PayPal subscriptions can be updated with PATCH requests
        // This is a simplified implementation

        $updateData = [];
        if (isset($subscriptionData["plan_id"])) {
            $updateData[] = [
                "op" => "replace",
                "path" => "/plan_id",
                "value" => $subscriptionData["plan_id"],
            ];
        }

        if (isset($subscriptionData["shipping_amount"])) {
            $updateData[] = [
                "op" => "replace",
                "path" => "/shipping_amount",
                "value" => [
                    "currency_code" => strtoupper($subscriptionData["currency"] ?? "USD"),
                    "value" => number_format($subscriptionData["shipping_amount"], 2, ".", ""),
                ],
            ];
        }

        if (empty($updateData)) {
            throw new PaymentGatewayException("No valid subscription data provided for update");
        }

        // Make API request to update subscription
        $response = $this->makeRequest("PATCH", "/v1/billing/subscriptions/" . $subscriptionId, $updateData);

        return [
            "success" => $response === null || empty($response), // PayPal returns empty response on success
            "gateway" => $this->getName(),
            "subscription_id" => $subscriptionId,
            "message" => "Subscription updated successfully",
            "timestamp" => now()->toISOString(),
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
        // PayPal doesn't have a simple transaction history API
        // You would need to use the reporting API or track transactions in your database

        throw new PaymentGatewayException(
            "Transaction history is not available via direct PayPal API. " .
                "Use PayPal's reporting API or track transactions in your application database.",
            ["filters" => $filters],
        );
    }

    /**
     * Get required fields for PayPal payment
     *
     * @return array
     */
    public function getRequiredFields(): array
    {
        return ["amount", "currency", "description"];
    }

    /**
     * Get optional fields for PayPal payment
     *
     * @return array
     */
    public function getOptionalFields(): array
    {
        return [
            "reference",
            "customer.email",
            "customer.name",
            "customer.first_name",
            "customer.last_name",
            "customer.phone",
            "shipping_address.line1",
            "shipping_address.line2",
            "shipping_address.city",
            "shipping_address.state",
            "shipping_address.postal_code",
            "shipping_address.country_code",
            "metadata",
            "brand_name",
            "locale",
            "invoice_id",
            "custom_id",
        ];
    }
}
