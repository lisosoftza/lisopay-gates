<?php

namespace Lisosoft\PaymentGateway\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lisosoft\PaymentGateway\Facades\Payment;
use Lisosoft\PaymentGateway\Models\Transaction;
use Carbon\Carbon;

class PaymentGatewayController extends Controller
{
    /**
     * Display payment dashboard
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            // Check admin permissions
            if (!$this->hasAdminPermissions()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Insufficient permissions",
                    ],
                    403,
                );
            }

            // Get date range
            $startDate = $request->input(
                "start_date",
                Carbon::now()->subDays(30)->toDateString(),
            );
            $endDate = $request->input(
                "end_date",
                Carbon::now()->toDateString(),
            );

            // Calculate statistics
            $statistics = $this->calculateDashboardStatistics(
                $startDate,
                $endDate,
            );

            // Get recent transactions
            $recentTransactions = Transaction::with("user")
                ->whereBetween("created_at", [
                    $startDate . " 00:00:00",
                    $endDate . " 23:59:59",
                ])
                ->orderBy("created_at", "desc")
                ->limit(10)
                ->get();

            // Get gateway statistics
            $gatewayStats = $this->getGatewayStatistics($startDate, $endDate);

            return response()->json([
                "success" => true,
                "message" => "Dashboard data retrieved successfully",
                "data" => [
                    "statistics" => $statistics,
                    "recent_transactions" => $recentTransactions,
                    "gateway_statistics" => $gatewayStats,
                    "date_range" => [
                        "start_date" => $startDate,
                        "end_date" => $endDate,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to load dashboard",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Display all transactions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function transactions(Request $request): JsonResponse
    {
        try {
            // Check admin permissions
            if (!$this->hasAdminPermissions()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Insufficient permissions",
                    ],
                    403,
                );
            }

            $query = Transaction::with("user");

            // Apply filters
            if ($request->has("status")) {
                $query->where("status", $request->input("status"));
            }

            if ($request->has("gateway")) {
                $query->where("gateway", $request->input("gateway"));
            }

            if ($request->has("customer_email")) {
                $query->where(
                    "customer_email",
                    "like",
                    "%" . $request->input("customer_email") . "%",
                );
            }

            if ($request->has("reference")) {
                $query->where(
                    "reference",
                    "like",
                    "%" . $request->input("reference") . "%",
                );
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

            // Search
            if ($request->has("search")) {
                $search = $request->input("search");
                $query->where(function ($q) use ($search) {
                    $q->where("reference", "like", "%" . $search . "%")
                        ->orWhere("customer_email", "like", "%" . $search . "%")
                        ->orWhere("customer_name", "like", "%" . $search . "%")
                        ->orWhere("description", "like", "%" . $search . "%");
                });
            }

            // Sorting
            $sortBy = $request->input("sort_by", "created_at");
            $sortOrder = $request->input("sort_order", "desc");
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->input("per_page", 50);
            $page = $request->input("page", 1);
            $transactions = $query->paginate($perPage, ["*"], "page", $page);

            // Get summary statistics
            $summary = $this->getTransactionsSummary($query);

            return response()->json([
                "success" => true,
                "message" => "Transactions retrieved successfully",
                "data" => [
                    "transactions" => $transactions->items(),
                    "summary" => $summary,
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
                    "message" => "Failed to get transactions",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Display transaction details
     *
     * @param Request $request
     * @param int $transactionId
     * @return JsonResponse
     */
    public function showTransaction(
        Request $request,
        int $transactionId,
    ): JsonResponse {
        try {
            // Check admin permissions
            if (!$this->hasAdminPermissions()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Insufficient permissions",
                    ],
                    403,
                );
            }

            $transaction = Transaction::with([
                "user",
                "parentTransaction",
                "childTransactions",
            ])->find($transactionId);

            if (!$transaction) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Transaction not found",
                    ],
                    404,
                );
            }

            // Get gateway details if available
            $gatewayDetails = null;
            try {
                $gatewayInstance = Payment::gateway($transaction->gateway);
                $gatewayDetails = $gatewayInstance->getTransactionHistory([
                    "transaction_id" => $transaction->gateway_transaction_id,
                ]);
            } catch (\Exception $e) {
                // Gateway details not available
            }

            return response()->json([
                "success" => true,
                "message" => "Transaction details retrieved successfully",
                "data" => [
                    "transaction" => $transaction,
                    "gateway_details" => $gatewayDetails,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to get transaction details",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Display gateway configurations
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function gateways(Request $request): JsonResponse
    {
        try {
            // Check admin permissions
            if (!$this->hasAdminPermissions()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Insufficient permissions",
                    ],
                    403,
                );
            }

            $gateways = Payment::getAvailableGateways();
            $gatewayConfigs = config("payment-gateway.gateways", []);

            // Prepare gateway details
            $gatewayDetails = [];
            foreach ($gateways as $name => $gateway) {
                $gatewayDetails[$name] = [
                    "name" => $gateway["display_name"],
                    "enabled" => $gatewayConfigs[$name]["enabled"] ?? false,
                    "test_mode" => $gatewayConfigs[$name]["test_mode"] ?? true,
                    "config" => $gatewayConfigs[$name] ?? [],
                    "statistics" => $this->getGatewayTransactionStats($name),
                ];
            }

            return response()->json([
                "success" => true,
                "message" => "Gateway configurations retrieved successfully",
                "data" => [
                    "gateways" => $gatewayDetails,
                    "total_gateways" => count($gateways),
                    "enabled_gateways" => count(
                        array_filter($gatewayDetails, function ($gateway) {
                            return $gateway["enabled"];
                        }),
                    ),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to get gateway configurations",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Update gateway configuration
     *
     * @param Request $request
     * @param string $gateway
     * @return JsonResponse
     */
    public function updateGateway(
        Request $request,
        string $gateway,
    ): JsonResponse {
        try {
            // Check admin permissions
            if (!$this->hasAdminPermissions()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Insufficient permissions",
                    ],
                    403,
                );
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                "enabled" => "nullable|boolean",
                "test_mode" => "nullable|boolean",
                "config" => "nullable|array",
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

            // Get current configuration
            $gatewayConfigs = config("payment-gateway.gateways", []);

            if (!isset($gatewayConfigs[$gateway])) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Gateway '{$gateway}' not found in configuration",
                    ],
                    404,
                );
            }

            // Update configuration
            $currentConfig = $gatewayConfigs[$gateway];

            if ($request->has("enabled")) {
                $currentConfig["enabled"] = $request->boolean("enabled");
            }

            if ($request->has("test_mode")) {
                $currentConfig["test_mode"] = $request->boolean("test_mode");
            }

            if ($request->has("config")) {
                $currentConfig = array_merge(
                    $currentConfig,
                    $request->input("config"),
                );
            }

            // In a real implementation, you would save this to database or config file
            // For now, we'll just return the updated configuration
            $gatewayConfigs[$gateway] = $currentConfig;

            return response()->json([
                "success" => true,
                "message" => "Gateway configuration updated successfully",
                "data" => [
                    "gateway" => $gateway,
                    "config" => $currentConfig,
                    "enabled" => $currentConfig["enabled"] ?? false,
                    "test_mode" => $currentConfig["test_mode"] ?? true,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to update gateway configuration",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Display analytics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            // Check admin permissions
            if (!$this->hasAdminPermissions()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Insufficient permissions",
                    ],
                    403,
                );
            }

            // Get date range
            $startDate = $request->input(
                "start_date",
                Carbon::now()->subDays(30)->toDateString(),
            );
            $endDate = $request->input(
                "end_date",
                Carbon::now()->toDateString(),
            );

            // Get analytics data
            $analyticsData = $this->getAnalyticsData($startDate, $endDate);

            return response()->json([
                "success" => true,
                "message" => "Analytics data retrieved successfully",
                "data" => array_merge($analyticsData, [
                    "date_range" => [
                        "start_date" => $startDate,
                        "end_date" => $endDate,
                    ],
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to get analytics",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Export transactions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function export(Request $request): JsonResponse
    {
        try {
            // Check admin permissions
            if (!$this->hasAdminPermissions()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Insufficient permissions",
                    ],
                    403,
                );
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                "format" => "required|string|in:csv,json,xlsx",
                "start_date" => "nullable|date",
                "end_date" => "nullable|date|after_or_equal:start_date",
                "status" => "nullable|string",
                "gateway" => "nullable|string",
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

            $query = Transaction::query();

            // Apply filters
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

            if ($request->has("status")) {
                $query->where("status", $request->input("status"));
            }

            if ($request->has("gateway")) {
                $query->where("gateway", $request->input("gateway"));
            }

            // Get transactions
            $transactions = $query->orderBy("created_at", "desc")->get();

            // Prepare export data
            $exportData = $this->prepareExportData(
                $transactions,
                $request->input("format"),
            );

            return response()->json([
                "success" => true,
                "message" => "Export data prepared successfully",
                "data" => [
                    "format" => $request->input("format"),
                    "total_records" => $transactions->count(),
                    "export_data" => $exportData,
                    "download_url" => $this->generateExportDownloadUrl(
                        $exportData,
                        $request->input("format"),
                    ),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to export transactions",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Display subscriptions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function subscriptions(Request $request): JsonResponse
    {
        try {
            // Check admin permissions
            if (!$this->hasAdminPermissions()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Insufficient permissions",
                    ],
                    403,
                );
            }

            $query = Transaction::where("is_subscription", true);

            // Apply filters
            if ($request->has("status")) {
                $query->where("status", $request->input("status"));
            }

            if ($request->has("gateway")) {
                $query->where("gateway", $request->input("gateway"));
            }

            if ($request->has("customer_email")) {
                $query->where(
                    "customer_email",
                    "like",
                    "%" . $request->input("customer_email") . "%",
                );
            }

            // Search
            if ($request->has("search")) {
                $search = $request->input("search");
                $query->where(function ($q) use ($search) {
                    $q->where("reference", "like", "%" . $search . "%")
                        ->orWhere("customer_email", "like", "%" . $search . "%")
                        ->orWhere("customer_name", "like", "%" . $search . "%")
                        ->orWhere("description", "like", "%" . $search . "%");
                });
            }

            // Sorting
            $sortBy = $request->input("sort_by", "next_billing_date");
            $sortOrder = $request->input("sort_order", "asc");
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->input("per_page", 50);
            $page = $request->input("page", 1);
            $subscriptions = $query->paginate($perPage, ["*"], "page", $page);

            // Get subscription statistics
            $subscriptionStats = $this->getSubscriptionStatistics();

            return response()->json([
                "success" => true,
                "message" => "Subscriptions retrieved successfully",
                "data" => [
                    "subscriptions" => $subscriptions->items(),
                    "statistics" => $subscriptionStats,
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
     * Process manual payment
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function manualPayment(Request $request): JsonResponse
    {
        try {
            // Check admin permissions
            if (!$this->hasAdminPermissions()) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Insufficient permissions",
                    ],
                    403,
                );
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                "customer_email" => "required|email",
                "customer_name" => "required|string|max:255",
                "amount" => "required|numeric|min:0.01",
                "currency" => "required|string|size:3",
                "description" => "required|string|max:255",
                "payment_method" =>
                    "required|string|in:manual,cash,bank_transfer,other",
                "reference" => "nullable|string|max:255",
                "notes" => "nullable|string",
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

            // Create manual transaction
            $transaction = Transaction::create([
                "reference" => $request->input(
                    "reference",
                    Transaction::generateReference(),
                ),
                "gateway" => "manual",
                "amount" => $request->input("amount"),
                "currency" => $request->input("currency"),
                "status" => "completed",
                "description" => $request->input("description"),
                "customer_email" => $request->input("customer_email"),
                "customer_name" => $request->input("customer_name"),
                "payment_method" => $request->input("payment_method"),
                "transaction_type" => "manual_payment",
                "completed_at" => now(),
                "notes" => $request->input("notes"),
                "metadata" => $request->input("metadata", []),
                "processed_at" => now(),
            ]);

            return response()->json([
                "success" => true,
                "message" => "Manual payment recorded successfully",
                "data" => [
                    "transaction" => $transaction->toArray(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to process manual payment",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Check if user has admin permissions
     *
     * @return bool
     */
    private function hasAdminPermissions(): bool
    {
        // In a real implementation, check user roles/permissions
        // For now, assume authenticated users with admin role have permissions
        $user = Auth::user();
        return $user &&
            ($user->hasRole("admin") || $user->can("admin-payments"));
    }

    /**
     * Calculate dashboard statistics
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function calculateDashboardStatistics(
        string $startDate,
        string $endDate,
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Total transactions
        $totalTransactions = Transaction::whereBetween("created_at", [
            $start,
            $end,
        ])->count();

        // Total revenue
        $totalRevenue = Transaction::whereBetween("created_at", [$start, $end])
            ->where("status", "completed")
            ->sum("amount");

        // Successful transactions
        $successfulTransactions = Transaction::whereBetween("created_at", [
            $start,
            $end,
        ])
            ->where("status", "completed")
            ->count();

        // Failed transactions
        $failedTransactions = Transaction::whereBetween("created_at", [
            $start,
            $end,
        ])
            ->where("status", "failed")
            ->count();

        // Pending transactions
        $pendingTransactions = Transaction::whereBetween("created_at", [
            $start,
            $end,
        ])
            ->where("status", "pending")
            ->count();

        // Average transaction value
        $averageTransactionValue =
            $successfulTransactions > 0
                ? $totalRevenue / $successfulTransactions
                : 0;

        // Conversion rate
        $conversionRate =
            $totalTransactions > 0
                ? ($successfulTransactions / $totalTransactions) * 100
                : 0;

        return [
            "total_transactions" => $totalTransactions,
            "total_revenue" => (float) $totalRevenue,
            "successful_transactions" => $successfulTransactions,
            "failed_transactions" => $failedTransactions,
            "pending_transactions" => $pendingTransactions,
            "average_transaction_value" => (float) $averageTransactionValue,
            "conversion_rate" => (float) $conversionRate,
            "date_range" => [
                "start_date" => $startDate,
                "end_date" => $endDate,
            ],
        ];
    }

    /**
     * Get gateway statistics
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function getGatewayStatistics(
        string $startDate,
        string $endDate,
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $gatewayStats = Transaction::whereBetween("created_at", [$start, $end])
            ->select(
                "gateway",
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(amount) as revenue"),
            )
            ->where("status", "completed")
            ->groupBy("gateway")
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    $item->gateway => [
                        "total" => $item->total,
                        "revenue" => (float) $item->revenue,
                    ],
                ];
            })
            ->toArray();

        return $gatewayStats;
    }

    /**
     * Get transactions summary
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return array
     */
    private function getTransactionsSummary(
        \Illuminate\Database\Eloquent\Builder $query,
    ): array {
        $cloneQuery = clone $query;

        $summary = $cloneQuery
            ->select(
                DB::raw("COUNT(*) as total"),
                DB::raw(
                    'SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as total_revenue',
                ),
                DB::raw(
                    'SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count',
                ),
                DB::raw(
                    'SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count',
                ),
                DB::raw(
                    'SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count',
                ),
            )
            ->first();

        return [
            "total" => $summary->total ?? 0,
            "total_revenue" => (float) ($summary->total_revenue ?? 0),
            "completed" => $summary->completed_count ?? 0,
            "failed" => $summary->failed_count ?? 0,
            "pending" => $summary->pending_count ?? 0,
        ];
    }

    /**
     * Get gateway transaction statistics
     *
     * @param string $gateway
     * @return array
     */
    private function getGatewayTransactionStats(string $gateway): array
    {
        $stats = Transaction::where("gateway", $gateway)
            ->select(
                DB::raw("COUNT(*) as total"),
                DB::raw(
                    'SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as revenue',
                ),
                DB::raw(
                    'SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed',
                ),
                DB::raw(
                    'SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed',
                ),
                DB::raw(
                    'SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending',
                ),
            )
            ->first();

        return [
            "total" => $stats->total ?? 0,
            "revenue" => (float) ($stats->revenue ?? 0),
            "completed" => $stats->completed ?? 0,
            "failed" => $stats->failed ?? 0,
            "pending" => $stats->pending ?? 0,
        ];
    }

    /**
     * Get analytics data
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function getAnalyticsData(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $daysDiff = $start->diffInDays($end);

        // Daily transactions
        $dailyTransactions = [];
        for ($i = 0; $i <= $daysDiff; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $nextDate = $start
                ->copy()
                ->addDays($i + 1)
                ->toDateString();

            $stats = Transaction::whereBetween("created_at", [
                $date . " 00:00:00",
                $nextDate . " 00:00:00",
            ])
                ->select(
                    DB::raw("COUNT(*) as total"),
                    DB::raw(
                        'SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as revenue',
                    ),
                    DB::raw(
                        'SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed',
                    ),
                )
                ->first();

            $dailyTransactions[] = [
                "date" => $date,
                "total" => $stats->total ?? 0,
                "revenue" => (float) ($stats->revenue ?? 0),
                "completed" => $stats->completed ?? 0,
            ];
        }

        // Gateway performance
        $gatewayPerformance = $this->getGatewayPerformance(
            $startDate,
            $endDate,
        );

        // Customer statistics
        $customerStats = $this->getCustomerStatistics($startDate, $endDate);

        return [
            "daily_transactions" => $dailyTransactions,
            "gateway_performance" => $gatewayPerformance,
            "customer_statistics" => $customerStats,
            "period_days" => $daysDiff + 1,
        ];
    }

    /**
     * Get gateway performance
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function getGatewayPerformance(
        string $startDate,
        string $endDate,
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $performance = Transaction::whereBetween("created_at", [$start, $end])
            ->select("gateway")
            ->selectRaw("COUNT(*) as total_transactions")
            ->selectRaw(
                'SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as total_revenue',
            )
            ->selectRaw(
                'SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_transactions',
            )
            ->selectRaw(
                'SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_transactions',
            )
            ->selectRaw(
                'ROUND((SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as success_rate',
            )
            ->groupBy("gateway")
            ->orderBy("total_revenue", "desc")
            ->get()
            ->map(function ($item) {
                return [
                    "gateway" => $item->gateway,
                    "total_transactions" => $item->total_transactions,
                    "total_revenue" => (float) $item->total_revenue,
                    "successful_transactions" => $item->successful_transactions,
                    "failed_transactions" => $item->failed_transactions,
                    "success_rate" => (float) $item->success_rate,
                ];
            })
            ->toArray();

        return $performance;
    }

    /**
     * Get customer statistics
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function getCustomerStatistics(
        string $startDate,
        string $endDate,
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Top customers by revenue
        $topCustomers = Transaction::whereBetween("created_at", [$start, $end])
            ->where("status", "completed")
            ->select("customer_email", "customer_name")
            ->selectRaw("COUNT(*) as transaction_count")
            ->selectRaw("SUM(amount) as total_spent")
            ->groupBy("customer_email", "customer_name")
            ->orderBy("total_spent", "desc")
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    "email" => $item->customer_email,
                    "name" => $item->customer_name,
                    "transaction_count" => $item->transaction_count,
                    "total_spent" => (float) $item->total_spent,
                ];
            })
            ->toArray();

        // Customer acquisition
        $customerAcquisition = Transaction::whereBetween("created_at", [
            $start,
            $end,
        ])
            ->select(DB::raw("DATE(created_at) as date"))
            ->selectRaw("COUNT(DISTINCT customer_email) as new_customers")
            ->groupBy(DB::raw("DATE(created_at)"))
            ->orderBy("date")
            ->get()
            ->map(function ($item) {
                return [
                    "date" => $item->date,
                    "new_customers" => $item->new_customers,
                ];
            })
            ->toArray();

        return [
            "top_customers" => $topCustomers,
            "customer_acquisition" => $customerAcquisition,
            "total_unique_customers" => Transaction::whereBetween(
                "created_at",
                [$start, $end],
            )
                ->distinct("customer_email")
                ->count("customer_email"),
        ];
    }

    /**
     * Prepare export data
     *
     * @param \Illuminate\Database\Eloquent\Collection $transactions
     * @param string $format
     * @return array
     */
    private function prepareExportData(
        \Illuminate\Database\Eloquent\Collection $transactions,
        string $format,
    ): array {
        $data = $transactions
            ->map(function ($transaction) {
                return [
                    "reference" => $transaction->reference,
                    "gateway" => $transaction->gateway,
                    "amount" => $transaction->amount,
                    "currency" => $transaction->currency,
                    "status" => $transaction->status,
                    "description" => $transaction->description,
                    "customer_email" => $transaction->customer_email,
                    "customer_name" => $transaction->customer_name,
                    "customer_phone" => $transaction->customer_phone,
                    "payment_method" => $transaction->payment_method,
                    "created_at" => $transaction->created_at->toDateTimeString(),
                    "completed_at" => $transaction->completed_at
                        ? $transaction->completed_at->toDateTimeString()
                        : null,
                    "failed_at" => $transaction->failed_at
                        ? $transaction->failed_at->toDateTimeString()
                        : null,
                    "refund_amount" => $transaction->refund_amount,
                    "fee_amount" => $transaction->fee_amount,
                    "net_amount" => $transaction->net_amount,
                    "ip_address" => $transaction->ip_address,
                ];
            })
            ->toArray();

        // Format based on export type
        switch ($format) {
            case "csv":
                return $this->formatAsCsv($data);
            case "json":
                return $data;
            case "xlsx":
                return $this->formatAsXlsx($data);
            default:
                return $data;
        }
    }

    /**
     * Format data as CSV
     *
     * @param array $data
     * @return string
     */
    private function formatAsCsv(array $data): string
    {
        if (empty($data)) {
            return "";
        }

        $output = fopen("php://temp", "r+");

        // Write headers
        fputcsv($output, array_keys($data[0]));

        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Format data as XLSX
     *
     * @param array $data
     * @return array
     */
    private function formatAsXlsx(array $data): array
    {
        // In a real implementation, this would generate an Excel file
        // For now, return the data with metadata
        return [
            "data" => $data,
            "total_rows" => count($data),
            "columns" => empty($data) ? [] : array_keys($data[0]),
            "format" => "xlsx",
            "file_size" => "N/A (simulated)",
        ];
    }

    /**
     * Generate export download URL
     *
     * @param mixed $exportData
     * @param string $format
     * @return string
     */
    private function generateExportDownloadUrl(
        $exportData,
        string $format,
    ): string {
        // In a real implementation, this would generate a temporary download URL
        // For now, return a simulated URL
        $timestamp = time();
        $filename = "transactions_export_{$timestamp}.{$format}";

        return url("/admin/payments/export/download/{$filename}");
    }

    /**
     * Get subscription statistics
     *
     * @return array
     */
    private function getSubscriptionStatistics(): array
    {
        $stats = Transaction::where("is_subscription", true)
            ->select(
                DB::raw("COUNT(*) as total"),
                DB::raw(
                    'SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active',
                ),
                DB::raw(
                    'SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled',
                ),
                DB::raw(
                    'SUM(CASE WHEN status = "expired" THEN 1 ELSE 0 END) as expired',
                ),
                DB::raw("SUM(amount) as monthly_recurring_revenue"),
            )
            ->first();

        return [
            "total" => $stats->total ?? 0,
            "active" => $stats->active ?? 0,
            "cancelled" => $stats->cancelled ?? 0,
            "expired" => $stats->expired ?? 0,
            "monthly_recurring_revenue" =>
                $stats->monthly_recurring_revenue ?? 0,
        ];
    }
}
