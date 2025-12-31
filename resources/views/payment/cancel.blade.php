<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - {{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cancel-container {
            max-width: 600px;
            margin: 20px;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        .cancel-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 48px;
            animation: shake 0.8s ease-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        .cancel-title {
            font-size: 32px;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 15px;
        }
        .cancel-message {
            font-size: 18px;
            color: #6c757d;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .payment-details-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
            border-left: 5px solid #dc3545;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #6c757d;
            font-weight: 500;
        }
        .detail-value {
            font-weight: 600;
            color: #212529;
        }
        .amount-highlight {
            font-size: 28px;
            font-weight: 700;
            color: #dc3545;
            margin: 10px 0;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid #6c757d;
            color: #6c757d;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-outline:hover {
            border-color: #dc3545;
            color: #dc3545;
            transform: translateY(-2px);
        }
        .warning-badge {
            background: #f8d7da;
            color: #721c24;
            border-radius: 20px;
            padding: 8px 20px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .error-info {
            background: #fff3cd;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .troubleshooting {
            background: #e3f2fd;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            text-align: left;
        }
        .troubleshooting h5 {
            color: #1976d2;
            margin-bottom: 15px;
        }
        .troubleshooting ul {
            padding-left: 20px;
            margin-bottom: 0;
        }
        .troubleshooting li {
            margin-bottom: 10px;
            color: #424242;
        }
        .troubleshooting li:last-child {
            margin-bottom: 0;
        }
        .support-notice {
            background: #d1ecf1;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        .timer {
            font-size: 14px;
            color: #6c757d;
            margin-top: 20px;
        }
        .timer span {
            font-weight: 600;
            color: #dc3545;
        }
        .retry-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
        .retry-section h6 {
            color: #dc3545;
            margin-bottom: 15px;
        }
        .alternative-methods {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px dashed #dee2e6;
        }
        .alternative-methods h6 {
            color: #6c757d;
            margin-bottom: 15px;
        }
        .method-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .method-card:hover {
            border-color: #dc3545;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .method-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
            color: white;
        }
    </style>
</head>
<body>
    <div class="cancel-container">
        <!-- Cancel Icon -->
        <div class="cancel-icon">
            <i class="fas fa-times"></i>
        </div>

        <!-- Cancel Title -->
        <h1 class="cancel-title">Payment Cancelled</h1>

        <!-- Cancel Message -->
        <p class="cancel-message">
            Your payment was not completed. The transaction has been cancelled.
        </p>

        <!-- Warning Badge -->
        <div class="warning-badge">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Payment Not Processed</span>
        </div>

        <!-- Payment Details -->
        <div class="payment-details-card">
            <h5 class="mb-4"><i class="fas fa-receipt me-2"></i>Transaction Details</h5>

            @if(isset($transaction))
            <div class="detail-row">
                <span class="detail-label">Reference:</span>
                <span class="detail-value">{{ $transaction['reference'] ?? 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value">
                    <i class="fas fa-{{ $transaction['gateway_icon'] ?? 'credit-card' }} me-1"></i>
                    {{ $transaction['gateway_name'] ?? 'Payment Gateway' }}
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date & Time:</span>
                <span class="detail-value">{{ $transaction['created_at'] ?? now()->format('Y-m-d H:i:s') }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">
                    <span class="badge bg-danger">Cancelled</span>
                </span>
            </div>
            <div class="text-center mt-4">
                <div class="amount-highlight">
                    {{ $transaction['currency'] ?? 'ZAR' }} {{ number_format($transaction['amount'] ?? 0, 2) }}
                </div>
                <p class="text-muted mb-0">{{ $transaction['description'] ?? 'Payment' }}</p>
            </div>
            @endif
        </div>

        <!-- Error Information -->
        @if(isset($error))
        <div class="error-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Reason:</strong> {{ $error['message'] ?? 'Payment was cancelled by user' }}
            @if(isset($error['code']))
            <br><small>Error Code: {{ $error['code'] }}</small>
            @endif
        </div>
        @endif

        <!-- Retry Section -->
        <div class="retry-section">
            <h6><i class="fas fa-redo me-2"></i>Try Again</h6>
            <p class="mb-3">You can retry the payment with the same details:</p>
            <form action="{{ route('payment.retry') }}" method="POST" class="mb-3">
                @csrf
                <input type="hidden" name="reference" value="{{ $transaction['reference'] ?? '' }}">
                <input type="hidden" name="gateway" value="{{ $transaction['gateway'] ?? '' }}">
                <button type="submit" class="btn btn-danger w-100">
                    <i class="fas fa-redo me-2"></i>Retry Payment
                </button>
            </form>
            <small class="text-muted">Your payment details will be pre-filled for convenience.</small>
        </div>

        <!-- Alternative Payment Methods -->
        <div class="alternative-methods">
            <h6><i class="fas fa-exchange-alt me-2"></i>Try a Different Payment Method</h6>
            <p class="text-muted mb-3">Sometimes a different payment method works better:</p>

            <div class="method-card" onclick="selectMethod('payfast')">
                <div class="d-flex align-items-center">
                    <div class="method-icon" style="background: #28a745;">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">PayFast</h6>
                        <small class="text-muted">Instant EFT & Card Payments</small>
                    </div>
                </div>
            </div>

            <div class="method-card" onclick="selectMethod('eft')">
                <div class="d-flex align-items-center">
                    <div class="method-icon" style="background: #007bff;">
                        <i class="fas fa-university"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Bank Transfer (EFT)</h6>
                        <small class="text-muted">Manual bank transfer</small>
                    </div>
                </div>
            </div>

            <div class="method-card" onclick="selectMethod('paystack')">
                <div class="d-flex align-items-center">
                    <div class="method-icon" style="background: #6f42c1;">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">PayStack</h6>
                        <small class="text-muted">Card payments across Africa</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Troubleshooting Tips -->
        <div class="troubleshooting">
            <h5><i class="fas fa-wrench me-2"></i>Troubleshooting Tips</h5>
            <ul>
                <li>Check that your payment details are correct</li>
                <li>Ensure you have sufficient funds in your account</li>
                <li>Try using a different browser or device</li>
                <li>Clear your browser cache and cookies</li>
                <li>Contact your bank if you suspect card issues</li>
                <li>Try a different payment method from the options above</li>
            </ul>
        </div>

        <!-- Support Notice -->
        <div class="support-notice">
            <i class="fas fa-life-ring me-2"></i>
            Need help? Contact our support team at <strong>support@example.com</strong> or call <strong>+27 11 123 4567</strong>
        </div>

        <!-- Action Buttons -->
        <div class="btn-group">
            <a href="{{ route('payment.form') }}" class="btn btn-danger">
                <i class="fas fa-redo me-2"></i>Try Again
            </a>
            <a href="{{ route('home') }}" class="btn btn-outline">
                <i class="fas fa-home me-2"></i>Return to Home
            </a>
            <a href="{{ route('contact') }}" class="btn btn-outline">
                <i class="fas fa-headset me-2"></i>Contact Support
            </a>
        </div>

        <!-- Auto-redirect Timer -->
        <div class="timer">
            <i class="fas fa-clock me-1"></i>
            You will be redirected to the payment page in <span id="countdown">15</span> seconds
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Countdown timer for auto-redirect
            let countdown = 15;
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(() => {
                countdown--;
                countdownElement.textContent = countdown;

                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = "{{ route('payment.form') }}";
                }
            }, 1000);

            // Store transaction in localStorage for reference
            @if(isset($transaction))
            localStorage.setItem('failedPayment', JSON.stringify({
                reference: '{{ $transaction['reference'] }}',
                amount: '{{ $transaction['amount'] }}',
                currency: '{{ $transaction['currency'] }}',
                gateway: '{{ $transaction['gateway'] }}',
                error: '{{ $error['message'] ?? 'Cancelled by user' }}'
            }));
            @endif

            // Function to select alternative payment method
            window.selectMethod = function(method) {
                // Store selected method in localStorage
                localStorage.setItem('preferredPaymentMethod', method);

                // Show confirmation and redirect
                alert(`You've selected ${method.toUpperCase()}. You'll be redirected to try again with this method.`);
                window.location.href = "{{ route('payment.form') }}?method=" + method;
            };

            // Check if there's a preferred method from previous attempts
            const preferredMethod = localStorage.getItem('preferredPaymentMethod');
            if (preferredMethod) {
                console.log('Preferred payment method detected:', preferredMethod);
            }
        });
    </script>
</body>
</html>
