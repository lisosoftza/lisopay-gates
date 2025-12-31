<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - {{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .payment-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .payment-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 32px;
        }
        .payment-amount {
            font-size: 36px;
            font-weight: bold;
            color: #28a745;
            margin: 20px 0;
        }
        .gateway-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .gateway-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .gateway-card.selected {
            border-color: #667eea;
            background-color: rgba(102, 126, 234, 0.05);
        }
        .gateway-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
            color: white;
        }
        .gateway-info h5 {
            margin: 0;
            font-weight: 600;
        }
        .gateway-info small {
            color: #6c757d;
        }
        .payment-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-pay {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 10px;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-pay:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .security-badge {
            background: #e8f5e9;
            border-radius: 20px;
            padding: 8px 15px;
            font-size: 14px;
            color: #2e7d32;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .payment-processing {
            display: none;
            text-align: center;
            padding: 40px;
        }
        .payment-processing i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-container">
            <!-- Payment Header -->
            <div class="payment-header">
                <div class="payment-logo">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h2>Complete Your Payment</h2>
                <p class="text-muted">Select a payment method and enter your details</p>

                @if(isset($payment))
                <div class="payment-amount">
                    {{ $payment['currency'] }} {{ number_format($payment['amount'], 2) }}
                </div>
                <p class="text-muted">{{ $payment['description'] ?? 'Payment' }}</p>
                @endif
            </div>

            <!-- Payment Form -->
            <form id="paymentForm" action="{{ route('payment.process') }}" method="POST">
                @csrf

                @if(isset($payment))
                <input type="hidden" name="amount" value="{{ $payment['amount'] }}">
                <input type="hidden" name="currency" value="{{ $payment['currency'] }}">
                <input type="hidden" name="description" value="{{ $payment['description'] ?? '' }}">
                <input type="hidden" name="reference" value="{{ $payment['reference'] ?? '' }}">
                @endif

                <!-- Customer Information -->
                <div class="mb-4">
                    <h5><i class="fas fa-user me-2"></i>Your Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name"
                                   value="{{ old('customer_name', $customer['name'] ?? '') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="customer_email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="customer_email" name="customer_email"
                                   value="{{ old('customer_email', $customer['email'] ?? '') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="customer_phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="customer_phone" name="customer_phone"
                                   value="{{ old('customer_phone', $customer['phone'] ?? '') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="customer_address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="customer_address" name="customer_address"
                                   value="{{ old('customer_address', $customer['address'] ?? '') }}">
                        </div>
                    </div>
                </div>

                <!-- Payment Gateway Selection -->
                <div class="mb-4">
                    <h5><i class="fas fa-wallet me-2"></i>Select Payment Method</h5>
                    <p class="text-muted mb-3">Choose how you'd like to pay</p>

                    <div id="gatewaySelection">
                        <!-- Gateways will be populated dynamically -->
                        @foreach($gateways as $gateway)
                        <div class="gateway-card" data-gateway="{{ $gateway['code'] }}">
                            <div class="d-flex align-items-center">
                                <div class="gateway-icon" style="background: {{ $gateway['color'] ?? '#667eea' }};">
                                    <i class="{{ $gateway['icon'] ?? 'fas fa-credit-card' }}"></i>
                                </div>
                                <div class="gateway-info flex-grow-1">
                                    <h5>{{ $gateway['name'] }}</h5>
                                    <small>{{ $gateway['description'] ?? 'Secure payment' }}</small>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gateway"
                                           value="{{ $gateway['code'] }}" id="gateway_{{ $gateway['code'] }}"
                                           {{ $loop->first ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <input type="hidden" name="gateway" id="selectedGateway" value="{{ $gateways[0]['code'] ?? '' }}">
                </div>

                <!-- Gateway-specific fields (dynamic) -->
                <div id="gatewayFields">
                    <!-- Fields will be loaded based on selected gateway -->
                </div>

                <!-- Payment Details Summary -->
                <div class="payment-details">
                    <h5><i class="fas fa-receipt me-2"></i>Payment Summary</h5>
                    <div class="row">
                        <div class="col-6">
                            <p class="mb-1 text-muted">Amount</p>
                            <p class="mb-0 fw-bold">{{ $payment['currency'] ?? 'ZAR' }} {{ number_format($payment['amount'] ?? 0, 2) }}</p>
                        </div>
                        <div class="col-6 text-end">
                            <p class="mb-1 text-muted">Reference</p>
                            <p class="mb-0 fw-bold">{{ $payment['reference'] ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="security-badge">
                                <i class="fas fa-shield-alt"></i>
                                <span>Secure & Encrypted</span>
                            </span>
                        </div>
                        <div class="text-end">
                            <p class="mb-1 text-muted">Total to pay</p>
                            <h4 class="mb-0 text-success">{{ $payment['currency'] ?? 'ZAR' }} {{ number_format($payment['amount'] ?? 0, 2) }}</h4>
                        </div>
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a>
                        and authorize this payment
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-pay" id="submitBtn">
                    <span class="loading-spinner" id="loadingSpinner"></span>
                    <span id="buttonText">Pay Now</span>
                </button>

                <!-- Security Notice -->
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-lock me-1"></i>
                        Your payment is secured with 256-bit SSL encryption
                    </small>
                </div>
            </form>

            <!-- Payment Processing Screen -->
            <div class="payment-processing" id="processingScreen">
                <i class="fas fa-spinner fa-spin"></i>
                <h4>Processing Your Payment</h4>
                <p class="text-muted">Please wait while we securely process your payment...</p>
                <div class="progress mt-3" style="height: 8px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms & Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms & Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Payment Terms</h6>
                    <p>By proceeding with this payment, you agree to the following terms:</p>
                    <ul>
                        <li>All payments are processed securely through our payment partners</li>
                        <li>Refunds are subject to our refund policy and may take 5-10 business days</li>
                        <li>Your payment information is encrypted and never stored on our servers</li>
                        <li>We use industry-standard security measures to protect your data</li>
                        <li>You authorize us to charge the specified amount to your selected payment method</li>
                    </ul>
                    <h6>Privacy Policy</h6>
                    <p>We respect your privacy and are committed to protecting your personal information.
                    We only collect information necessary to process your payment and provide our services.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const gatewayCards = document.querySelectorAll('.gateway-card');
            const selectedGatewayInput = document.getElementById('selectedGateway');
            const gatewayFields = document.getElementById('gatewayFields');
            const paymentForm = document.getElementById('paymentForm');
            const submitBtn = document.getElementById('submitBtn');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const buttonText = document.getElementById('buttonText');
            const processingScreen = document.getElementById('processingScreen');

            // Gateway selection
            gatewayCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    gatewayCards.forEach(c => c.classList.remove('selected'));

                    // Add selected class to clicked card
                    this.classList.add('selected');

                    // Update radio button and hidden input
                    const gatewayCode = this.dataset.gateway;
                    const radioBtn = this.querySelector('input[type="radio"]');
                    radioBtn.checked = true;
                    selectedGatewayInput.value = gatewayCode;

                    // Load gateway-specific fields
                    loadGatewayFields(gatewayCode);
                });

                // Initialize first card as selected
                const radioBtn = card.querySelector('input[type="radio"]');
                if (radioBtn && radioBtn.checked) {
                    card.classList.add('selected');
                    loadGatewayFields(radioBtn.value);
                }
            });

            // Load gateway-specific fields
            function loadGatewayFields(gatewayCode) {
                // In a real implementation, this would fetch fields from the server
                // For now, we'll show some example fields based on the gateway
                let fieldsHtml = '';

                switch(gatewayCode) {
                    case 'payfast':
                        fieldsHtml = `
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                You will be redirected to PayFast's secure payment page
                            </div>
                        `;
                        break;
                    case 'paystack':
                        fieldsHtml = `
                            <div class="mb-3">
                                <label class="form-label">Card Details</label>
                                <div class="row">
                                    <div class="col-md-8 mb-2">
                                        <input type="text" class="form-control" placeholder="Card Number" name="card_number">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <input type="text" class="form-control" placeholder="CVV" name="cvv">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <input type="text" class="form-control" placeholder="MM/YY" name="expiry">
                                    </div>
                                </div>
                            </div>
                        `;
                        break;
                    case 'eft':
                        fieldsHtml = `
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-university me-2"></i>Bank Transfer Instructions</h6>
                                <p class="mb-1">Please use the following details for your EFT payment:</p>
                                <ul class="mb-1">
                                    <li><strong>Bank:</strong> Standard Bank</li>
                                    <li><strong>Account:</strong> 1234567890</li>
                                    <li><strong>Branch:</strong> 051001</li>
                                    <li><strong>Reference:</strong> ${document.querySelector('input[name="reference"]')?.value || 'PAYMENT'}</li>
                                </ul>
                                <p class="mb-0"><small>Payment must be made within 24 hours</small></p>
                            </div>
                        `;
                        break;
                    default:
                        fieldsHtml = `
                            <div class="alert alert-secondary">
                                <i class="fas fa-credit-card me-2"></i>
                                You will be redirected to the selected payment gateway
                            </div>
                        `;
                }

                gatewayFields.innerHTML = fieldsHtml;
            }

            // Form submission
            paymentForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Show loading state
                submitBtn.disabled = true;
                loadingSpinner.style.display = 'inline-block';
                buttonText.textContent = 'Processing...';

                // Show processing screen
                paymentForm.style.display = 'none';
                processingScreen.style.display = 'block';

                // In a real implementation, you would submit the form via AJAX
                // For now, we'll simulate a delay and then submit
                setTimeout(() => {
                    // Submit the form
                    this.submit();
                }, 2000);
            });

            // Initialize first gateway
            const firstGateway = document.querySelector('.gateway-card');
            if (firstGateway) {
                firstGateway.click();
            }
        });
    </script>
</body>
</html>
