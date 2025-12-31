<?php

namespace Lisosoft\PaymentGateway\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Lisosoft\PaymentGateway\Facades\Payment;
use Lisosoft\PaymentGateway\Models\Transaction;
use Lisosoft\PaymentGateway\Events\PaymentCompleted;
use Lisosoft\PaymentGateway\Events\PaymentFailed;
use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Carbon\Carbon;

class PaymentApiController extends Controller
{
    /**
     * Initialize a new payment
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function initialize(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                "gateway" =>
                    "required|string|in:payfast,paystack,paypal,stripe,ozow,zapper,crypto,eft,vodapay,snapscan",
                "amount" => "required|numeric|min:0.01",
                "currency" => "required|string|size:3",
                "description" => "required|string|max:255",
                "customer_email" => "required|email",
                "customer_name" => "nullable|string|max:255",
                "customer_phone" => "nullable|string|max:20",
                "metadata" => "nullable|array",
                "return_url" => "nullable|url",
                "cancel_url" => "nullable|url",
                "webhook_url" => "nullable|url",
                "is_subscription" => "nullable|boolean",
                "subscription_data" => "nullable|array",
            ]);

            if ($validator->fails()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Validation failed",
                        "errors" => $validator->errors(),
                    ],
                    422,
                );
            }

            $gateway = $request->input("gateway");
            $paymentData = $request->only([
                "amount",
                "currency",
                "description",
                "customer_email",
                "customer_name",
                "customer_phone",
                "metadata",
                "return_url",
                "cancel_url",
                "webhook_url",
            ]);

            // Add customer data
            $paymentData["customer"] = [
                "email" => $request->input("customer_email"),
                "name" => $request->input("customer_name"),
                "phone" => $request->input("customer_phone"),
            ];

            // Add subscription data if applicable
            if ($request->boolean("is_subscription")) {
                $paymentData["is_subscription"] = true;
                $paymentData["subscription_data"] = $request->input(
                    "subscription_data",
                    [],
                );
            }

            // Add request information
            $paymentData["ip_address"] = $request->ip();
            $paymentData["user_agent"] = $request->userAgent();

            // Add user ID if authenticated
            if (Auth::check()) {
                $paymentData["user_id"] = Auth::id();
                $paymentData["user_type"] = get_class(Auth::user());
            }

            // Check if gateway is available
            if (!Payment::isGatewayAvailable($gateway)) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Payment gateway '{$gateway}' is not available",
                        "gateway" => $gateway,
                    ],
                    400,
                );
            }

            // Initialize payment with gateway
            $result = Payment::initializePayment($gateway, $paymentData);

            // Create transaction record
            $transaction = Transaction::create([
                "reference" =>
                    $result["transaction_id"] ??
                    Transaction::generateReference(),
                "gateway" => $gateway,
                "gateway_transaction_id" =>
                    $result["gateway_transaction_id"] ?? null,
                "amount" => $paymentData["amount"],
                "currency" => $paymentData["currency"],
                "status" => "pending",
                "description" => $paymentData["description"],
                "customer_email" => $paymentData["customer"]["email"],
                "customer_name" => $paymentData["customer"]["name"],
                "customer_phone" => $paymentData["customer"]["phone"],
                "is_subscription" => $paymentData["is_subscription"] ?? false,
                "subscription_id" => $result["subscription_id"] ?? null,
                "return_url" => $paymentData["return_url"] ?? null,
                "cancel_url" => $paymentData["cancel_url"] ?? null,
                "webhook_url" => $paymentData["webhook_url"] ?? null,
                "ip_address" => $paymentData["ip_address"],
                "user_agent" => $paymentData["user_agent"],
                "metadata" => $paymentData["metadata"] ?? null,
                "processed_at" => now(),
            ]);

            // Add user relationship if authenticated
            if (Auth::check()) {
                $transaction->user()->associate(Auth::user());
                $transaction->save();
            }

            return response()->json([
                "success" => true,
                "message" => "Payment initialized successfully",
                "data" => array_merge($result, [
                    "transaction_id" => $transaction->reference,
                    "transaction" => $transaction->toArray(),
                ]),
            ]);
        } catch (PaymentGatewayException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Payment initialization failed",
                    "error" => $e->getMessage(),
                    "errors" => $e->getErrors(),
                ],
                400,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "An unexpected error occurred",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Verify a payment
     *
     * @param Request $request
     * @param string $transactionReference
     * @return JsonResponse
     */
    public function verify(
        Request $request,
        string $transactionReference,
    ): JsonResponse {
        try {
            // Find transaction
            $transaction = Transaction::where(
                "reference",
                $transactionReference,
            )->first();

            if (!$transaction) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Transaction not found",
                    ],
                    404,
                );
            }

            // Verify payment with gateway
            $result = Payment::verifyPayment(
                $transaction->gateway,
                $transaction->reference,
            );

            // Update transaction status
            $transaction->status = $result["status"] ?? $transaction->status;
            $transaction->gateway_transaction_id =
                $result["gateway_transaction_id"] ??
                $transaction->gateway_transaction_id;

            if ($result["success"] ?? false) {
                $transaction->completed_at = now();
                $transaction->status = "completed";

                // Dispatch payment completed event
                event(new PaymentCompleted($transaction));
            } else {
                $transaction->failed_at = now();
                $transaction->status = "failed";
                $transaction->error_message =
                    $result["message"] ?? "Payment verification failed";

                // Dispatch payment failed event
                event(new PaymentFailed($transaction));
            }

            $transaction->save();

            return response()->json([
                "success" => true,
                "message" => "Payment verified successfully",
                "data" => array_merge($result, [
                    "transaction" => $transaction->toArray(),
                ]),
            ]);
        } catch (PaymentGatewayException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Payment verification failed",
                    "error" => $e->getMessage(),
                    "errors" => $e->getErrors(),
                ],
                400,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "An unexpected error occurred",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Get payment status
     *
     * @param Request $request
     * @param string $transactionReference
     * @return JsonResponse
     */
    public function status(
        Request $request,
        string $transactionReference,
    ): JsonResponse {
        try {
            // Find transaction
            $transaction = Transaction::where(
                "reference",
                $transactionReference,
            )->first();

            if (!$transaction) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Transaction not found",
                    ],
                    404,
                );
            }

            // Get payment status from gateway
            $status = Payment::getPaymentStatus(
                $transaction->gateway,
                $transaction->reference,
            );

            // Update transaction status if changed
            if ($transaction->status !== $status) {
                $transaction->status = $status;

                if ($status === "completed") {
                    $transaction->completed_at = now();
                    event(new PaymentCompleted($transaction));
                } elseif ($status === "failed") {
                    $transaction->failed_at = now();
                    event(new PaymentFailed($transaction));
                }

                $transaction->save();
            }

            return response()->json([
                "success" => true,
                "message" => "Payment status retrieved successfully",
                "data" => [
                    "transaction_id" => $transaction->reference,
                    "status" => $status,
                    "is_successful" => Payment::isPaymentSuccessful(
                        $transaction->gateway,
                        $transaction->reference,
                    ),
                    "is_pending" => $status === "pending",
                    "is_failed" => $status === "failed",
                    "transaction" => $transaction->toArray(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to get payment status",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Refund a payment
     *
     * @param Request $request
     * @param string $transactionReference
     * @return JsonResponse
     */
    public function refund(
        Request $request,
        string $transactionReference,
    ): JsonResponse {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                "amount" => "nullable|numeric|min:0.01",
                "reason" => "nullable|string|max:255",
            ]);

            if ($validator->fails()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Validation failed",
                        "errors" => $validator->errors(),
                    ],
                    422,
                );
            }

            // Find transaction
            $transaction = Transaction::where(
                "reference",
                $transactionReference,
            )->first();

            if (!$transaction) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Transaction not found",
                    ],
                    404,
                );
            }

            // Check if transaction can be refunded
            if ($transaction->status !== "completed") {
                return response()->json(
                    [
                        "success" => false,
                        "message" =>
                            "Only completed transactions can be refunded",
                        "current_status" => $transaction->status,
                    ],
                    400,
                );
            }

            // Check if already refunded
            if ($transaction->refunded_at) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Transaction already refunded",
                        "refunded_at" => $transaction->refunded_at,
                    ],
                    400,
                );
            }

            $amount = $request->input("amount", $transaction->amount);
            $reason = $request->input("reason", "Customer request");

            // Process refund with gateway
            $result = Payment::refundPayment(
                $transaction->gateway,
                $transaction->reference,
                $amount,
            );

            // Update transaction
            $transaction->refund_amount = $amount;
            $transaction->refund_reason = $reason;
            $transaction->refunded_at = now();
            $transaction->status = "refunded";
            $transaction->save();

            // Create refund transaction record
            $refundTransaction = Transaction::create([
                "reference" =>
                    $result["refund_reference"] ??
                    Transaction::generateReference(),
                "gateway" => $transaction->gateway,
                "gateway_transaction_id" => $result["refund_id"] ?? null,
                "amount" => $amount,
                "currency" => $transaction->currency,
                "status" => "completed",
                "description" => "Refund: {$transaction->description}",
                "customer_email" => $transaction->customer_email,
                "customer_name" => $transaction->customer_name,
                "customer_phone" => $transaction->customer_phone,
                "transaction_type" => "refund",
                "parent_transaction_id" => $transaction->id,
                "refund_amount" => $amount,
                "refund_reason" => $reason,
                "completed_at" => now(),
                "metadata" => [
                    "original_transaction" => $transaction->reference,
                    "refund_reason" => $reason,
                ],
            ]);

            return response()->json([
                "success" => true,
                "message" => "Refund processed successfully",
                "data" => array_merge($result, [
                    "transaction" => $transaction->toArray(),
                    "refund_transaction" => $refundTransaction->toArray(),
                ]),
            ]);
        } catch (PaymentGatewayException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Refund failed",
                    "error" => $e->getMessage(),
                    "errors" => $e->getErrors(),
                ],
                400,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "An unexpected error occurred",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Retry a failed payment
     *
     * @param Request $request
     * @param string $transactionReference
     * @return JsonResponse
     */
    public function retry(
        Request $request,
        string $transactionReference,
    ): JsonResponse {
        try {
            // Find transaction
            $transaction = Transaction::where(
                "reference",
                $transactionReference,
            )->first();

            if (!$transaction) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Transaction not found",
                    ],
                    404,
                );
            }

            // Check if transaction can be retried
            if ($transaction->status !== "failed") {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Only failed transactions can be retried",
                        "current_status" => $transaction->status,
                    ],
                    400,
                );
            }

            // Check retry attempts
            if ($transaction->attempts >= $transaction->max_attempts) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Maximum retry attempts reached",
                        "attempts" => $transaction->attempts,
                        "max_attempts" => $transaction->max_attempts,
                    ],
                    400,
                );
            }

            // Prepare payment data for retry
            $paymentData = [
                "amount" => $transaction->amount,
                "currency" => $transaction->currency,
                "description" => $transaction->description,
                "customer" => [
                    "email" => $transaction->customer_email,
                    "name" => $transaction->customer_name,
                    "phone" => $transaction->customer_phone,
                ],
                "metadata" => $transaction->metadata ?? [],
                "return_url" => $transaction->return_url,
                "cancel_url" => $transaction->cancel_url,
                "webhook_url" => $transaction->webhook_url,
                "ip_address" => $transaction->ip_address,
                "user_agent" => $transaction->user_agent,
            ];

            // Add user ID if exists
            if ($transaction->user_id) {
                $paymentData["user_id"] = $transaction->user_id;
                $paymentData["user_type"] = $transaction->user_type;
            }

            // Initialize new payment
            $result = Payment::initializePayment(
                $transaction->gateway,
                $paymentData,
            );

            // Update original transaction
            $transaction->attempts += 1;
            $transaction->retry_at = now();
            $transaction->save();

            // Create new transaction for retry
            $retryTransaction = Transaction::create([
                "reference" =>
                    $result["transaction_id"] ??
                    Transaction::generateReference(),
                "gateway" => $transaction->gateway,
                "gateway_transaction_id" =>
                    $result["gateway_transaction_id"] ?? null,
                "amount" => $transaction->amount,
                "currency" => $transaction->currency,
                "status" => "pending",
                "description" => "Retry: {$transaction->description}",
                "customer_email" => $transaction->customer_email,
                "customer_name" => $transaction->customer_name,
                "customer_phone" => $transaction->customer_phone,
                "parent_transaction_id" => $transaction->id,
                "return_url" => $transaction->return_url,
                "cancel_url" => $transaction->cancel_url,
                "webhook_url" => $transaction->webhook_url,
                "ip_address" => $transaction->ip_address,
                "user_agent" => $transaction->user_agent,
                "metadata" => array_merge($transaction->metadata ?? [], [
                    "original_transaction" => $transaction->reference,
                    "retry_attempt" => $transaction->attempts,
                ]),
                "processed_at" => now(),
            ]);

            // Add user relationship if exists
            if ($transaction->user_id) {
                $retryTransaction->user_id = $transaction->user_id;
                $retryTransaction->user_type = $transaction->user_type;
                $retryTransaction->save();
            }

            return response()->json([
                "success" => true,
                "message" => "Payment retry initialized successfully",
                "data" => array_merge($result, [
                    "original_transaction" => $transaction->toArray(),
                    "retry_transaction" => $retryTransaction->toArray(),
                ]),
            ]);
        } catch (PaymentGatewayException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Payment retry failed",
                    "error" => $e->getMessage(),
                    "errors" => $e->getErrors(),
                ],
                400,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "An unexpected error occurred",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Get available gateways
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function gateways(Request $request): JsonResponse
    {
        try {
            $gateways = Payment::getAvailableGateways();

            return response()->json([
                "success" => true,
                "message" => "Gateways retrieved successfully",
                "data" => [
                    "gateways" => $gateways,
                    "total" => count($gateways),
                    "default_gateway" => config(
                        "payment-gateway.default",
                        "payfast",
                    ),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to get gateways",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Get transaction history
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Transaction::query();

            // Filter by user if authenticated
            if ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where("user_id", $user->id)->where(
                        "user_type",
                        get_class($user),
                    );
                });
            }

            // Apply filters
            if ($request->has("status")) {
                $query->where("status", $request->input("status"));
            }

            if ($request->has("gateway")) {
                $query->where("gateway", $request->input("gateway"));
            }

            if ($request->has("start_date")) {
                $query->where(
                    "created_at",
                    ">=",
                    Carbon::parse($request->input("start_date")),
                );
            }

            if ($request->has("end_date")) {
                $query->where(
                    "created_at",
                    "<=",
                    Carbon::parse($request->input("end_date")),
                );
            }

            // Pagination
            $perPage = $request->input("per_page", 20);
            $page = $request->input("page", 1);
            $transactions = $query
                ->orderBy("created_at", "desc")
                ->paginate($perPage, ["*"], "page", $page);

            return response()->json([
                "success" => true,
                "message" => "Transaction history retrieved successfully",
                "data" => [
                    "transactions" => $transactions->items(),
                    "pagination" => [
                        "total" => $transactions->total(),
                        "per_page" => $transactions->perPage(),
                        "current_page" => $transactions->currentPage(),
                        "last_page" => $transactions->lastPage(),
                        "has_more" => $transactions->hasMorePages(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to get transaction history",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Get user's transactions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function myTransactions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Authentication required",
                    ],
                    401,
                );
            }

            $query = Transaction::where("user_id", $user->id)->where(
                "user_type",
                get_class($user),
            );

            // Apply filters
            if ($request->has("status")) {
                $query->where("status", $request->input("status"));
            }

            if ($request->has("gateway")) {
                $query->where("gateway", $request->input("gateway"));
            }

            if ($request->has("start_date")) {
                $query->where(
                    "created_at",
                    ">=",
                    Carbon::parse($request->input("start_date")),
                );
            }

            if ($request->has("end_date")) {
                $query->where(
                    "created_at",
                    "<=",
                    Carbon::parse($request->input("end_date")),
                );
            }

            // Pagination
            $perPage = $request->input("per_page", 20);
            $page = $request->input("page", 1);
            $transactions = $query
                ->orderBy("created_at", "desc")
                ->paginate($perPage, ["*"], "page", $page);

            return response()->json([
                "success" => true,
                "message" => "Your transactions retrieved successfully",
                "data" => [
                    "transactions" => $transactions->items(),
                    "pagination" => [
                        "total" => $transactions->total(),
                        "per_page" => $transactions->perPage(),
                        "current_page" => $transactions->currentPage(),
                        "last_page" => $transactions->lastPage(),
                        "has_more" => $transactions->hasMorePages(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to get your transactions",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Handle PayFast webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handlePayFastWebhook(Request $request): JsonResponse
    {
        return $this->handleGenericWebhook($request, "payfast");
    }

    /**
     * Handle PayStack webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handlePayStackWebhook(Request $request): JsonResponse
    {
        return $this->handleGenericWebhook($request, "paystack");
    }

    /**
     * Handle PayPal webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handlePayPalWebhook(Request $request): JsonResponse
    {
        return $this->handleGenericWebhook($request, "paypal");
    }

    /**
     * Handle Stripe webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleStripeWebhook(Request $request): JsonResponse
    {
        return $this->handleGenericWebhook($request, "stripe");
    }

    /**
     * Handle Ozow webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleOzowWebhook(Request $request): JsonResponse
    {
        return $this->handleGenericWebhook($request, "ozow");
    }

    /**
     * Handle Zapper webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleZapperWebhook(Request $request): JsonResponse
    {
        return $this->handleGenericWebhook($request, "zapper");
    }

    /**
     * Handle Crypto webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleCryptoWebhook(Request $request): JsonResponse
    {
        return $this->handleGenericWebhook($request, "crypto");
    }

    /**
     * Handle VodaPay webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleVodaPayWebhook(Request $request): JsonResponse
    {
        return $this->handleGenericWebhook($request, "vodapay");
    }

    /**
     * Handle SnapScan webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleSnapScanWebhook(Request $request): JsonResponse
    {
        return $this->handleGenericWebhook($request, "snapscan");
    }

    /**
     * Handle generic webhook
     *
     * @param Request $request
     * @param string $gateway
     * @return JsonResponse
     */
    public function handleGenericWebhook(
        Request $request,
        string $gateway,
    ): JsonResponse {
        try {
            // Process callback with gateway
            $callbackData = $request->all();
            $result = Payment::processCallback($gateway, $callbackData);

            // Find transaction by reference or gateway transaction ID
            $transaction = null;
            if (isset($result["reference"])) {
                $transaction = Transaction::where(
                    "reference",
                    $result["reference"],
                )->first();
            }

            if (!$transaction && isset($result["transaction_id"])) {
                $transaction = Transaction::where(
                    "gateway_transaction_id",
                    $result["transaction_id"],
                )
                    ->orWhere("reference", $result["transaction_id"])
                    ->first();
            }

            // Update transaction if found
            if ($transaction) {
                $transaction->status =
                    $result["status"] ?? $transaction->status;
                $transaction->gateway_transaction_id =
                    $result["transaction_id"] ??
                    $transaction->gateway_transaction_id;

                if ($result["success"] ?? false) {
                    $transaction->completed_at = now();
                    $transaction->status = "completed";
                    event(new PaymentCompleted($transaction));
                } else {
                    $transaction->failed_at = now();
                    $transaction->status = "failed";
                    $transaction->error_message =
                        $result["message"] ?? "Payment failed";
                    event(new PaymentFailed($transaction));
                }

                $transaction->save();
            }

            return response()->json([
                "success" => true,
                "message" => "Webhook processed successfully",
                "data" => array_merge($result, [
                    "transaction" => $transaction
                        ? $transaction->toArray()
                        : null,
                ]),
            ]);
        } catch (PaymentGatewayException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Webhook processing failed",
                    "error" => $e->getMessage(),
                    "errors" => $e->getErrors(),
                ],
                400,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "An unexpected error occurred",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Create subscription
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createSubscription(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                "gateway" =>
                    "required|string|in:payfast,paystack,paypal,stripe,ozow,zapper,crypto,eft,vodapay,snapscan",
                "amount" => "required|numeric|min:0.01",
                "currency" => "required|string|size:3",
                "description" => "required|string|max:255",
                "customer_email" => "required|email",
                "customer_name" => "nullable|string|max:255",
                "customer_phone" => "nullable|string|max:20",
                "frequency" =>
                    "required|string|in:daily,weekly,monthly,quarterly,yearly",
                "start_date" => "nullable|date",
                "end_date" => "nullable|date|after:start_date",
                "cycles" => "nullable|integer|min:1",
                "metadata" => "nullable|array",
            ]);

            if ($validator->fails()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Validation failed",
                        "errors" => $validator->errors(),
                    ],
                    422,
                );
            }

            $gateway = $request->input("gateway");
            $subscriptionData = $request->only([
                "amount",
                "currency",
                "description",
                "customer_email",
                "customer_name",
                "customer_phone",
                "frequency",
                "start_date",
                "end_date",
                "cycles",
                "metadata",
            ]);

            // Add customer data
            $subscriptionData["customer"] = [
                "email" => $request->input("customer_email"),
                "name" => $request->input("customer_name"),
                "phone" => $request->input("customer_phone"),
            ];

            // Add user ID if authenticated
            if (Auth::check()) {
                $subscriptionData["user_id"] = Auth::id();
                $subscriptionData["user_type"] = get_class(Auth::user());
            }

            // Check if gateway is available
            if (!Payment::isGatewayAvailable($gateway)) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Payment gateway '{$gateway}' is not available",
                        "gateway" => $gateway,
                    ],
                    400,
                );
            }

            // Get gateway instance and create subscription
            $gatewayInstance = Payment::gateway($gateway);
            $result = $gatewayInstance->createSubscription($subscriptionData);

            // Create transaction record for subscription
            $transaction = Transaction::create([
                "reference" =>
                    $result["subscription_id"] ??
                    Transaction::generateReference(),
                "gateway" => $gateway,
                "gateway_transaction_id" => $result["subscription_id"] ?? null,
                "amount" => $subscriptionData["amount"],
                "currency" => $subscriptionData["currency"],
                "status" => "active",
                "description" => $subscriptionData["description"],
                "customer_email" => $subscriptionData["customer"]["email"],
                "customer_name" => $subscriptionData["customer"]["name"],
                "customer_phone" => $subscriptionData["customer"]["phone"],
                "is_subscription" => true,
                "subscription_id" => $result["subscription_id"] ?? null,
                "recurring_frequency" => $subscriptionData["frequency"],
                "recurring_cycles" => $subscriptionData["cycles"] ?? null,
                "next_billing_date" =>
                    $subscriptionData["start_date"] ??
                    Carbon::now()->addMonth(),
                "metadata" => $subscriptionData["metadata"] ?? null,
                "processed_at" => now(),
            ]);

            // Add user relationship if authenticated
            if (Auth::check()) {
                $transaction->user()->associate(Auth::user());
                $transaction->save();
            }

            return response()->json([
                "success" => true,
                "message" => "Subscription created successfully",
                "data" => array_merge($result, [
                    "transaction" => $transaction->toArray(),
                ]),
            ]);
        } catch (PaymentGatewayException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Subscription creation failed",
                    "error" => $e->getMessage(),
                    "errors" => $e->getErrors(),
                ],
                400,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "An unexpected error occurred",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * List subscriptions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listSubscriptions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Authentication required",
                    ],
                    401,
                );
            }

            $query = Transaction::where("user_id", $user->id)
                ->where("user_type", get_class($user))
                ->where("is_subscription", true);

            // Apply filters
            if ($request->has("status")) {
                $query->where("status", $request->input("status"));
            }

            if ($request->has("gateway")) {
                $query->where("gateway", $request->input("gateway"));
            }

            // Pagination
            $perPage = $request->input("per_page", 20);
            $page = $request->input("page", 1);
            $subscriptions = $query
                ->orderBy("created_at", "desc")
                ->paginate($perPage, ["*"], "page", $page);

            return response()->json([
                "success" => true,
                "message" => "Subscriptions retrieved successfully",
                "data" => [
                    "subscriptions" => $subscriptions->items(),
                    "pagination" => [
                        "total" => $subscriptions->total(),
                        "per_page" => $subscriptions->perPage(),
                        "current_page" => $subscriptions->currentPage(),
                        "last_page" => $subscriptions->lastPage(),
                        "has_more" => $subscriptions->hasMorePages(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to get subscriptions",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Get subscription details
     *
     * @param Request $request
     * @param string $subscriptionId
     * @return JsonResponse
     */
    public function getSubscription(
        Request $request,
        string $subscriptionId,
    ): JsonResponse {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Authentication required",
                    ],
                    401,
                );
            }

            $subscription = Transaction::where("user_id", $user->id)
                ->where("user_type", get_class($user))
                ->where("is_subscription", true)
                ->where(function ($query) use ($subscriptionId) {
                    $query
                        ->where("reference", $subscriptionId)
                        ->orWhere("subscription_id", $subscriptionId);
                })
                ->first();

            if (!$subscription) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Subscription not found",
                    ],
                    404,
                );
            }

            // Get subscription details from gateway
            $gatewayInstance = Payment::gateway($subscription->gateway);
            $gatewayDetails = $gatewayInstance->getSubscription(
                $subscription->subscription_id,
            );

            return response()->json([
                "success" => true,
                "message" => "Subscription details retrieved successfully",
                "data" => array_merge($gatewayDetails, [
                    "subscription" => $subscription->toArray(),
                ]),
            ]);
        } catch (PaymentGatewayException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Failed to get subscription details from gateway",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to get subscription details",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
