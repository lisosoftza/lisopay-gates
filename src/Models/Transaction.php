<?php

namespace Lisosoft\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Crypt;

class Transaction extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_transactions';

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
        'gateway_transaction_id',
        'amount',
        'currency',
        'status',
        'description',
        'customer_email',
        'customer_name',
        'customer_phone',
        'customer_address',
        'billing_address',
        'shipping_address',
        'metadata',
        'is_subscription',
        'subscription_id',
        'recurring_frequency',
        'recurring_cycles',
        'next_billing_date',
        'payment_method',
        'card_last_four',
        'card_brand',
        'card_expiry_month',
        'card_expiry_year',
        'ip_address',
        'user_agent',
        'return_url',
        'cancel_url',
        'webhook_url',
        'processed_at',
        'completed_at',
        'failed_at',
        'cancelled_at',
        'refunded_at',
        'refund_amount',
        'refund_reason',
        'fee_amount',
        'tax_amount',
        'discount_amount',
        'net_amount',
        'error_code',
        'error_message',
        'error_details',
        'attempts',
        'max_attempts',
        'retry_at',
        'locked_until',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'is_subscription' => 'boolean',
        'metadata' => 'array',
        'error_details' => 'array',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'next_billing_date' => 'datetime',
        'retry_at' => 'datetime',
        'locked_until' => 'datetime',
        'fee_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    /**
     * The attributes that should be encrypted.
     *
     * @var array<int, string>
     */
    protected $encrypted = [
        'customer_email',
        'customer_name',
        'customer_phone',
        'customer_address',
        'billing_address',
        'shipping_address',
        'card_last_four',
        'card_brand',
        'card_expiry_month',
        'card_expiry_year',
        'error_details',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'card_last_four',
        'card_brand',
        'card_expiry_month',
        'card_expiry_year',
        'error_details',
        'ip_address',
        'user_agent',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'formatted_amount',
        'status_label',
        'is_successful',
        'is_pending',
        'is_failed',
        'is_refunded',
        'is_cancelled',
        'payment_age',
    ];

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->reference)) {
                $transaction->reference = static::generateReference();
            }

            if (empty($transaction->status)) {
                $transaction->status = 'pending';
            }

            if (empty($transaction->attempts)) {
                $transaction->attempts = 1;
            }

            if (empty($transaction->max_attempts)) {
                $transaction->max_attempts = 3;
            }
        });

        static::updating(function ($transaction) {
            // Update timestamps based on status changes
            if ($transaction->isDirty('status')) {
                $newStatus = $transaction->status;
                $now = now();

                switch ($newStatus) {
                    case 'processing':
                        $transaction->processed_at = $now;
                        break;
                    case 'completed':
                        $transaction->completed_at = $now;
                        break;
                    case 'failed':
                        $transaction->failed_at = $now;
                        break;
                    case 'cancelled':
                        $transaction->cancelled_at = $now;
                        break;
                    case 'refunded':
                        $transaction->refunded_at = $now;
                        break;
                }
            }
        });
    }

    /**
     * Generate a unique transaction reference.
     *
     * @return string
     */
    public static function generateReference(): string
    {
        $prefix = 'TXN';
        $timestamp = time();
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Get the user that owns the transaction.
     *
     * @return MorphTo
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the subscription associated with the transaction.
     *
     * @return BelongsTo
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /**
     * Get the refunds for the transaction.
     *
     * @return HasMany
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Transaction::class, 'parent_transaction_id')
            ->where('transaction_type', 'refund');
    }

    /**
     * Get the parent transaction for refunds.
     *
     * @return BelongsTo
     */
    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'parent_transaction_id');
    }

    /**
     * Scope a query to only include successful transactions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include pending transactions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include failed transactions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include refunded transactions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    /**
     * Scope a query to only include cancelled transactions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to only include transactions for a specific gateway.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $gateway
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope a query to only include transactions for a specific user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, $user)
    {
        return $query->where('user_id', $user->id)
            ->where('user_type', get_class($user));
    }

    /**
     * Scope a query to only include transactions within a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBetweenDates($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include transactions with a minimum amount.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $amount
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMinimumAmount($query, float $amount)
    {
        return $query->where('amount', '>=', $amount);
    }

    /**
     * Scope a query to only include transactions with a maximum amount.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $amount
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMaximumAmount($query, float $amount)
    {
        return $query->where('amount', '<=', $amount);
    }

    /**
     * Scope a query to only include subscription transactions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSubscriptions($query)
    {
        return $query->where('is_subscription', true);
    }

    /**
     * Scope a query to only include one-time payment transactions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOneTimePayments($query)
    {
        return $query->where('is_subscription', false);
    }

    /**
     * Get the formatted amount attribute.
     *
     * @return string
     */
    public function getFormattedAmountAttribute(): string
    {
        $currencySymbols = [
            'ZAR' => 'R',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];

        $symbol = $currencySymbols[$this->currency] ?? $this->currency;
        return $symbol . number_format($this->amount, 2);
    }

    /**
     * Get the status label attribute.
     *
     * @return string
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'partially_refunded' => 'Partially Refunded',
            'authorized' => 'Authorized',
            'voided' => 'Voided',
            'expired' => 'Expired',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get the is successful attribute.
     *
     * @return bool
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return in_array($this->status, ['completed', 'authorized']);
    }

    /**
     * Get the is pending attribute.
     *
     * @return bool
     */
    public function getIsPendingAttribute(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Get the is failed attribute.
     *
     * @return bool
     */
    public function getIsFailedAttribute(): bool
    {
        return in_array($this->status, ['failed', 'expired', 'voided']);
    }

    /**
     * Get the is refunded attribute.
     *
     * @return bool
     */
    public function getIsRefundedAttribute(): bool
    {
        return in_array($this->status, ['refunded', 'partially_refunded']);
    }

    /**
     * Get the is cancelled attribute.
     *
     * @return bool
     */
    public function getIsCancelledAttribute(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get the payment age in minutes.
     *
     * @return int|null
     */
    public function getPaymentAgeAttribute(): ?int
    {
        if (!$this->created_at) {
            return null;
        }

        return $this->created_at->diffInMinutes(now());
    }

    /**
     * Check if the transaction can be retried.
     *
     * @return bool
     */
    public function canRetry(): bool
    {
        if ($this->is_successful || $this->is_refunded || $this->is_cancelled) {
            return false;
        }

        if ($this->attempts >= $this->max_attempts) {
            return false;
        }

        if ($this->retry_at && $this->retry_at->isFuture()) {
            return false;
        }

        if ($this->locked_until && $this->locked_until->isFuture()) {
            return false;
        }

        // Check if error is retryable
        $nonRetryableErrors = [
            'invalid_card',
            'insufficient_funds',
            'card_declined',
            'expired_card',
            'invalid_amount',
            'invalid_currency',
            'unauthorized',
        ];

        if ($this->error_code && in_array($this->error_code, $nonRetryableErrors)) {
            return false;
        }

        return true;
    }

    /**
     * Mark the transaction for retry.
     *
     * @param int $delayMinutes
     * @return $this
     */
    public function markForRetry(int $delayMinutes = 5): self
    {
        $this->retry_at = now()->addMinutes($delayMinutes);
        $this->attempts++;
        $this->save();

        return $this;
    }

    /**
     * Lock the transaction to prevent concurrent processing.
     *
     * @param int $lockMinutes
     * @return bool
     */
    public function lock(int $lockMinutes = 5): bool
    {
        if ($this->locked_until && $this->locked_until->isFuture()) {
            return false;
        }

        $this->locked_until = now()->addMinutes($lockMinutes);
        return $this->save();
    }

    /**
     * Unlock the transaction.
     *
     * @return bool
     */
    public function unlock(): bool
    {
        $this->locked_until = null;
        return $this->save();
    }

    /**
     * Check if the transaction is locked.
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Update transaction status.
     *
     * @param string $status
     * @param array $additionalData
     * @return $this
     */
    public function updateStatus(string $status, array $additionalData = []): self
    {
        $this->status = $status;

        foreach ($additionalData as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->$key = $value;
            }
        }

        $this->save();

        return $this;
    }

    /**
     * Add metadata to the transaction.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function addMetadata(string $key, $value): self
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        $this->save();

        return $this;
    }

    /**
     * Get metadata value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMetadata(string $key, $default = null)
    {
        $metadata = $this->metadata ?? [];
        return $metadata[$key] ?? $default;
    }

    /**
     * Process a refund for this transaction.
     *
     * @param float|null $amount
     * @param string|null $reason
     * @return Transaction|null
     */
    public function refund(?float $amount = null, ?string $reason = null): ?Transaction
    {
        if (!$this->is_successful) {
            return null;
        }

        $refundAmount = $amount ?? $this->amount;

        if ($refundAmount > $this->amount) {
            return null;
        }

        // Create refund transaction
        $refundTransaction = new static([
            'reference' => static::generateReference(),
            'user_id' => $this->user_id,
            'user_type' => $this->user_type,
            'gateway' => $this->gateway,
            'amount' => $refundAmount,
            'currency' => $this->currency,
            'status' => 'pending',
            'description' => "Refund for {$this->reference}",
            'customer_email' => $this->customer_email,
            'customer_name' => $this->customer_name,
            'is_subscription' => false,
            'parent_transaction_id' => $this->id,
            'transaction_type' => 'refund',
            'refund_reason' => $reason,
            'metadata' => [
                'original_transaction' => $this->reference,
                'refund_reason' => $reason,
            ],
        ]);

        $refundTransaction->save();

        return $refundTransaction;
    }

    /**
     * Get the total amount refunded.
     *
     * @return float
     */
    public function totalRefunded(): float
    {
        return $this->refunds()->where('status', 'completed')->sum('amount');
    }

    /**
     * Check if transaction is fully refunded.
     *
     * @return bool
     */
    public function isFullyRefunded(): bool
    {
        return $this->totalRefunded() >= $this->amount;
    }

    /**
     * Check if transaction is partially refunded.
     *
     * @return bool
     */
    public function isPartiallyRefunded(): bool
    {
        $totalRefunded = $this->totalRefunded();
        return $totalRefunded > 0 && $totalRefunded < $this->amount;
    }

    /**
     * Get the remaining refundable amount.
     *
     * @return float
     */
    public function refundableAmount(): float
    {
        return $this->amount - $this->totalRefunded();
    }

    /**
     * Encrypt sensitive attributes.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->encrypted) && !is_null($value)) {
            $value = Crypt::encryptString($value);
        }

        parent::setAttribute($key, $value);
    }

    /**
     * Decrypt sensitive attributes.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (in_array($key, $this->encrypted) && !is_null($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                // If decryption fails, return the encrypted value
                // This might happen if the encryption key has changed
            }
        }

        return $value;
    }

    /**
     * Get the transaction summary.
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'reference' => $this->reference,
            'amount' => $this->formatted_amount,
            'status' => $this->status_label,
            'gateway' => $this->gateway,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'customer_email' => $this->customer_email,
            'customer_name' => $this->customer_name,
            'is_subscription' => $this->is_subscription,
            'subscription_id' => $this->subscription_id,
            'payment_age' => $this->payment_age . ' minutes',
        ];
    }
}
