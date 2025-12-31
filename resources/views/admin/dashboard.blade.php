<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Dashboard - {{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
            --sidebar-width: 250px;
            --header-height: 70px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 20px 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h3 {
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header h3 i {
            font-size: 24px;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            text-decoration: none;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .nav-link .badge {
            margin-left: auto;
            font-size: 0.7em;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            color: var(--dark-color);
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }

        .stats-content h3 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
        }

        .stats-content p {
            color: #6c757d;
            margin: 5px 0 0;
            font-size: 14px;
        }

        .stats-change {
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }

        .stats-change.positive {
            color: var(--success-color);
        }

        .stats-change.negative {
            color: var(--danger-color);
        }

        /* Charts */
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h4 {
            margin: 0;
            font-weight: 600;
            color: var(--dark-color);
        }

        /* Tables */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h4 {
            margin: 0;
            font-weight: 600;
            color: var(--dark-color);
        }

        .dataTables_wrapper {
            padding: 0;
        }

        .dataTables_filter input {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 5px 10px;
        }

        /* Status Badges */
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .badge-warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }

        .badge-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        .badge-info {
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .action-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }

        .action-card i {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .action-card h5 {
            margin: 0;
            font-weight: 600;
            color: var(--dark-color);
        }

        .action-card p {
            color: #6c757d;
            font-size: 12px;
            margin: 5px 0 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }

            .sidebar:hover {
                width: var(--sidebar-width);
            }

            .sidebar-header h3 span,
            .nav-link span {
                display: none;
            }

            .sidebar:hover .sidebar-header h3 span,
            .sidebar:hover .nav-link span {
                display: inline;
            }

            .main-content {
                margin-left: 70px;
            }

            .sidebar:hover + .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #dee2e6;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>
                <i class="fas fa-credit-card"></i>
                <span>Payment Gateway</span>
            </h3>
            <small class="text-white-50">Admin Dashboard</small>
        </div>

        <nav class="nav flex-column">
            <a href="#dashboard" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="#transactions" class="nav-link">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
                <span class="badge bg-light text-primary">{{ $stats['total_transactions'] ?? 0 }}</span>
            </a>
            <a href="#gateways" class="nav-link">
                <i class="fas fa-plug"></i>
                <span>Gateways</span>
                <span class="badge bg-light text-primary">{{ count($gateways ?? []) }}</span>
            </a>
            <a href="#subscriptions" class="nav-link">
                <i class="fas fa-sync"></i>
                <span>Subscriptions</span>
                <span class="badge bg-light text-primary">{{ $stats['active_subscriptions'] ?? 0 }}</span>
            </a>
            <a href="#analytics" class="nav-link">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>
            </a>
            <a href="#reports" class="nav-link">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
            <a href="#settings" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <div class="mt-auto">
                <a href="#support" class="nav-link">
                    <i class="fas fa-headset"></i>
                    <span>Support</span>
                </a>
                <a href="#logout" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Payment Dashboard</h1>
                <small class="text-muted">Welcome back, {{ auth()->user()->name ?? 'Admin' }}</small>
            </div>
            <div class="header-actions">
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-calendar me-2"></i>
                        Last 30 Days
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="setDateRange('today')">Today</a></li>
                        <li><a class="dropdown-item" href="#" onclick="setDateRange('yesterday')">Yesterday</a></li>
                        <li><a class="dropdown-item" href="#" onclick="setDateRange('week')">This Week</a></li>
                        <li><a class="dropdown-item" href="#" onclick="setDateRange('month')">This Month</a></li>
                        <li><a class="dropdown-item" href="#" onclick="setDateRange('quarter')">This Quarter</a></li>
                        <li><a class="dropdown-item" href="#" onclick="setDateRange('year')">This Year</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="showCustomDateRange()">Custom Range</a></li>
                    </ul>
                </div>
                <button class="btn btn-primary" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i>
                    Refresh
                </button>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-card" onclick="processRefunds()">
                <i class="fas fa-money-bill-wave"></i>
                <h5>Process Refunds</h5>
                <p>Manage pending refund requests</p>
            </div>
            <div class="action-card" onclick="reconcilePayments()">
                <i class="fas fa-balance-scale"></i>
                <h5>Reconcile Payments</h5>
                <p>Match transactions with bank statements</p>
            </div>
            <div class="action-card" onclick="generateReport()">
                <i class="fas fa-file-export"></i>
                <h5>Generate Report</h5>
                <p>Create custom payment reports</p>
            </div>
            <div class="action-card" onclick="manageGateways()">
                <i class="fas fa-cogs"></i>
                <h5>Manage Gateways</h5>
                <p>Configure payment gateways</p>
            </div>
            <div class="action-card" onclick="viewAlerts()">
                <i class="fas fa-bell"></i>
                <h5>View Alerts</h5>
                <p>Check system notifications</p>
            </div>
            <div class="action-card" onclick="backupData()">
                <i class="fas fa-database"></i>
                <h5>Backup Data</h5>
                <p>Create data backup</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--success-color) 0%, #20c997 100%);">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stats-content">
                        <h3>{{ $stats['total_revenue'] ?? '0' | number_format(2) }}</h3>
                        <p>Total Revenue</p>
                        <div class="stats-change positive">
                            <i class="fas fa-arrow-up"></i> 12.5% from last month
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="stats-content">
                        <h3>{{ $stats['total_transactions'] ?? 0 }}</h3>
                        <p>Total Transactions</p>
                        <div class="stats-change positive">
                            <i class="fas fa-arrow-up"></i> 8.3% from last month
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--warning-color) 0%, #fd7e14 100%);">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stats-content">
                        <h3>{{ $stats['pending_payments'] ?? 0 }}</h3>
                        <p>Pending Payments</p>
                        <div class="stats-change negative">
                            <i class="fas fa-arrow-down"></i> 3.2% from last month
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--danger-color) 0%, #e83e8c 100%);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stats-content">
                        <h3>{{ $stats['failed_payments'] ?? 0 }}</h3>
                        <p>Failed Payments</p>
                        <div class="stats-change positive">
                            <i class="fas fa-arrow-down"></i> 15.7% from last month
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-md-8">
                <div class="chart-container">
                    <div class="chart-header">
                        <h4>Revenue Overview</h4>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-secondary active" onclick="setChartType('revenue')">Revenue</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="setChartType('transactions')">Transactions</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="setChartType('conversion')">Conversion</button>
                        </div>
                    </div>
                    <div id="revenueChart"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-container">
                    <div class="chart-header">
                        <h4>Gateway Distribution</h4>
                    </div>
                    <div id="gatewayChart"></div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="table-container">
            <div class="table-header">
                <h4>Recent Transactions</h4>
                <a href="#transactions" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <table id="transactionsTable" class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Gateway</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recent_transactions ?? [] as $transaction)
                    <tr>
                        <td>{{ $transaction['id'] }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $transaction['customer_name'] }}</div>
                                    <small class="text-muted">{{ $transaction['customer_email'] }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold">{{ $transaction['currency'] }} {{ number_format($transaction['amount'], 2) }}</div>
                            <small class="text-muted">{{ $transaction['description'] }}</small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-{{ $transaction['gateway_icon'] }} me-1"></i>
                                {{ $transaction['gateway_name'] }}
                            </span>
                        </td>
                        <td>
                            @php
                                $statusClass = [
                                    'completed' => 'badge-success',
                                    'pending' => 'badge-warning',
                                    'failed' => 'badge-danger',
                                    'refunded' => 'badge-info',
                                ][$transaction['status']] ?? 'badge-secondary';
                            @endphp
                            <span class="badge-status {{ $statusClass }}">
                                {{ ucfirst($transaction['status']) }}
                            </span>
                        </td>
                        <td>{{ $transaction['created_at']->format('M d, Y H:i') }}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="viewTransaction('{{ $transaction['id'] }}')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-info" onclick="refundTransaction('{{ $transaction['id'] }}')">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="deleteTransaction('{{ $transaction['id'] }}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- System Status -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <div class="chart-header">
                        <h4>System Status</h4>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-success d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">API Status</div>
                                    <small class="text-muted">All systems operational</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-success d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-database text-white"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Database</div>
                                    <small class="text-muted">Connected & healthy</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-exclamation-triangle text-white"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Queue</div>
                                    <small class="text-muted">3 pending jobs</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-success d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-shield-alt text-white"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Security</div>
                                    <small class="text-muted">All checks passed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <div class="chart-header">
                        <h4>Recent Activity</h4>
                    </div>
                    <div class="activity-feed">
                        @foreach($recent_activity ?? [] as $activity)
                        <div class="activity-item mb-3">
                            <div class="d-flex">
                                <div class="activity-icon me-3">
                                    <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <i class="fas fa-{{ $activity['icon'] }}"></i>
                                    </div>
                                </div>
                                <div class="activity-content flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <div class="fw-bold">{{ $activity['title'] }}</div>
                                        <small class="text-muted">{{ $activity['time'] }}</small>
                                    </div>
                                    <p class="mb-0 text-muted">{{ $activity['description'] }}</p>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.js"></script>
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#transactionsTable').DataTable({
                pageLength: 10,
                order: [[5, 'desc']],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search transactions..."
                }
            });
        });

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue Chart
            const revenueOptions = {
                series: [{
                    name: 'Revenue',
                    data: [{{ implode(',', $chart_data['revenue'] ?? [0,0,0,0,0,0,0]) }}]
                }],
                chart: {
                    height: 350,
                    type: 'area',
                    toolbar: {
                        show: true
                    }
                },
                colors: ['#667eea'],
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                xaxis: {
                    categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    labels: {
                        style: {
                            colors: '#6c757d'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        formatter: function(value) {
                            return 'R ' + value.toLocaleString();
                        },
                        style: {
                            colors: '#6c757d'
                        }
                    }
                },
                grid: {
                    borderColor: '#f1f1f1',
                },
                tooltip: {
                    y: {
                        formatter: function(value) {
                            return 'R ' + value.toLocaleString();
                        }
                    }
                }
            };

            const revenueChart = new ApexCharts(document.querySelector("#revenueChart"), revenueOptions);
            revenueChart.render();

            // Gateway Distribution Chart
            const gatewayOptions = {
                series: {{ json_encode($chart_data['gateway_distribution'] ?? [30, 25, 15, 10, 8, 7, 5]) }},
                chart: {
                    type: 'donut',
                    height: 350
                },
                colors: ['#667eea', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1', '#fd7e14'],
                labels: ['PayFast', 'PayStack', 'PayPal', 'Stripe', 'Ozow', 'EFT', 'Other'],
                responsive: [{
                    breakpoint: 480,
                    options: {
                        chart: {
                            width: 200
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }],
                legend: {
                    position: 'right',
                    offsetY: 0,
                    height: 230,
                }
            };

            const gatewayChart = new ApexCharts(document.querySelector("#gatewayChart"), gatewayOptions);
            gatewayChart.render();
        });

        // Dashboard Functions
        function setDateRange(range) {
            console.log('Setting date range:', range);
            // Implement date range filtering
            showToast('Date range updated to ' + range);
        }

        function setChartType(type) {
            console.log('Setting chart type:', type);
            // Implement chart type switching
            showToast('Chart type changed to ' + type);
        }

        function refreshData() {
            const btn = event.target;
            const originalHTML = btn.innerHTML;

            btn.innerHTML = '<span class="loading-spinner"></span> Refreshing...';
            btn.disabled = true;

            // Simulate API call
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                showToast('Dashboard data refreshed successfully');
            }, 1500);
        }

        function viewTransaction(id) {
            console.log('Viewing transaction:', id);
            // Implement transaction view modal
            showToast('Opening transaction details for ID: ' + id);
        }

        function refundTransaction(id) {
            if (confirm('Are you sure you want to refund this transaction?')) {
                console.log('Refunding transaction:', id);
                // Implement refund logic
                showToast('Refund initiated for transaction: ' + id);
            }
        }

        function deleteTransaction(id) {
            if (confirm('Are you sure you want to delete this transaction? This action cannot be undone.')) {
                console.log('Deleting transaction:', id);
                // Implement delete logic
                showToast('Transaction deleted: ' + id);
            }
        }

        // Quick Action Functions
        function processRefunds() {
            showToast('Opening refund processing interface');
        }

        function reconcilePayments() {
            showToast('Opening payment reconciliation');
        }

        function generateReport() {
            showToast('Opening report generator');
        }

        function manageGateways() {
            showToast('Opening gateway management');
        }

        function viewAlerts() {
            showToast('Opening alerts panel');
        }

        function backupData() {
            showToast('Initiating data backup');
        }

        // Utility Functions
        function showToast(message, type = 'success') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');

            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;

            // Add to container
            const container = document.querySelector('.toast-container') || createToastContainer();
            container.appendChild(toast);

            // Initialize and show
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();

            // Remove after hide
            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            });
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        }

        function showCustomDateRange() {
            // Implement custom date range picker
            const startDate = prompt('Enter start date (YYYY-MM-DD):');
            const endDate = prompt('Enter end date (YYYY-MM-DD):');

            if (startDate && endDate) {
                setDateRange('custom');
                showToast(`Custom range set: ${startDate} to ${endDate}`);
            }
        }

        // Auto-refresh every 5 minutes
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                console.log('Auto-refreshing dashboard data...');
                // Implement auto-refresh logic
            }
        }, 300000);

        // Handle sidebar collapse on mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('collapsed');
        }

        // Initialize tooltips
        $(function () {
            $('[data-bs-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>
