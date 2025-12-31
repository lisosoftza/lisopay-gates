<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - {{ config('app.name', 'Laravel') }}</title>
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
        .success-container {
            max-width: 600px;
            margin: 20px;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 48px;
            animation: bounceIn 0.8s ease-out;
        }
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); opacity: 1; }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); }
        }
        .success-title {
            font-size: 32px;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 15px;
        }
        .success-message {
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
            border-left: 5px solid #28a745;
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
            color: #28a745;
            margin: 10px 0;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
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
            border-color: #28a745;
            color: #28a745;
            transform: translateY(-2px);
        }
        .confirmation-badge {
            background: #d4edda;
            color: #155724;
            border-radius: 20px;
            padding: 8px 20px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .receipt-info {
            background: #e8f5e9;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: #2e7d32;
        }
        .whats-next {
            background: #e3f2fd;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            text-align: left;
        }
        .whats-next h5 {
            color: #1976d2;
            margin-bottom: 15px;
        }
        .whats-next ul {
            padding-left: 20px;
            margin-bottom: 0;
        }
        .whats-next li {
            margin-bottom: 10px;
            color: #424242;
        }
        .whats-next li:last-child {
            margin-bottom: 0;
        }
        .email-notice {
            background: #fff3cd;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: #856404;
            border-left: 4px solid #ffc107;
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
    </style>
</head>
<body>
    <div class="success-container">
        <!-- Success Icon -->
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>

        <!-- Success Title -->
        <h1 class="success-title">Payment Successful!</h1>

        <!-- Success Message -->
        <p class="success-message">
            Thank you for your payment. Your transaction has been completed successfully.
        </p>

        <!-- Confirmation Badge -->
        <div class="confirmation-badge">
            <i class="fas fa-shield-alt"></i>
            <span>Payment Confirmed & Secured</span>
        </div>

        <!-- Payment Details -->
        <div class="payment-details-card">
            <h5 class="mb-4"><i class="fas fa-receipt me-2"></i>Payment Details</h5>

            @if(isset($transaction))
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value">{{ $transaction['id'] ?? 'N/A' }}</span>
            </div>
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
                    <span class="badge bg-success">Completed</span>
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

        <!-- Receipt Information -->
        <div class="receipt-info">
            <i class="fas fa-envelope me-2"></i>
            A receipt has been sent to <strong>{{ $customer['email'] ?? 'your email address' }}</strong>
        </div>

        <!-- Email Notice -->
        <div class="email-notice">
            <i class="fas fa-info-circle me-2"></i>
            Please check your email (including spam folder) for the payment confirmation and receipt.
        </div>

        <!-- What's Next Section -->
        <div class="whats-next">
            <h5><i class="fas fa-forward me-2"></i>What happens next?</h5>
            <ul>
                <li>Your order is being processed and will be shipped shortly</li>
                <li>You will receive tracking information via email within 24 hours</li>
                <li>For any questions, contact our support team at support@example.com</li>
                <li>Keep your transaction ID for future reference: <strong>{{ $transaction['id'] ?? 'N/A' }}</strong></li>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="btn-group">
            <a href="{{ route('home') }}" class="btn btn-success">
                <i class="fas fa-home me-2"></i>Return to Home
            </a>
            <a href="{{ route('dashboard') }}" class="btn btn-outline">
                <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
            </a>
            <button onclick="window.print()" class="btn btn-outline">
                <i class="fas fa-print me-2"></i>Print Receipt
            </button>
        </div>

        <!-- Auto-redirect Timer -->
        <div class="timer">
            <i class="fas fa-clock me-1"></i>
            You will be redirected to the dashboard in <span id="countdown">10</span> seconds
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Countdown timer for auto-redirect
            let countdown = 10;
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(() => {
                countdown--;
                countdownElement.textContent = countdown;

                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = "{{ route('dashboard') }}";
                }
            }, 1000);

            // Add confetti effect for celebration
            setTimeout(() => {
                createConfetti();
            }, 500);

            function createConfetti() {
                const colors = ['#28a745', '#20c997', '#17a2b8', '#007bff', '#6f42c1'];
                const confettiCount = 100;

                for (let i = 0; i < confettiCount; i++) {
                    const confetti = document.createElement('div');
                    confetti.style.position = 'fixed';
                    confetti.style.width = '10px';
                    confetti.style.height = '10px';
                    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.borderRadius = '50%';
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.top = '-20px';
                    confetti.style.opacity = '0.8';
                    confetti.style.zIndex = '9999';
                    confetti.style.pointerEvents = 'none';

                    document.body.appendChild(confetti);

                    // Animation
                    const animation = confetti.animate([
                        { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
                        { transform: `translateY(${window.innerHeight + 20}px) rotate(${Math.random() * 360}deg)`, opacity: 0 }
                    ], {
                        duration: 2000 + Math.random() * 3000,
                        easing: 'cubic-bezier(0.215, 0.610, 0.355, 1)'
                    });

                    animation.onfinish = () => confetti.remove();
                }
            }

            // Store transaction in localStorage for reference
            @if(isset($transaction))
            localStorage.setItem('lastPayment', JSON.stringify({
                id: '{{ $transaction['id'] }}',
                amount: '{{ $transaction['amount'] }}',
                currency: '{{ $transaction['currency'] }}',
                date: '{{ $transaction['created_at'] ?? now()->format('Y-m-d H:i:s') }}',
                reference: '{{ $transaction['reference'] }}'
            }));
            @endif
        });
    </script>
</body>
</html>
