<?php

namespace Lisosoft\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Lisosoft\PaymentGateway\Facades\Payment;
use Lisosoft\PaymentGateway\Models\Transaction;
use Lisosoft\PaymentGateway\Events\PaymentCompleted;
use Lisosoft\PaymentGateway\Events\PaymentFailed;
use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;

class PaymentController extends Controller
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
                'gateway' => 'required|string|in:payfast,paystack,paypal,stripe,ozow,zapper,crypto,eft,vodapay,snapscan',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'required|string|size:3',
                'description' => 'required|string|max:255',
                'customer_email' => 'required|email',
                'customer_name' => 'nullable|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'metadata' => 'nullable|array',
                'return_url' => 'nullable|url',
                'cancel_url' => 'nullable|url',
                'webhook_url' => 'nullable|url',
                'is_subscription' => 'nullable|boolean',
                'subscription_data' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $gateway = $request->input('gateway');
            $paymentData = $request->only([
                'amount', 'currency', 'description', 'customer_email',
                'customer_name', 'customer_phone', 'metadata',
                'return_url', 'cancel_url', 'webhook_url',
            ]);

            // Add customer data
            $paymentData['customer'] = [
                'email' => $request->input('customer_email'),
                'name' => $request->input('customer_name'),
                'phone' => $request->input('customer_phone'),
            ];

            // Add subscription data if applicable
            if ($request->boolean('is_subscription')) {
                $paymentData['is_subscription'] = true;
                $paymentData['subscription_data'] = $request->input('subscription_data', []);
            }

            // Add request information
            $paymentData['ip_address'] = $request->ip();
            $paymentData['user_agent'] = $request->userAgent();

            // Check if gateway is available
            if (!Payment::isGatewayAvailable($gateway)) {
                return response()->json([
                    'success' => false,
                    'message' => "Payment gateway '{$gateway}' is not available",
                    'gateway' => $gateway,
                ], 400);
            }

            // Initialize payment with gateway
            $result = Payment::initializePayment($gateway, $paymentData);

            // Create transaction record
            $transaction = Transaction::create([
                'reference' => $result['transaction_id'] ?? Transaction::generateReference(),
                'gateway' => $gateway,
                'gateway_transaction_id' => $result['gateway_transaction_id'] ?? null,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'],
                'status' => 'pending',
                'description' => $paymentData['description'],
                'customer_email' => $paymentData['customer']['email'],
                'customer_name' => $paymentData['customer']['name'],
                'customer_phone' => $paymentData['customer']['phone'],
                'is_subscription' => $paymentData['is_subscription'] ?? false,
                'subscription_id' => $result['subscription_id'] ?? null,
                'return_url' => $paymentData['return_url'] ?? null,
                'cancel_url' => $paymentData['cancel_url'] ?? null,
                'webhook_url' => $paymentData['webhook_url'] ?? null,
                'ip_address' => $paymentData['ip_address'],
                'user_agent' => $paymentData['user_agent'],
                'metadata' => $paymentData['metadata'] ?? [],
                'processed_at' => now(),
            ]);

            // Add transaction ID to result
            $result['transaction_reference'] = $transaction->reference;
            $result['transaction_id'] = $transaction->id;

            return response()->json([
                'success' => true,
                'message' => 'Payment initialized successfully',
                'data' => $result,
                'transaction' => $transaction->getSummary(),
            ]);

        } catch (PaymentGatewayException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment initialization failed',
                'error' => $e->getMessage(),
                'error_details' => $e->getErrors(),
                'gateway' => $request->input('gateway'),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
                'gateway' => $request->input('gateway'),
            ], 500);
        }
    }

    /**
     * Verify a payment
     *
     * @param Request $request
     * @param string $transactionReference
     * @return JsonResponse
     */
    public function verify(Request $request, string $transactionReference): JsonResponse
    {
        try {
            // Find transaction
            $transaction = Transaction::where('reference', $transactionReference)->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                    'transaction_reference' => $transactionReference,
                ], 404);
            }

            // Verify payment with gateway
            $result = Payment::verifyPayment(
                $transaction->gateway,
                $transaction->gateway_transaction_id ?? $transaction->reference
            );

            // Update transaction status
            if (isset($result['status'])) {
                $transaction->updateStatus($result['status'], [
                    'gateway_transaction_id' => $result['gateway_transaction_id'] ?? $transaction->gateway_transaction_id,
                    'metadata' => array_merge($transaction->metadata ?? [], $result['raw_data'] ?? []),
                ]);

                // Trigger events based on status
                if ($transaction->is_successful) {
                    event(new PaymentCompleted(
                        $transaction,
                        $transaction->gateway,
                        $transaction->amount,
                        $transaction->currency,
                        $result
                    ));
                } elseif ($transaction->is_failed) {
                    event(new PaymentFailed(
                        $transaction,
                        $transaction->gateway,
                        $transaction->amount,
                        $transaction->currency,
                        $result['error_message'] ?? 'Payment verification failed',
                        $result['error_code'] ?? null,
                        $result['error_details'] ?? [],
                        $result
                    ));
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment verification completed',
                'transaction' => $transaction->getSummary(),
                'verification_result' => $result,
            ]);

        } catch (PaymentGatewayException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage(),
                'error_details' => $e->getErrors(),
                'transaction_reference' => $transactionReference,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
                'transaction_reference' => $transactionReference,
            ], 500);
        }
    }

    /**
     * Get payment status
     *
     * @param Request $request
     * @param string $transactionReference
     * @return JsonResponse
     */
    public function status(Request $request, string $transactionReference): JsonResponse
    {
        try {
            // Find transaction
            $transaction = Transaction::where('reference', $transactionReference)->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                    'transaction_reference' => $transactionReference,
                ], 404);
            }

            // Get payment status from gateway
            $gatewayStatus = Payment::getPaymentStatus(
                $transaction->gateway,
                $transaction->gateway_transaction_id ?? $transaction->reference
            );

            // Update transaction if status has changed
            if ($gatewayStatus !== $transaction->status && $gatewayStatus !== 'unknown') {
                $transaction->updateStatus($gatewayStatus);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment status retrieved',
                'transaction' => $transaction->getSummary(),
                'gateway_status' => $gatewayStatus,
                'can_retry' => $transaction->canRetry(),
                'is_locked' => $transaction->isLocked(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status',
                'error' => $e->getMessage(),
                'transaction_reference' => $transactionReference,
            ], 500);
        }
    }

    /**
     * Process payment callback (for server-to-server notifications)
     *
     * @param Request $request
     * @param string $gateway
     * @return JsonResponse
     */
    public function callback(Request $request, string $gateway): JsonResponse
    {
        try {
            // Process callback with gateway
            $result = Payment::processCallback($gateway, $request->all());

            // Find transaction by reference or gateway transaction ID
            $transactionReference = $result['transaction_id'] ?? null;
            $gatewayTransactionId = $result['gateway_transaction_id'] ?? null;

            $transaction = null;
            if ($transactionReference) {
                $transaction = Transaction::where('reference', $transactionReference)->first();
            }

            if (!$transaction && $gatewayTransactionId) {
                $transaction = Transaction::where('gateway_transaction_id', $gatewayTransactionId)->first();
            }

            if ($transaction) {
                // Update transaction status
                $transaction->updateStatus($result['status'], [
                    'gateway_transaction_id' => $gatewayTransactionId ?? $transaction->gateway_transaction_id,
                    'metadata' => array_merge($transaction->metadata ?? [], $result['raw_data'] ?? []),
                    'fee_amount' => $result['amount_fee'] ?? $transaction->fee_amount,
                    'net_amount' => $result['amount_net'] ?? $transaction->net_amount,
                ]);

                // Trigger events based on status
                if ($transaction->is_successful) {
                    event(new PaymentCompleted(
                        $transaction,
                        $gateway,
                        $transaction->amount,
                        $transaction->currency,
                        $result
                    ));
                } elseif ($transaction->is_failed) {
                    event(new PaymentFailed(
                        $transaction,
                        $gateway,
                        $transaction->amount,
                        $transaction->currency,
                        $result['error_message'] ?? 'Payment failed via callback',
                        $result['error_code'] ?? null,
                        $result['error_details'] ?? [],
                        $result
                    ));
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Callback processed successfully',
                'transaction' => $transaction?->getSummary(),
                'callback_result' => $result,
            ]);

        } catch (PaymentGatewayException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Callback processing failed',
                'error' => $e->getMessage(),
                'error_details' => $e->getErrors(),
                'gateway' => $gateway,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
                'gateway' => $gateway,
            ], 500);
        }
    }

    /**
     * Refund a payment
     *
     * @param Request $request
     * @param string $transactionReference
     * @return JsonResponse
     */
    public function refund(Request $request, string $transactionReference): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'amount' => 'nullable|numeric|min:0.01',
                'reason' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Find transaction
            $transaction = Transaction::where('reference', $transactionReference)->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                    'transaction_reference' => $transactionReference,
                ], 404);
            }

            // Check if transaction can be refunded
            if (!$transaction->is_successful) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only successful transactions can be refunded',
                    'transaction_status' => $transaction->status,
                ], 400);
            }

            $refundAmount = $request->input('amount', $transaction->refundableAmount());
            $refundReason = $request->input('reason');

            // Check refund amount
            if ($refundAmount > $transaction->refundableAmount()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund amount exceeds refundable amount',
                    'refund_amount' => $refundAmount,
                    'refundable_amount' => $transaction->refundableAmount(),
                ], 400);
            }

            // Process refund with gateway
            $result = Payment::refundPayment(
                $transaction->gateway,
                $transaction->gateway_transaction_id ?? $transaction->reference,
                $refundAmount
            );

            // Create refund transaction
            $refundTransaction = $transaction->refund($refundAmount, $refundReason);

            if ($refundTransaction) {
                // Update refund transaction with gateway result
                $refundTransaction->update([
                    'gateway_transaction_id' => $result['refund_id'] ?? null,
                    'metadata' => array_merge($refundTransaction->metadata ?? [], $result),
                ]);

                // Update original transaction refund amount
                $transaction->refund_amount = $transaction->refund_amount + $refundAmount;

                if ($transaction->isFullyRefunded()) {
                    $transaction->updateStatus('refunded');
                } elseif ($transaction->isPartiallyRefunded()) {
                    $transaction->updateStatus('partially_refunded');
                }

                $transaction->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'original_transaction' => $transaction->getSummary(),
                'refund_transaction' => $refundTransaction?->getSummary(),
                'refund_result' => $result,
            ]);

        } catch (PaymentGatewayException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Refund processing failed',
                'error' => $e->getMessage(),
                'error_details' => $e->getErrors(),
                'transaction_reference' => $transactionReference,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
                'transaction_reference' => $transactionReference,
            ], 500);
        }
    }

    /**
     * Retry a failed payment
     *
     * @param Request $request
     * @param string $transactionReference
     * @return JsonResponse
     */
    public function retry(Request $request, string $transactionReference): JsonResponse
    {
        try {
            // Find transaction
            $transaction = Transaction::where('reference', $transactionReference)->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                    'transaction_reference' => $transactionReference,
                ], 404);
            }

            // Check if transaction can be retried
            if (!$transaction->canRetry()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction cannot be retried',
                    'can_retry' => false,
                    'attempts' => $transaction->attempts,
                    'max_attempts' => $transaction->max_attempts,
                    'status' => $transaction->status,
                ], 400);
            }

            // Mark transaction for retry
            $transaction->markForRetry();

            return response()->json([
                'success' => true,
                'message' => 'Transaction marked for retry',
                'transaction' => $transaction->getSummary(),
                'retry_at' => $transaction->retry_at?->toISOString(),
                'next_attempt' => $transaction->attempts,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry transaction',
                'error' => $e->getMessage(),
                'transaction_reference' => $transactionReference,
            ], 500);
        }
    }

    /**
     * Get available payment gateways
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function gateways(Request $request): JsonResponse
    {
        try {
            $availableGateways = Payment::getAvailableGateways();
            $statistics = Payment::getStatistics();

            return response()->json([
                'success' => true,
                'message' => 'Available payment gateways retrieved',
                'data' => [
                    'gateways' => $availableGateways,
                    'statistics' => $statistics,
                    'default_gateway' => Payment::getDefaultDriver(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment gateways',
                'error' => $e->getMessage(),
            ], 500);
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
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'status' => 'nullable|string|in:pending,processing,completed,failed,cancelled,refunded',
                'gateway' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'customer_email' => 'nullable|email',
                'search' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = Transaction::query();

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('gateway')) {
                $query->where('gateway', $request->input('gateway'));
            }

            if ($request->filled('customer_email')) {
                $query->where('customer_email', $request->input('customer_email'));
            }

            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->input('end_date'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('customer_email', 'like', "%{$search}%");
                });
            }

            // Order by latest first
            $query->orderBy('created_at', 'desc');

            // Paginate results
            $perPage = $request->input('per_page', 20);
            $transactions = $query->paginate($perPage);

            // Transform transactions
            $transactions->getCollection()->transform(function ($transaction) {
                return $transaction->getSummary();
            });

            // Calculate statistics
            $totalAmount = $query->sum('amount');
            $successfulCount = $query->clone()->successful()->count();
            $failedCount = $query->clone()->failed()->count();

            return response()->json([
                'success' => true,
                'message' => 'Transaction history retrieved',
                'data' => [
                    'transactions' => $transactions,
                    'statistics' => [
                        'total_transactions' => $transactions->total(),
                        'total_amount' => $totalAmount,
                        'successful_count' => $successfulCount,
                        'failed_count' => $failedCount,
                        'success_rate' => $transactions->total() > 0 ?
                            round(($successfulCount / $transactions->total()) * 100, 2) : 0,
                    ],
                    'filters' => $request->only([
                        'status', 'gateway', 'start_date', 'end_date', 'customer_email', 'search'
                    ]),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get transaction history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
