<?php

namespace Lisosoft\PaymentGateway\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'gateway_subscription_id' => $this->gateway_subscription_id,
            'gateway_customer_id' => $this->gateway_customer_id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'frequency' => $this->frequency,
            'frequency_label' => $this->getFrequencyLabel(),
            'interval' => $this->interval,
            'interval_unit' => $this->interval_unit,
            'billing_cycle' => $this->billing_cycle,
            'total_cycles' => $this->total_cycles,
            'cycles_completed' => $this->cycles_completed,
            'cycles_remaining' => $this->total_cycles - $this->cycles_completed,
            'next_billing_date' => $this->next_billing_date ? $this->next_billing_date->toISOString() : null,
            'next_billing_date_formatted' => $this->next_billing_date ? $this->next_billing_date->format('F d, Y') : null,
            'last_billing_date' => $this->last_billing_date ? $this->last_billing_date->toISOString() : null,
            'start_date' => $this->start_date ? $this->start_date->toISOString() : null,
            'end_date' => $this->end_date ? $this->end_date->toISOString() : null,
            'trial_ends_at' => $this->trial_ends_at ? $this->trial_ends_at->toISOString() : null,
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
                'address' => $this->customer_address,
            ],
            'metadata' => $this->metadata ? json_decode($this->metadata, true) : [],
            'gateway_response' => $this->gateway_response ? json_decode($this->gateway_response, true) : [],
            'payment_method' => $this->payment_method,
            'payment_method_details' => $this->payment_method_details ? json_decode($this->payment_method_details, true) : [],
            'auto_renew' => (bool) $this->auto_renew,
            'grace_period_days' => $this->grace_period_days,
            'retry_count' => $this->retry_count,
            'max_retries' => $this->max_retries,
            'cancelled_at' => $this->cancelled_at ? $this->cancelled_at->toISOString() : null,
            'cancelled_by' => $this->cancelled_by,
            'cancellation_reason' => $this->cancellation_reason,
            'paused_at' => $this->paused_at ? $this->paused_at->toISOString() : null,
            'resumed_at' => $this->resumed_at ? $this->resumed_at->toISOString() : null,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'links' => [
                'self' => route('api.subscriptions.show', $this->id),
                'cancel' => $this->status === 'active' ? route('api.subscriptions.cancel', $this->id) : null,
                'pause' => $this->status === 'active' ? route('api.subscriptions.pause', $this->id) : null,
                'resume' => $this->status === 'paused' ? route('api.subscriptions.resume', $this->id) : null,
                'update_payment_method' => route('api.subscriptions.update-payment-method', $this->id),
                'transactions' => route('api.subscriptions.transactions', $this->id),
            ],
            'stats' => [
                'total_paid' => (float) $this->total_paid,
                'total_attempts' => $this->total_attempts,
                'successful_payments' => $this->successful_payments,
                'failed_payments' => $this->failed_payments,
                'success_rate' => $this->total_attempts > 0
                    ? round(($this->successful_payments / $this->total_attempts) * 100, 2)
                    : 0,
            ],
        ];
    }

    /**
     * Get human-readable frequency label.
     *
     * @return string
     */
    protected function getFrequencyLabel()
    {
        $labels = [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'biweekly' => 'Bi-weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'semiannually' => 'Semi-annually',
            'annually' => 'Annually',
            'custom' => 'Custom',
        ];

        return $labels[$this->frequency] ?? ucfirst($this->frequency);
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
                'subscription_status' => $this->getSubscriptionStatus(),
                'upcoming_payments' => $this->getUpcomingPayments(),
                'security' => [
                    'encrypted' => true,
                    'pci_compliant' => true,
                ],
            ],
        ];
    }

    /**
     * Get detailed subscription status information.
     *
     * @return array
     */
    protected function getSubscriptionStatus()
    {
        $status = [
            'code' => $this->status,
            'message' => $this->getStatusMessage(),
            'is_active' => $this->status === 'active',
            'is_paused' => $this->status === 'paused',
            'is_cancelled' => $this->status === 'cancelled',
            'is_expired' => $this->status === 'expired',
            'is_trial' => $this->trial_ends_at && $this->trial_ends_at->isFuture(),
            'can_cancel' => in_array($this->status, ['active', 'paused']),
            'can_pause' => $this->status === 'active',
            'can_resume' => $this->status === 'paused',
            'can_update' => in_array($this->status, ['active', 'paused']),
            'days_until_next_billing' => $this->next_billing_date
                ? now()->diffInDays($this->next_billing_date, false)
                : null,
            'trial_days_remaining' => $this->trial_ends_at
                ? max(0, now()->diffInDays($this->trial_ends_at, false))
                : null,
        ];

        // Add gateway-specific status if available
        if ($this->gateway_response) {
            $response = json_decode($this->gateway_response, true);
            if (isset($response['gateway_status'])) {
                $status['gateway_status'] = $response['gateway_status'];
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
            'active' => 'Subscription is active',
            'paused' => 'Subscription is paused',
            'cancelled' => 'Subscription is cancelled',
            'expired' => 'Subscription has expired',
            'pending' => 'Subscription is pending activation',
            'failed' => 'Subscription activation failed',
            'trial' => 'Subscription is in trial period',
        ];

        return $messages[$this->status] ?? 'Unknown status';
    }

    /**
     * Get upcoming payment schedule.
     *
     * @return array
     */
    protected function getUpcomingPayments()
    {
        $payments = [];

        if (!$this->next_billing_date || $this->status !== 'active') {
            return $payments;
        }

        $date = clone $this->next_billing_date;
        $remainingCycles = $this->total_cycles - $this->cycles_completed;

        // Limit to next 12 payments or remaining cycles
        $limit = min(12, $remainingCycles);

        for ($i = 0; $i < $limit; $i++) {
            $payments[] = [
                'cycle' => $this->cycles_completed + $i + 1,
                'date' => $date->toISOString(),
                'date_formatted' => $date->format('F d, Y'),
                'amount' => (float) $this->amount,
                'currency' => $this->currency,
                'status' => $i === 0 ? 'upcoming' : 'scheduled',
            ];

            // Calculate next date based on frequency
            switch ($this->frequency) {
                case 'daily':
                    $date->addDay();
                    break;
                case 'weekly':
                    $date->addWeek();
                    break;
                case 'biweekly':
                    $date->addWeeks(2);
                    break;
                case 'monthly':
                    $date->addMonth();
                    break;
                case 'quarterly':
                    $date->addMonths(3);
                    break;
                case 'semiannually':
                    $date->addMonths(6);
                    break;
                case 'annually':
                    $date->addYear();
                    break;
                default:
                    // For custom intervals
                    if ($this->interval && $this->interval_unit) {
                        $method = 'add' . ucfirst($this->interval_unit) . 's';
                        if (method_exists($date, $method)) {
                            $date->$method($this->interval);
                        }
                    }
                    break;
            }
        }

        return $payments;
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
        $response->header('X-Subscription-ID', $this->id);
        $response->header('X-Subscription-Reference', $this->reference);
        $response->header('X-Subscription-Status', $this->status);

        if ($this->next_billing_date) {
            $response->header('X-Next-Billing-Date', $this->next_billing_date->toISOString());
        }

        // Add cache headers
        $response->header('Cache-Control', 'private, max-age=300'); // Cache for 5 minutes
    }
}
