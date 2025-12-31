<?php

namespace Lisosoft\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lisosoft\PaymentGateway\Models\Transaction;
use Carbon\Carbon;

class ListTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment-gateway:transactions
                            {--status= : Filter by status (pending, completed, failed, refunded)}
                            {--gateway= : Filter by gateway}
                            {--customer= : Filter by customer email or name}
                            {--reference= : Filter by transaction reference}
                            {--start-date= : Filter by start date (YYYY-MM-DD)}
                            {--end-date= : Filter by end date (YYYY-MM-DD)}
                            {--limit=50 : Number of transactions to display}
                            {--page=1 : Page number for pagination}
                            {--sort-by=created_at : Field to sort by}
                            {--sort-order=desc : Sort order (asc or desc)}
                            {--export : Export transactions to CSV}
                            {--summary : Show summary statistics}
                            {--verbose : Show detailed transaction information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List and manage payment transactions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('📊 Listing Payment Transactions...');
        $this->newLine();

        $query = Transaction::query();

        // Apply filters
        $this->applyFilters($query);

        // Get total count before pagination
        $total = $query->count();

        if ($total === 0) {
            $this->warn('No transactions found matching the specified criteria.');
            return Command::SUCCESS;
        }

        // Apply sorting
        $sortBy = $this->option('sort-by');
        $sortOrder = $this->option('sort-order');
        $query->orderBy($sortBy, $sortOrder);

        // Apply pagination
        $limit = (int) $this->option('limit');
        $page = (int) $this->option('page');
        $offset = ($page - 1) * $limit;
        $transactions = $query->skip($offset)->take($limit)->get();

        // Show summary if requested
        if ($this->option('summary')) {
            $this->displaySummary($query);
            $this->newLine();
        }

        // Export if requested
        if ($this->option('export')) {
            return $this->exportTransactions($transactions);
        }

        // Display transactions
        $this->displayTransactions($transactions, $total, $page, $limit);

        return Command::SUCCESS;
    }

    /**
     * Apply filters to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    protected function applyFilters(\Illuminate\Database\Eloquent\Builder $query): void
    {
        // Status filter
        if ($status = $this->option('status')) {
            $query->where('status', $status);
            $this->line("Filter: Status = {$status}");
        }

        // Gateway filter
        if ($gateway = $this->option('gateway')) {
            $query->where('gateway', $gateway);
            $this->line("Filter: Gateway = {$gateway}");
        }

        // Customer filter
        if ($customer = $this->option('customer')) {
            $query->where(function ($q) use ($customer) {
                $q->where('customer_email', 'like', "%{$customer}%")
                  ->orWhere('customer_name', 'like', "%{$customer}%");
            });
            $this->line("Filter: Customer contains '{$customer}'");
        }

        // Reference filter
        if ($reference = $this->option('reference')) {
            $query->where('reference', 'like', "%{$reference}%");
            $this->line("Filter: Reference contains '{$reference}'");
        }

        // Date range filter
        if ($startDate = $this->option('start-date')) {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
            $this->line("Filter: Start Date >= {$startDate}");
        }

        if ($endDate = $this->option('end-date')) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
            $this->line("Filter: End Date <= {$endDate}");
        }

        if ($this->option('verbose')) {
            $this->newLine();
        }
    }

    /**
     * Display transaction summary.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    protected function displaySummary(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $cloneQuery = clone $query;

        $summary = $cloneQuery->select(
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as total_revenue'),
            DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
            DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
            DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'),
            DB::raw('SUM(CASE WHEN status = "refunded" THEN 1 ELSE 0 END) as refunded'),
            DB::raw('AVG(CASE WHEN status = "completed" THEN amount ELSE NULL END) as avg_amount')
        )->first();

        $this->info('📈 Transaction Summary:');
        $this->line("Total Transactions: {$summary->total}");
        $this->line("Total Revenue: " . number_format($summary->total_revenue ?? 0, 2));
        $this->line("Completed: {$summary->completed}");
        $this->line("Pending: {$summary->pending}");
        $this->line("Failed: {$summary->failed}");
        $this->line("Refunded: {$summary->refunded}");
        $this->line("Average Amount: " . number_format($summary->avg_amount ?? 0, 2));

        // Gateway breakdown
        $gatewaySummary = $cloneQuery->select('gateway', DB::raw('COUNT(*) as count'))
            ->groupBy('gateway')
            ->orderBy('count', 'desc')
            ->get();

        if ($gatewaySummary->isNotEmpty()) {
            $this->newLine();
            $this->info('🌐 Gateway Breakdown:');
            foreach ($gatewaySummary as $gateway) {
                $percentage = $summary->total > 0 ? round(($gateway->count / $summary->total) * 100, 1) : 0;
                $this->line("  {$gateway->gateway}: {$gateway->count} ({$percentage}%)");
            }
        }
    }

    /**
     * Display transactions in a table.
     *
     * @param \Illuminate\Database\Eloquent\Collection $transactions
     * @param int $total
     * @param int $page
     * @param int $limit
     * @return void
     */
    protected function displayTransactions($transactions, int $total, int $page, int $limit): void
    {
        $verbose = $this->option('verbose');
        $totalPages = ceil($total / $limit);

        $this->info("📋 Transactions (Page {$page}/{$totalPages}, Total: {$total}):");
        $this->newLine();

        if ($verbose) {
            $this->displayVerboseTransactions($transactions);
        } else {
            $this->displayCompactTransactions($transactions);
        }

        $this->newLine();
        $this->line("Page {$page} of {$totalPages} | Showing {$transactions->count()} of {$total} transactions");

        if ($page < $totalPages) {
            $this->line("Use --page=" . ($page + 1) . " to view next page");
        }

        if ($page > 1) {
            $this->line("Use --page=" . ($page - 1) . " to view previous page");
        }
    }

    /**
     * Display transactions in verbose format.
     *
     * @param \Illuminate\Database\Eloquent\Collection $transactions
     * @return void
     */
    protected function displayVerboseTransactions($transactions): void
    {
        foreach ($transactions as $transaction) {
            $this->info("──────────────────────────────────────────────────────");
            $this->line("Reference: {$transaction->reference}");
            $this->line("Gateway: {$transaction->gateway}");
            $this->line("Amount: " . number_format($transaction->amount, 2) . " {$transaction->currency}");
            $this->line("Status: " . $this->getStatusIcon($transaction->status) . " {$transaction->status}");
            $this->line("Description: {$transaction->description}");
            $this->line("Customer: {$transaction->customer_name} <{$transaction->customer_email}>");

            if ($transaction->customer_phone) {
                $this->line("Phone: {$transaction->customer_phone}");
            }

            $this->line("Created: {$transaction->created_at->format('Y-m-d H:i:s')}");

            if ($transaction->completed_at) {
                $this->line("Completed: {$transaction->completed_at->format('Y-m-d H:i:s')}");
            }

            if ($transaction->failed_at) {
                $this->line("Failed: {$transaction->failed_at->format('Y-m-d H:i:s')}");
            }

            if ($transaction->refunded_at) {
                $this->line("Refunded: {$transaction->refunded_at->format('Y-m-d H:i:s')}");
                $this->line("Refund Amount: " . number_format($transaction->refund_amount, 2));
            }

            if ($transaction->error_message) {
                $this->line("Error: {$transaction->error_message}");
            }

            if ($transaction->metadata) {
                $this->line("Metadata: " . json_encode($transaction->metadata));
            }

            $this->info("──────────────────────────────────────────────────────");
            $this->newLine();
        }
    }

    /**
     * Display transactions in compact table format.
     *
     * @param \Illuminate\Database\Eloquent\Collection $transactions
     * @return void
     */
    protected function displayCompactTransactions($transactions): void
    {
        $headers = ['Reference', 'Gateway', 'Amount', 'Status', 'Customer', 'Created'];
        $rows = [];

        foreach ($transactions as $transaction) {
            $rows[] = [
                $transaction->reference,
                $transaction->gateway,
                number_format($transaction->amount, 2) . ' ' . $transaction->currency,
                $this->getStatusIcon($transaction->status) . ' ' . $transaction->status,
                $transaction->customer_email,
                $transaction->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Export transactions to CSV.
     *
     * @param \Illuminate\Database\Eloquent\Collection $transactions
     * @return int
     */
    protected function exportTransactions($transactions): int
    {
        $filename = 'transactions_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = storage_path('app/exports/' . $filename);

        // Ensure directory exists
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $headers = [
            'Reference',
            'Gateway',
            'Amount',
            'Currency',
            'Status',
            'Description',
            'Customer Email',
            'Customer Name',
            'Customer Phone',
            'Created At',
            'Completed At',
            'Failed At',
            'Refunded At',
            'Refund Amount',
            'Error Message',
            'Payment Method',
            'IP Address',
        ];

        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);

        foreach ($transactions as $transaction) {
            $row = [
                $transaction->reference,
                $transaction->gateway,
                $transaction->amount,
                $transaction->currency,
                $transaction->status,
                $transaction->description,
                $transaction->customer_email,
                $transaction->customer_name,
                $transaction->customer_phone ?? '',
                $transaction->created_at->toDateTimeString(),
                $transaction->completed_at ? $transaction->completed_at->toDateTimeString() : '',
                $transaction->failed_at ? $transaction->failed_at->toDateTimeString() : '',
                $transaction->refunded_at ? $transaction->refunded_at->toDateTimeString() : '',
                $transaction->refund_amount,
                $transaction->error_message ?? '',
                $transaction->payment_method ?? '',
                $transaction->ip_address ?? '',
            ];

            fputcsv($file, $row);
        }

        fclose($file);

        $this->info("✅ Transactions exported successfully to: {$filepath}");
        $this->line("Total records exported: {$transactions->count()}");

        return Command::SUCCESS;
    }

    /**
     * Get status icon for display.
     *
     * @param string $status
     * @return string
     */
    protected function getStatusIcon(string $status): string
    {
        $icons = [
            'pending' => '⏳',
            'completed' => '✅',
            'failed' => '❌',
            'refunded' => '↩️',
            'cancelled' => '🚫',
            'expired' => '⏰',
        ];

        return $icons[$status] ?? '❓';
    }
}
