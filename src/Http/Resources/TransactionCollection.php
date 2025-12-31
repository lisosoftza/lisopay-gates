<?php

namespace Lisosoft\PaymentGateway\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TransactionCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'data' => TransactionResource::collection($this->collection),
            'meta' => [
                'total' => $this->total(),
                'count' => $this->count(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'total_pages' => $this->lastPage(),
                'links' => [
                    'first' => $this->url(1),
                    'last' => $this->url($this->lastPage()),
                    'prev' => $this->previousPageUrl(),
                    'next' => $this->nextPageUrl(),
                ],
            ],
            'summary' => $this->getSummary(),
        ];
    }

    /**
     * Get summary statistics for the transaction collection.
     *
     * @return array
     */
    protected function getSummary()
    {
        $summary = [
            'total_amount' => 0,
            'total_transactions' => $this->total(),
            'status_counts' => [
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'cancelled' => 0,
                'refunded' => 0,
            ],
            'gateway_counts' => [],
            'currency_counts' => [],
            'daily_totals' => [],
        ];

        // Calculate summary statistics
        foreach ($this->collection as $transaction) {
            // Total amount
            if (in_array($transaction->status, ['completed', 'processing'])) {
                $summary['total_amount'] += $transaction->amount;
            }

            // Status counts
            $summary['status_counts'][$transaction->status] =
                ($summary['status_counts'][$transaction->status] ?? 0) + 1;

            // Gateway counts
            $gateway = $transaction->gateway;
            $summary['gateway_counts'][$gateway] =
                ($summary['gateway_counts'][$gateway] ?? 0) + 1;

            // Currency counts
            $currency = $transaction->currency;
            $summary['currency_counts'][$currency] =
                ($summary['currency_counts'][$currency] ?? 0) + 1;

            // Daily totals
            $date = $transaction->created_at->format('Y-m-d');
            if (!isset($summary['daily_totals'][$date])) {
                $summary['daily_totals'][$date] = [
                    'date' => $date,
                    'count' => 0,
                    'amount' => 0,
                ];
            }
            $summary['daily_totals'][$date]['count']++;
            if (in_array($transaction->status, ['completed', 'processing'])) {
                $summary['daily_totals'][$date]['amount'] += $transaction->amount;
            }
        }

        // Convert daily totals to array and sort by date
        $summary['daily_totals'] = array_values($summary['daily_totals']);
        usort($summary['daily_totals'], function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        // Format total amount
        $summary['total_amount_formatted'] = number_format($summary['total_amount'], 2);

        // Calculate success rate
        $completed = $summary['status_counts']['completed'] ?? 0;
        $total = array_sum($summary['status_counts']);
        $summary['success_rate'] = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        // Calculate average transaction value
        $summary['average_transaction_value'] = $completed > 0
            ? round($summary['total_amount'] / $completed, 2)
            : 0;

        return $summary;
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
                'timezone' => config('app.timezone', 'UTC'),
                'filters' => $this->getAppliedFilters($request),
                'sorting' => $this->getAppliedSorting($request),
            ],
        ];
    }

    /**
     * Get the applied filters from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function getAppliedFilters($request)
    {
        $filters = [];

        // Status filter
        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }

        // Gateway filter
        if ($request->has('gateway')) {
            $filters['gateway'] = $request->input('gateway');
        }

        // Date range filter
        if ($request->has('start_date')) {
            $filters['start_date'] = $request->input('start_date');
        }
        if ($request->has('end_date')) {
            $filters['end_date'] = $request->input('end_date');
        }

        // Amount range filter
        if ($request->has('min_amount')) {
            $filters['min_amount'] = $request->input('min_amount');
        }
        if ($request->has('max_amount')) {
            $filters['max_amount'] = $request->input('max_amount');
        }

        // Customer filter
        if ($request->has('customer_email')) {
            $filters['customer_email'] = $request->input('customer_email');
        }

        return $filters;
    }

    /**
     * Get the applied sorting from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function getAppliedSorting($request)
    {
        $sorting = [
            'field' => 'created_at',
            'direction' => 'desc',
        ];

        if ($request->has('sort_by')) {
            $sorting['field'] = $request->input('sort_by');
        }

        if ($request->has('sort_order')) {
            $sorting['direction'] = $request->input('sort_order');
        }

        return $sorting;
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
        $response->header('X-Total-Count', $this->total());
        $response->header('X-Page-Count', $this->lastPage());
        $response->header('X-Current-Page', $this->currentPage());
        $response->header('X-Per-Page', $this->perPage());

        // Add cache headers
        $response->header('Cache-Control', 'private, max-age=300'); // Cache for 5 minutes

        // Add pagination links in headers
        $links = [];
        if ($this->previousPageUrl()) {
            $links[] = '<' . $this->previousPageUrl() . '>; rel="prev"';
        }
        if ($this->nextPageUrl()) {
            $links[] = '<' . $this->nextPageUrl() . '>; rel="next"';
        }
        if ($this->url(1)) {
            $links[] = '<' . $this->url(1) . '>; rel="first"';
        }
        if ($this->url($this->lastPage())) {
            $links[] = '<' . $this->url($this->lastPage()) . '>; rel="last"';
        }

        if (!empty($links)) {
            $response->header('Link', implode(', ', $links));
        }
    }
}
