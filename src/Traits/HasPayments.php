<?php

namespace Lisosoft\PaymentGateway\Traits;

use Lisosoft\PaymentGateway\Models\Transaction;
use Lisosoft\PaymentGateway\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;

trait HasPayments
{
    /**
     * Get all transactions for the model.
     *
     * @return MorphMany
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'user')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get successful transactions for the model.
     *
     * @return MorphMany
     */
    public function successfulTransactions(): MorphMany
    {
        return $this->transactions()->successful();
    }

    /**
     * Get pending transactions for the model.
     *
     * @return MorphMany
     */
    public function pendingTransactions(): MorphMany
    {
        return $this->transactions()->pending();
    }

    /**
     * Get failed transactions for the model.
     *
     * @return MorphMany
     */
    public function failedTransactions(): MorphMany
    {
        return $this->transactions()->failed();
    }

    /**
     * Get refunded transactions for the model.
     *
     * @return MorphMany
     */
    public function refundedTransactions(): MorphMany
    {
        return $this->transactions()->refunded();
    }

    /**
     * Get subscriptions for the model.
     *
     * @return MorphMany
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'user')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get active subscriptions for the model.
     *
     * @return MorphMany
     */
    public function activeSubscriptions(): MorphMany
    {
        return $this->subscriptions()->active();
    }

    /**
     * Get cancelled subscriptions for the model.
     *
     * @return MorphMany
     */
    public function cancelledSubscriptions(): MorphMany
    {
        return $this->subscriptions()->cancelled();
    }

    /**
     * Get expired subscriptions for the model.
     *
     * @return MorphMany
     */
    public function expiredSubscriptions(): MorphMany
    {
        return $this->subscriptions()->expired();
    }

    /**
     * Get the latest transaction for the model.
     *
     * @return MorphOne
     */
    public function latestTransaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'user')
            ->latestOfMany();
    }

    /**
     * Get the latest successful transaction for the model.
     *
     * @return MorphOne|null
     */
    public function latestSuccessfulTransaction(): ?Transaction
    {
        return $this->successfulTransactions()->latest()->first();
    }

    /**
     * Get the total amount spent by the model.
     *
     * @param string|null $currency
     * @return float
     */
    public function totalSpent(?string $currency = null): float
    {
        $query = $this->successfulTransactions();

        if ($currency) {
            $query->where('currency', $currency);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get the total amount refunded to the model.
     *
     * @param string|null $currency
     * @return float
     */
    public function totalRefunded(?string $currency = null): float
    {
        $query = $this->refundedTransactions();

        if ($currency) {
            $query->where('currency', $currency);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get the net amount (spent - refunded) for the model.
     *
     * @param string|null $currency
     * @return float
     */
    public function netAmount(?string $currency = null): float
    {
        return $this->totalSpent($currency) - $this->totalRefunded($currency);
    }

    /**
     * Get the average transaction amount for the model.
     *
     * @param string|null $currency
     * @return float
     */
    public function averageTransactionAmount(?string $currency = null): float
    {
        $query = $this->successfulTransactions();

        if ($currency) {
            $query->where('currency', $currency);
        }

        $count = $query->count();
        if ($count === 0) {
            return 0.0;
        }

        return $this->totalSpent($currency) / $count;
    }

    /**
     * Get the transaction count for the model.
     *
     * @param string|null $status
     * @param string|null $currency
     * @return int
     */
    public function transactionCount(?string $status = null, ?string $currency = null): int
    {
        $query = $this->transactions();

        if ($status) {
            $query->where('status', $status);
        }

        if ($currency) {
            $query->where('currency', $currency);
        }

        return $query->count();
    }

    /**
     * Get the subscription count for the model.
     *
     * @param string|null $status
     * @return int
     */
    public function subscriptionCount(?string $status = null): int
    {
        $query = $this->subscriptions();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    /**
     * Check if the model has any successful transactions.
     *
     * @return bool
     */
    public function hasSuccessfulTransactions(): bool
    {
        return $this->successfulTransactions()->exists();
    }

    /**
     * Check if the model has any active subscriptions.
     *
     * @return bool
     */
    public function hasActiveSubscriptions(): bool
    {
        return $this->activeSubscriptions()->exists();
    }

    /**
     * Check if the model has a specific subscription plan.
     *
     * @param string $planId
     * @return bool
     */
    public function hasSubscriptionPlan(string $planId): bool
    {
        return $this->activeSubscriptions()
            ->where('plan_id', $planId)
            ->exists();
    }

    /**
     * Get the preferred payment gateway for the model.
     *
     * @return string|null
     */
    public function preferredPaymentGateway(): ?string
    {
        $mostUsedGateway = $this->successfulTransactions()
            ->select('gateway', DB::raw('COUNT(*) as count'))
            ->groupBy('gateway')
            ->orderBy('count', 'desc')
            ->first();

        return $mostUsedGateway?->gateway;
    }

    /**
     * Get the preferred currency for the model.
     *
     * @return string|null
     */
    public function preferredCurrency(): ?string
    {
        $mostUsedCurrency = $this->successfulTransactions()
            ->select('currency', DB::raw('COUNT(*) as count'))
            ->groupBy('currency')
            ->orderBy('count', 'desc')
            ->first();

        return $mostUsedCurrency?->currency;
    }

    /**
     * Get the payment statistics for the model.
     *
     * @return array
     */
    public function paymentStatistics(): array
    {
        $totalTransactions = $this->transactionCount();
        $successfulTransactions = $this->transactionCount('completed');
        $failedTransactions = $this->transactionCount('failed');
        $pendingTransactions = $this->transactionCount('pending');

        return [
            'total_transactions' => $totalTransactions,
            'successful_transactions' => $successfulTransactions,
            'failed_transactions' => $failedTransactions,
            'pending_transactions' => $pendingTransactions,
            'success_rate' => $totalTransactions > 0 ? round(($successfulTransactions / $totalTransactions) * 100, 2) : 0,
            'total_spent' => $this->totalSpent(),
            'total_refunded' => $this->totalRefunded(),
            'net_amount' => $this->netAmount(),
            'average_transaction_amount' => $this->averageTransactionAmount(),
            'preferred_gateway' => $this->preferredPaymentGateway(),
            'preferred_currency' => $this->preferredCurrency(),
            'active_subscriptions' => $this->subscriptionCount('active'),
            'cancelled_subscriptions' => $this->subscriptionCount('cancelled'),
            'expired_subscriptions' => $this->subscriptionCount('expired'),
        ];
    }

    /**
     * Get the payment timeline for the model.
     *
     * @param int $months
     * @return array
     */
    public function paymentTimeline(int $months = 12): array
    {
        $endDate = now();
        $startDate = now()->subMonths($months);

        $transactions = $this->successfulTransactions()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $timeline = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->format('Y-m-d');
            $transaction = $transactions->firstWhere('date', $dateString);

            $timeline[] = [
                'date' => $dateString,
                'count' => $transaction ? (int) $transaction->count : 0,
                'amount' => $transaction ? (float) $transaction->total_amount : 0.0,
            ];

            $currentDate->addDay();
        }

        return $timeline;
    }

    /**
     * Get the monthly revenue for the model.
     *
     * @param int $months
     * @return array
     */
    public function monthlyRevenue(int $months = 12): array
    {
        $endDate = now();
        $startDate = now()->subMonths($months);

        $revenue = $this->successfulTransactions()
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $monthlyRevenue = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $year = $currentDate->year;
            $month = $currentDate->month;
            $monthKey = $currentDate->format('Y-m');

            $monthData = $revenue->first(function ($item) use ($year, $month) {
                return $item->year == $year && $item->month == $month;
            });

            $monthlyRevenue[] = [
                'month' => $monthKey,
                'revenue' => $monthData ? (float) $monthData->total_amount : 0.0,
                'transaction_count' => $this->successfulTransactions()
                    ->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->count(),
            ];

            $currentDate->addMonth();
        }

        return $monthlyRevenue;
    }

    /**
     * Create a new transaction for the model.
     *
     * @param array $data
     * @return Transaction
     */
    public function createTransaction(array $data): Transaction
    {
        $data['user_id'] = $this->id;
        $data['user_type'] = get_class($this);

        return Transaction::create($data);
    }

    /**
     * Create a new subscription for the model.
     *
     * @param array $data
     * @return Subscription
     */
    public function createSubscription(array $data): Subscription
    {
        $data['user_id'] = $this->id;
        $data['user_type'] = get_class($this);

        return Subscription::create($data);
    }

    /**
     * Check if the model can make a payment.
     *
     * @param float $amount
     * @param string $currency
     * @return bool
     */
    public function canMakePayment(float $amount, string $currency = 'ZAR'): bool
    {
        // Check if user is not blocked
        if (method_exists($this, 'isPaymentBlocked') && $this->isPaymentBlocked()) {
            return false;
        }

        // Check if user has exceeded payment limits
        $dailyLimit = config('payment-gateway.security.daily_limit', 100000);
        $dailySpent = $this->successfulTransactions()
            ->where('currency', $currency)
            ->whereDate('created_at', today())
            ->sum('amount');

        if (($dailySpent + $amount) > $dailyLimit) {
            return false;
        }

        // Check for suspicious activity
        $recentFailures = $this->failedTransactions()
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($recentFailures > config('payment-gateway.security.max_failures', 5)) {
            return false;
        }

        return true;
    }

    /**
     * Get the payment methods used by the model.
     *
     * @return array
     */
    public function paymentMethods(): array
    {
        return $this->successfulTransactions()
            ->select('payment_method')
            ->distinct()
            ->pluck('payment_method')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Get the currencies used by the model.
     *
     * @return array
     */
    public function currenciesUsed(): array
    {
        return $this->successfulTransactions()
            ->select('currency')
            ->distinct()
            ->pluck('currency')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Get the gateways used by the model.
     *
     * @return array
     */
    public function gatewaysUsed(): array
    {
        return $this->successfulTransactions()
            ->select('gateway')
            ->distinct()
            ->pluck('gateway')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Scope a query to only include models with successful transactions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasSuccessfulTransactions($query)
    {
        return $query->whereHas('transactions', function ($q) {
            $q->successful();
        });
    }

    /**
     * Scope a query to only include models with active subscriptions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasActiveSubscriptions($query)
    {
        return $query->whereHas('subscriptions', function ($q) {
            $q->active();
        });
    }

    /**
     * Scope a query to only include models with a specific subscription plan.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $planId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasSubscriptionPlan($query, string $planId)
    {
        return $query->whereHas('subscriptions', function ($q) use ($planId) {
            $q->active()->where('plan_id', $planId);
        });
    }

    /**
     * Scope a query to only include models with transactions above a certain amount.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $amount
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasTransactionsAbove($query, float $amount)
    {
        return $query->whereHas('transactions', function ($q) use ($amount) {
            $q->successful()->where('amount', '>=', $amount);
        });
    }

    /**
     * Scope a query to only include models with transactions in a specific currency.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $currency
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasTransactionsInCurrency($query, string $currency)
    {
        return $query->whereHas('transactions', function ($q) use ($currency) {
            $q->where('currency', $currency);
        });
    }
}
