<?php

namespace Lisosoft\PaymentGateway\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'gateway' => $this->gateway,
            'gateway_name' => $this->gateway_name,
            'gateway_icon' => $this->gateway_icon,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
                'address' => $this->customer_address,
            ],
            'metadata' => $this->metadata ? json_decode($this->metadata, true) : [],
            'gateway_response' => $this->gateway_response ? json_decode($this->gateway_response, true) : [],
            'gateway_transaction_id' => $this->gateway_transaction_id,
            'gateway_reference' => $this->gateway_reference,
            'payment_method' => $this->payment_method,
            'payment_method_details' => $this->payment_method_details ? json_decode($this->payment_method_details, true) : [],
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'webhook_received' => (bool) $this->webhook_received,
            'webhook_processed' => (bool) $this->webhook_processed,
            'webhook_response' => $this->webhook_response ? json_decode($this->webhook_response, true) : [],
            'refunded' => (bool) $this->refunded,
            'refund_amount' => $this->refund_amount ? (float) $this->refund_amount : null,
            'refund_reason' => $this->refund_reason,
            'refunded_at' => $this->refunded_at ? $this->refunded_at->toISOString() : null,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'completed_at' => $this->completed_at ? $this->completed_at->toISOString() : null,
            'failed_at' => $this->failed_at ? $this->failed_at->toISOString() : null,
            'cancelled_at' => $this->cancelled_at ? $this->cancelled_at->toISOString() : null,
            'links' => [
                'self' => route('api.payments.show', $this->id),
                'refund' => $this->status === 'completed' ? route('api.payments.refund', $this->id) : null,
                'receipt' => route('api.payments.receipt', $this->id),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        return [
            'meta' => [
                'version' => '1.0',
                'api_version' => config('payment-gateway.api_version', 'v1'),
                'timestamp' => now()->toISOString(),
                'gateway_status' => $this->getGatewayStatus(),
                'security' => [
                    'encrypted' => true,
                    'pci_compliant' => true,
                ],
            ],
        ];
    }

    /**
     * Get the gateway-specific status information.
     *
     * @return array
     */
    protected function getGatewayStatus()
    {
        $status = [
            'code' => $this->status,
            'message' => $this->getStatusMessage(),
            'is_final' => in_array($this->status, ['completed', 'failed', 'cancelled', 'refunded']),
            'can_retry' => $this->status === 'failed',
            'can_refund' => $this->status === 'completed' && !$this->refunded,
            'can_cancel' => $this->status === 'pending',
        ];

        // Add gateway-specific status codes if available
        if ($this->gateway_response) {
            $response = json_decode($this->gateway_response, true);
            if (isset($response['status_code'])) {
                $status['gateway_code'] = $response['status_code'];
            }
            if (isset($response['status_message'])) {
                $status['gateway_message'] = $response['status_message'];
            }
        }

        return $status;
    }

    /**
     * Get human-readable status message.
     *
     * @return string
     */
    protected function getStatusMessage()
    {
        $messages = [
            'pending' => 'Payment is pending confirmation',
            'processing' => 'Payment is being processed',
            'completed' => 'Payment completed successfully',
            'failed' => 'Payment failed',
            'cancelled' => 'Payment was cancelled',
            'refunded' => 'Payment has been refunded',
            'partially_refunded' => 'Payment partially refunded',
            'disputed' => 'Payment is under dispute',
            'chargeback' => 'Payment has been charged back',
        ];

        return $messages[$this->status] ?? 'Unknown status';
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        $response->header('X-Payment-API-Version', config('payment-gateway.api_version', 'v1'));
        $response->header('X-Transaction-ID', $this->id);
        $response->header('X-Transaction-Reference', $this->reference);

        // Add cache headers
        if ($this->status === 'completed' || $this->status === 'failed') {
            $response->header('Cache-Control', 'public, max-age=3600'); // Cache for 1 hour
        }
    }
}
