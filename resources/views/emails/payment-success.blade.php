<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation - {{ config('app.name', 'Laravel') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .email-header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .email-body {
            padding: 30px;
        }
        .success-icon {
            text-align: center;
            margin-bottom: 20px;
        }
        .success-icon .icon {
            display: inline-block;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            line-height: 80px;
            color: white;
            font-size: 36px;
        }
        .greeting {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
        }
        .payment-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
            border-left: 4px solid #28a745;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
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
            text-align: right;
        }
        .amount-highlight {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(32, 201, 151, 0.1) 100%);
            border-radius: 8px;
        }
        .amount-highlight .amount {
            font-size: 32px;
            font-weight: 700;
            color: #28a745;
            margin: 5px 0;
        }
        .amount-highlight .currency {
            font-size: 18px;
            color: #6c757d;
        }
        .next-steps {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .next-steps h3 {
            color: #1976d2;
            margin-top: 0;
            font-size: 18px;
        }
        .next-steps ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .next-steps li {
            margin-bottom: 8px;
            color: #424242;
        }
        .receipt-info {
            background: #e8f5e9;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            color: #2e7d32;
            font-size: 14px;
        }
        .security-notice {
            background: #fff3cd;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            color: #856404;
            font-size: 14px;
            border-left: 4px solid #ffc107;
        }
        .cta-button {
            display: block;
            width: 100%;
            text-align: center;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            text-decoration: none;
            padding: 15px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            margin: 25px 0;
            transition: all 0.3s ease;
        }
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .email-footer {
            background: #343a40;
            color: #adb5bd;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        .email-footer a {
            color: #adb5bd;
            text-decoration: none;
        }
        .email-footer a:hover {
            color: white;
        }
        .contact-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #495057;
        }
        .transaction-id {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 14px;
            text-align: center;
            margin: 10px 0;
            color: #495057;
        }
        .thank-you {
            text-align: center;
            font-size: 18px;
            color: #28a745;
            margin: 20px 0;
            font-weight: 600;
        }
        .social-links {
            text-align: center;
            margin: 20px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #6c757d;
            text-decoration: none;
        }
        .social-links a:hover {
            color: #28a745;
        }
        @media (max-width: 600px) {
            .email-container {
                border-radius: 0;
            }
            .email-body {
                padding: 20px;
            }
            .detail-row {
                flex-direction: column;
            }
            .detail-value {
                text-align: left;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>Payment Successful!</h1>
            <p>Your payment has been processed successfully</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <!-- Success Icon -->
            <div class="success-icon">
                <div class="icon">✓</div>
            </div>

            <!-- Greeting -->
            <div class="greeting">
                <p>Hello <strong>{{ $customer['name'] ?? 'Valued Customer' }}</strong>,</p>
                <p>Thank you for your payment! We've successfully processed your transaction and here are the details:</p>
            </div>

            <!-- Transaction ID -->
            <div class="transaction-id">
                Transaction ID: <strong>{{ $transaction['id'] ?? 'N/A' }}</strong>
            </div>

            <!-- Payment Details -->
            <div class="payment-details">
                <h3 style="margin-top: 0; color: #28a745;">Payment Details</h3>

                <div class="detail-row">
                    <span class="detail-label">Reference Number:</span>
                    <span class="detail-value">{{ $transaction['reference'] ?? 'N/A' }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value">
                        {{ $transaction['gateway_name'] ?? 'Payment Gateway' }}
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Date & Time:</span>
                    <span class="detail-value">{{ $transaction['created_at'] ?? now()->format('F d, Y \a\t h:i A') }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value" style="color: #28a745; font-weight: 700;">Completed</span>
                </div>
            </div>

            <!-- Amount Highlight -->
            <div class="amount-highlight">
                <div class="amount">{{ $transaction['currency'] ?? 'ZAR' }} {{ number_format($transaction['amount'] ?? 0, 2) }}</div>
                <div class="currency">{{ $transaction['description'] ?? 'Payment' }}</div>
            </div>

            <!-- Receipt Information -->
            <div class="receipt-info">
                <strong>📄 Receipt Attached</strong><br>
                A detailed receipt has been generated and is available for download.
            </div>

            <!-- Next Steps -->
            <div class="next-steps">
                <h3>What happens next?</h3>
                <ul>
                    <li>Your order is being processed and will be shipped shortly</li>
                    <li>You will receive tracking information within 24 hours</li>
                    <li>Access your order history in your account dashboard</li>
                    <li>Keep this email for your records and future reference</li>
                </ul>
            </div>

            <!-- Call to Action -->
            <a href="{{ $dashboard_url ?? '#' }}" class="cta-button">
                View Your Order Status
            </a>

            <!-- Security Notice -->
            <div class="security-notice">
                <strong>🔒 Security Notice:</strong><br>
                This payment was processed securely. Your payment information is encrypted and never stored on our servers.
            </div>

            <!-- Thank You Message -->
            <div class="thank-you">
                Thank you for your business! 🎉
            </div>

            <!-- Social Links -->
            <div class="social-links">
                <a href="{{ $website_url ?? '#' }}">Website</a> •
                <a href="{{ $support_url ?? '#' }}">Support</a> •
                <a href="{{ $faq_url ?? '#' }}">FAQ</a>
            </div>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>
                This is an automated message from {{ config('app.name', 'Laravel') }}.<br>
                Please do not reply to this email.
            </p>

            <div class="contact-info">
                <p>
                    Need help? Contact our support team:<br>
                    📧 <a href="mailto:support@example.com">support@example.com</a><br>
                    📞 +27 11 123 4567
                </p>

                <p style="margin-top: 15px; font-size: 12px; color: #6c757d;">
                    © {{ date('Y') }} {{ config('app.name', 'Laravel') }}. All rights reserved.<br>
                    This email was sent to {{ $customer['email'] ?? 'you' }}
                </p>
            </div>
        </div>
    </div>
</body>
</html>
