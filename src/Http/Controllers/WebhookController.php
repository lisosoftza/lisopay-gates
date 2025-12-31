<?php

namespace Lisosoft\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Lisosoft\PaymentGateway\Facades\Payment;
use Lisosoft\PaymentGateway\Models\Transaction;
use Lisosoft\PaymentGateway\Events\PaymentCompleted;
use Lisosoft\PaymentGateway\Events\PaymentFailed;
use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;

class WebhookController extends Controller
{
    /**
     * Handle PayFast ITN (Instant Transaction Notification)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handlePayFast(Request $request): JsonResponse
    {
        try {
            Log::info('PayFast ITN received', ['data' => $request->all()]);

            // Process PayFast callback
            $result = Payment::processCallback('payfast', $request->all());

            // Update transaction based on callback
            $this->updateTransactionFromCallback('payfast', $result);

            // Return success response to PayFast
            return response()->json(['status' => 'complete']);

        } catch (PaymentGatewayException $e) {
            Log::error('PayFast ITN processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected error in PayFast ITN', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle PayStack webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handlePayStack(Request $request): JsonResponse
    {
        try {
            Log::info('PayStack webhook received', ['data' => $request->all()]);

            // Validate PayStack signature
            $signature = $request->header('x-paystack-signature');
            if (!$this->validatePayStackSignature($request->getContent(), $signature)) {
                throw new PaymentGatewayException('Invalid PayStack signature');
            }

            // Process PayStack callback
            $result = Payment::processCallback('paystack', $request->all());

            // Update transaction based on callback
            $this->updateTransactionFromCallback('paystack', $result);

            return response()->json(['status' => 'success']);

        } catch (PaymentGatewayException $e) {
            Log::error('PayStack webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected error in PayStack webhook', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle PayPal webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handlePayPal(Request $request): JsonResponse
    {
        try {
            Log::info('PayPal webhook received', ['data' => $request->all()]);

            // Validate PayPal signature
            $transmissionId = $request->header('paypal-transmission-id');
            $timestamp = $request->header('paypal-transmission-time');
            $certUrl = $request->header('paypal-cert-url');
            $authAlgo = $request->header('paypal-auth-algo');
            $transmissionSig = $request->header('paypal-transmission-sig');

            if (!$this->validatePayPalSignature($request->getContent(), $transmissionId, $timestamp, $certUrl, $authAlgo, $transmissionSig)) {
                throw new PaymentGatewayException('Invalid PayPal signature');
            }

            // Process PayPal callback
            $result = Payment::processCallback('paypal', $request->all());

            // Update transaction based on callback
            $this->updateTransactionFromCallback('paypal', $result);

            return response()->json(['status' => 'success']);

        } catch (PaymentGatewayException $e) {
            Log::error('PayPal webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected error in PayPal webhook', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle Stripe webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleStripe(Request $request): JsonResponse
    {
        try {
            Log::info('Stripe webhook received', ['type' => $request->input('type')]);

            // Validate Stripe signature
            $signature = $request->header('stripe-signature');
            if (!$this->validateStripeSignature($request->getContent(), $signature)) {
                throw new PaymentGatewayException('Invalid Stripe signature');
            }

            // Process Stripe event
            $eventType = $request->input('type');
            $eventData = $request->input('data.object');

            // Map Stripe event to our callback data
            $callbackData = $this->mapStripeEventToCallbackData($eventType, $eventData);

            // Process callback
            $result = Payment::processCallback('stripe', $callbackData);

            // Update transaction based on callback
            $this->updateTransactionFromCallback('stripe', $result);

            return response()->json(['received' => true]);

        } catch (PaymentGatewayException $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'type' => $request->input('type'),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected error in Stripe webhook', [
                'error' => $e->getMessage(),
                'type' => $request->input('type'),
            ]);

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle Ozow callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleOzow(Request $request): JsonResponse
    {
        try {
            Log::info('Ozow callback received', ['data' => $request->all()]);

            // Process Ozow callback
            $result = Payment::processCallback('ozow', $request->all());

            // Update transaction based on callback
            $this->updateTransactionFromCallback('ozow', $result);

            return response()->json(['status' => 'success']);

        } catch (PaymentGatewayException $e) {
            Log::error('Ozow callback processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected error in Ozow callback', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle Zapper callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleZapper(Request $request): JsonResponse
    {
        try {
            Log::info('Zapper callback received', ['data' => $request->all()]);

            // Process Zapper callback
            $result = Payment::processCallback('zapper', $request->all());

            // Update transaction based on callback
            $this->updateTransactionFromCallback('zapper', $result);

            return response()->json(['status' => 'success']);

        } catch (PaymentGatewayException $e) {
            Log::error('Zapper callback processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected error in Zapper callback', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle Crypto webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleCrypto(Request $request): JsonResponse
    {
        try {
            Log::info('Crypto webhook received', ['data' => $request->all()]);

            // Process Crypto callback
            $result = Payment::processCallback('crypto', $request->all());

            // Update transaction based on callback
            $this->updateTransactionFromCallback('crypto', $result);

            return response()->json(['status' => 'success']);

        } catch (PaymentGatewayException $e) {
            Log::error('Crypto webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected error in Crypto webhook', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle VodaPay callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleVodaPay(Request $request): JsonResponse
    {
        try {
            Log::info('VodaPay callback received', ['data' => $request->all()]);

            // Process VodaPay callback
            $result = Payment::processCallback('vodapay', $request->all());

            // Update transaction based on callback
            $this->updateTransactionFromCallback('vodapay', $result);

            return response()->json(['status' => 'success']);

        } catch (PaymentGatewayException $e) {
            Log::error('VodaPay callback processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected error in VodaPay callback', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle SnapScan callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleSnapScan(Request $request): JsonResponse
    {
        try {
            Log::info('SnapScan callback received', ['data' => $request->all()]);

            // Process SnapScan callback
            $result = Payment::processCallback('snapscan', $request->all());

            // Update transaction based on callback
            $this->updateTransactionFromCallback('snapscan', $result);

            return response()->json(['status' => 'success']);

        } catch (PaymentGatewayException $e) {
            Log::error('SnapScan callback processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected error in SnapScan callback', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle generic webhook/callback
     *
     * @param Request $request
     * @param string $gateway
     * @return JsonResponse
     */
    public function handleGeneric(Request $request, string $gateway): JsonResponse
    {
        try {
            Log::info("Generic {$gateway} webhook received", ['data' => $request->all()]);

            // Validate gateway
            if (!Payment::isGatewayAvailable($gateway)) {
                throw new PaymentGatewayException("Gateway '{$gateway}' is not available");
            }

            // Process generic callback
            $result = Payment::processCallback($gateway, $request->all());

            // Update transaction based on callback
            $this->updateTransactionFromCallback($gateway, $result);

            return response()->json(['status' => 'success']);

        } catch (PaymentGatewayException $e) {
            Log::error("Generic {$gateway} webhook processing failed", [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error("Unexpected error in generic {$gateway} webhook", [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update transaction from callback result
     *
     * @param string $gateway
     * @param array $callbackResult
     * @return void
     */
    protected function updateTransactionFromCallback(string $gateway, array $callbackResult): void
    {
        try {
            // Find transaction by reference or gateway transaction ID
            $transactionReference = $callbackResult['transaction_id'] ?? null;
            $gatewayTransactionId = $callbackResult['gateway_transaction_id'] ?? null;

            $transaction = null;
            if ($transactionReference) {
                $transaction = Transaction::where('reference', $transactionReference)->first();
            }

            if (!$transaction && $gatewayTransactionId) {
                $transaction = Transaction::where('gateway_transaction_id', $gatewayTransactionId)->first();
            }

            if (!$transaction) {
                Log::warning('Transaction not found for callback', [
                    'gateway' => $gateway,
                    'callback_result' => $callbackResult,
                ]);
                return;
            }

            // Update transaction status
            $newStatus = $callbackResult['status'] ?? null;
            if ($newStatus && $newStatus !== $transaction->status) {
                $updateData = [
                    'gateway_transaction_id' => $gatewayTransactionId ?? $transaction->gateway_transaction_id,
                    'metadata' => array_merge($transaction->metadata ?? [], $callbackResult['raw_data'] ?? []),
                ];

                // Add fee information if available
                if (isset($callbackResult['amount_fee'])) {
                    $updateData['fee_amount'] = $callbackResult['amount_fee'];
                }

                if (isset($callbackResult['amount_net'])) {
                    $updateData['net_amount'] = $callbackResult['amount_net'];
                }

                $transaction->updateStatus($newStatus, $updateData);

                // Trigger events based on status
                if ($transaction->is_successful) {
                    event(new PaymentCompleted(
                        $transaction,
                        $gateway,
                        $transaction->amount,
                        $transaction->currency,
                        $callbackResult
                    ));
                } elseif ($transaction->is_failed) {
                    event(new PaymentFailed(
                        $transaction,
                        $gateway,
                        $transaction->amount,
                        $transaction->currency,
                        $callbackResult['error_message'] ?? 'Payment failed via webhook',
                        $callbackResult['error_code'] ?? null,
                        $callbackResult['error_details'] ?? [],
                        $callbackResult
                    ));
                }

                Log::info('Transaction updated from webhook', [
                    'transaction_reference' => $transaction->reference,
                    'old_status' => $transaction->getOriginal('status'),
                    'new_status' => $newStatus,
                    'gateway' => $gateway,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to update transaction from callback', [
                'error' => $e->getMessage(),
                'gateway' => $gateway,
                'callback_result' => $callbackResult,
            ]);
        }
    }

    /**
     * Validate PayStack webhook signature
     *
     * @param string $payload
     * @param string $signature
     * @return bool
     */
    protected function validatePayStackSignature(string $payload, string $signature): bool
    {
        $secretKey = config('payment-gateway.gateways.paystack.secret_key');
        if (!$secretKey) {
            return false;
        }

        $expectedSignature = hash_hmac('sha512', $payload, $secretKey);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate PayPal webhook signature
     *
     * @param string $payload
     * @param string $transmissionId
     * @param string $timestamp
     * @param string $certUrl
     * @param string $authAlgo
     * @param string $transmissionSig
     * @return bool
     */
    protected function validatePayPalSignature(
        string $payload,
        string $transmissionId,
        string $timestamp,
        string $certUrl,
        string $authAlgo,
        string $transmissionSig
    ): bool {
        // This is a simplified validation
        // In production, you should implement proper PayPal webhook signature validation
        // as per PayPal's documentation

        $webhookId = config('payment-gateway.gateways.paypal.webhook_id');
        if (!$webhookId) {
            return false;
        }

        // For now, return true in development/test environments
        if (app()->environment('local', 'testing')) {
            return true;
        }

        // In production, you would implement the actual validation
        // using PayPal's SDK or API
        return false;
    }

    /**
     * Validate Stripe webhook signature
     *
     * @param string $payload
     * @param string $signature
     * @return bool
     */
    protected function validateStripeSignature(string $payload, string $signature): bool
    {
        $webhookSecret = config('payment-gateway.gateways.stripe.webhook_secret');
        if (!$webhookSecret) {
            return false;
        }

        // This is a simplified validation
        // In production, you should implement proper Stripe webhook signature validation
        // as per Stripe's documentation

        // For now, return true in development/test environments
        if (app()->environment('local', 'testing')) {
            return true;
        }

        // In production, you would implement the actual validation
        // using Stripe's SDK or API
        return false;
    }

    /**
     * Map Stripe event to callback data
     *
     * @param string $eventType
     * @param array $eventData
     * @return array
     */
    protected function mapStripeEventToCallbackData(string $eventType, array $eventData): array
    {
        $callbackData = [
            'event_type' => $eventType,
            'raw_data' => $eventData,
        ];

        switch ($eventType) {
            case 'charge.succeeded':
                $callbackData['status'] = 'completed';
                $callbackData['transaction_id'] = $eventData['id'] ?? null;
                $callbackData['amount'] = $eventData['amount'] / 100; // Convert from cents
                $callbackData['currency'] = $eventData['currency'] ?? null;
                $callbackData['customer_email'] = $eventData['billing_details']['email'] ?? null;
                break;

            case 'charge.failed':
                $callbackData['status'] = 'failed';
                $callbackData['transaction_id'] = $eventData['id'] ?? null;
                $callbackData['error_message'] = $eventData['failure_message'] ?? 'Payment failed';
                $callbackData['error_code'] = $eventData['failure_code'] ?? null;
                break;

            case 'charge.refunded':
                $callbackData['status'] = 'refunded';
                $callbackData['transaction_id'] = $eventData['id'] ?? null;
                $callbackData['refund_amount'] = $eventData['amount_refunded'] / 100;
                break;

            case 'payment_intent.succeeded':
                $callbackData['status'] = 'completed';
                $callbackData['transaction_id'] = $eventData['id'] ?? null;
                $callbackData['amount'] = $eventData['amount'] / 100;
                $callbackData['currency'] = $eventData['currency'] ?? null;
                break;

            case 'payment_intent.payment_failed':
                $callbackData['status'] = 'failed';
                $callbackData['transaction_id'] = $eventData['id'] ?? null;
                $callbackData['error_message'] = $eventData['last_payment_error']['message'] ?? 'Payment failed';
                break;
        }

        return $callbackData;
    }
}
