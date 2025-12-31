<?php

namespace Lisosoft\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;

class Subscription extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_subscriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference',
        'user_id',
        'user_type',
        'gateway',
        'gateway_subscription_id',
        'gateway_customer_id',
        'amount',
        'currency',
        'status',
        'description',
        'customer_email',
        'customer_name',
        'customer_phone',
        'payment_method',
        'card_last_four',
        'card_brand',
        'card_expiry_month',
        'card_expiry_year',
        'frequency',
        'interval',
        'interval_count',
        'start_date',
        'end_date',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'cancelled_at',
        'cancel_reason',
        'trial_start',
        'trial_end',
        'billing_cycle_anchor',
        'days_until_due',
        'collection_method',
        'metadata',
        'notes',
        'attempts',
        'max_attempts',
        'retry_at',
        'last_payment_date',
        'last_transaction_id',
        'total_payments',
        'total_amount',
        'next_billing_date',
        'grace_period_ends_at',
        'auto_renew',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'trial_start' => 'datetime',
        'trial_end' => 'datetime',
        'billing_cycle_anchor' => 'datetime',
        'last_payment_date' => 'datetime',
        'next_billing_date' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'retry_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'auto_renew' => 'boolean',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'total_payments' => 'integer',
        'interval_count' => 'integer',
        'days_until_due' => 'integer',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'card_last_four',
        'card_brand',
        'card_expiry_month',
        'card_expiry_year',
    ];

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscription) {
            if (empty($subscription->reference)) {
                $subscription->reference = static::generateReference();
            }
        });
    }

    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the transactions for the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'subscription_id', 'reference');
    }

    /**
     * Get the last transaction for the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function lastTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'last_transaction_id');
    }

    /**
     * Scope a query to only include active subscriptions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include cancelled subscriptions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to only include past due subscriptions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePastDue($query)
    {
        return $query->where('status', 'past_due');
    }

    /**
     * Scope a query to only include trialing subscriptions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTrialing($query)
    {
        return $query->where('status', 'trialing');
    }

    /**
     * Scope a query to only include subscriptions due for renewal.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDueForRenewal($query)
    {
        return $query->where('next_billing_date', '<=', now())
                     ->whereIn('status', ['active', 'past_due'])
                     ->where('auto_renew', true);
    }

    /**
     * Scope a query to only include subscriptions in grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInGracePeriod($query)
    {
        return $query->where('status', 'past_due')
                     ->where('grace_period_ends_at', '>', now());
    }

    /**
     * Check if the subscription is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the subscription is cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if the subscription is past due.
     *
     * @return bool
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if the subscription is trialing.
     *
     * @return bool
     */
    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    /**
     * Check if the subscription is in trial period.
     *
     * @return bool
     */
    public function onTrial(): bool
    {
        return $this->trial_end && $this->trial_end->isFuture();
    }

    /**
     * Check if the subscription is in grace period.
     *
     * @return bool
     */
    public function onGracePeriod(): bool
    {
        return $this->grace_period_ends_at && $this->grace_period_ends_at->isFuture();
    }

    /**
     * Check if the subscription is due for renewal.
     *
     * @return bool
     */
    public function isDueForRenewal(): bool
    {
        return $this->next_billing_date && $this->next_billing_date->lte(now()) &&
               $this->isActive() && $this->auto_renew;
    }

    /**
     * Check if the subscription has been cancelled but is still active until period end.
     *
     * @return bool
     */
    public function isCancelledButActive(): bool
    {
        return $this->cancel_at_period_end && $this->isActive();
    }

    /**
     * Calculate the next billing date.
     *
     * @return \Carbon\Carbon|null
     */
    public function calculateNextBillingDate(): ?Carbon
    {
        if (!$this->current_period_end) {
            return null;
        }

        return match ($this->frequency) {
            'daily' => $this->current_period_end->copy()->addDays($this->interval_count ?? 1),
            'weekly' => $this->current_period_end->copy()->addWeeks($this->interval_count ?? 1),
            'monthly' => $this->current_period_end->copy()->addMonths($this->interval_count ?? 1),
            'quarterly' => $this->current_period_end->copy()->addMonths(3 * ($this->interval_count ?? 1)),
            'yearly' => $this->current_period_end->copy()->addYears($this->interval_count ?? 1),
            default => $this->current_period_end->copy()->addMonths($this->interval_count ?? 1),
        };
    }

    /**
     * Update the subscription status.
     *
     * @param  string  $status
     * @param  string|null  $reason
     * @return bool
     */
    public function updateStatus(string $status, ?string $reason = null): bool
    {
        $updateData = ['status' => $status];

        if ($status === 'cancelled') {
            $updateData['cancelled_at'] = now();
            $updateData['cancel_reason'] = $reason;
        }

        return $this->update($updateData);
    }

    /**
     * Cancel the subscription.
     *
     * @param  bool  $atPeriodEnd
     * @param  string|null  $reason
     * @return bool
     */
    public function cancel(bool $atPeriodEnd = true, ?string $reason = null): bool
    {
        $updateData = [
            'cancel_at_period_end' => $atPeriodEnd,
            'cancel_reason' => $reason,
        ];

        if (!$atPeriodEnd) {
            $updateData['status'] = 'cancelled';
            $updateData['cancelled_at'] = now();
            $updateData['next_billing_date'] = null;
        }

        return $this->update($updateData);
    }

    /**
     * Reactivate a cancelled subscription.
     *
     * @return bool
     */
    public function reactivate(): bool
    {
        if (!$this->isCancelled()) {
            return false;
        }

        return $this->update([
            'status' => 'active',
            'cancel_at_period_end' => false,
            'cancel_reason' => null,
            'next_billing_date' => $this->calculateNextBillingDate(),
        ]);
    }

    /**
     * Record a successful payment.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @return bool
     */
    public function recordSuccessfulPayment(Transaction $transaction): bool
    {
        $nextBillingDate = $this->calculateNextBillingDate();

        return $this->update([
            'last_payment_date' => now(),
            'last_transaction_id' => $transaction->id,
            'current_period_start' => $this->current_period_end ?? now(),
            'current_period_end' => $nextBillingDate,
            'next_billing_date' => $nextBillingDate,
            'status' => 'active',
            'attempts' => 0,
            'retry_at' => null,
            'total_payments' => $this->total_payments + 1,
            'total_amount' => $this->total_amount + $transaction->amount,
        ]);
    }

    /**
     * Record a failed payment.
     *
     * @param  string  $errorMessage
     * @param  string|null  $errorCode
     * @return bool
     */
    public function recordFailedPayment(string $errorMessage, ?string $errorCode = null): bool
    {
        $attempts = $this->attempts + 1;
        $maxAttempts = $this->max_attempts ?? config('payment-gateway.recurring.retry_attempts', 3);
        $retryInterval = config('payment-gateway.recurring.retry_interval_hours', 24);

        $updateData = [
            'attempts' => $attempts,
            'retry_at' => now()->addHours($retryInterval),
        ];

        if ($attempts >= $maxAttempts) {
            $updateData['status'] = 'past_due';
            $updateData['grace_period_ends_at'] = now()->addDays(
                config('payment-gateway.recurring.grace_period_days', 3)
            );
        }

        return $this->update($updateData);
    }

    /**
     * Get the display name for the subscription frequency.
     *
     * @return string
     */
    public function getFrequencyDisplayAttribute(): string
    {
        $interval = $this->interval_count > 1 ? "every {$this->interval_count} " : '';

        return match ($this->frequency) {
            'daily' => $interval . 'day' . ($this->interval_count > 1 ? 's' : ''),
            'weekly' => $interval . 'week' . ($this->interval_count > 1 ? 's' : ''),
            'monthly' => $interval . 'month' . ($this->interval_count > 1 ? 's' : ''),
            'quarterly' => $interval . 'quarter' . ($this->interval_count > 1 ? 's' : ''),
            'yearly' => $interval . 'year' . ($this->interval_count > 1 ? 's' : ''),
            default => $this->frequency,
        };
    }

    /**
     * Get the amount formatted with currency.
     *
     * @return string
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    /**
     * Get the total amount formatted with currency.
     *
     * @return string
     */
    public function getFormattedTotalAmountAttribute(): string
    {
        return number_format($this->total_amount, 2) . ' ' . $this->currency;
    }

    /**
     * Generate a unique reference for the subscription.
     *
     * @return string
     */
    public static function generateReference(): string
    {
        $prefix = 'SUB-';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return $prefix . $timestamp . '-' . $random;
    }

    /**
     * Find a subscription by reference.
     *
     * @param  string  $reference
     * @return \Lisosoft\PaymentGateway\Models\Subscription|null
     */
    public static function findByReference(string $reference): ?self
    {
        return static::where('reference', $reference)->first();
    }

    /**
     * Find a subscription by gateway subscription ID.
     *
     * @param  string  $gateway
     * @param  string  $gatewaySubscriptionId
     * @return \Lisosoft\PaymentGateway\Models\Subscription|null
     */
    public static function findByGatewaySubscriptionId(string $gateway, string $gatewaySubscriptionId): ?self
    {
        return static::where('gateway', $gateway)
                     ->where('gateway_subscription_id', $gatewaySubscriptionId)
                     ->first();
    }
}
