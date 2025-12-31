# Lisosoft Laravel Payment Gateway

A comprehensive payment gateway package for Laravel with support for multiple payment providers including PayFast, PayStack, PayPal, Stripe, Ozow, Zapper, Crypto, EFT, QR Code, VodaPay, and SnapScan.

## 🎯 Features

- **Multi-Gateway Support**: 10+ payment gateways including international and South African providers
- **Unified API**: Consistent interface across all payment gateways
- **Subscription Management**: Recurring payments and subscription handling
- **Webhook Support**: Real-time payment notifications
- **Transaction Management**: Complete transaction history and reporting
- **Security**: Built-in security features and encryption
- **Analytics**: Payment analytics and reporting dashboard
- **Extensible**: Easy to add custom payment gateways

## 📦 Supported Gateways

### International Gateways
- ✅ **PayPal** - Global payment processing
- ✅ **PayStack** - African payment gateway  
- ✅ **Stripe** - International cards & payments
- ✅ **Cryptocurrency** - Bitcoin, Ethereum, USDT, USDC

### South African Gateways
- ✅ **PayFast** - Leading SA payment gateway
- ✅ **Ozow** - Instant EFT payments
- ✅ **Zapper** - QR code payments
- ✅ **SnapScan** - Mobile QR payments
- ✅ **VodaPay** - Mobile wallet payments
- ✅ **EFT/Bank Transfer** - Manual bank deposits

## 🚀 Quick Start

### Installation

```bash
composer require lisosoft/laravel-payment-gateway
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=payment-gateway-config
```

### Run Migrations

```bash
php artisan migrate
```

### Configure Environment Variables

Add to your `.env` file:

```env
# Default Gateway
PAYMENT_GATEWAY_DEFAULT=payfast

# PayFast Configuration
PAYFAST_ENABLED=true
PAYFAST_MERCHANT_ID=your_merchant_id
PAYFAST_MERCHANT_KEY=your_merchant_key
PAYFAST_PASSPHRASE=your_passphrase
PAYFAST_TEST_MODE=true

# PayStack Configuration
PAYSTACK_ENABLED=true
PAYSTACK_PUBLIC_KEY=your_public_key
PAYSTACK_SECRET_KEY=your_secret_key
PAYSTACK_MERCHANT_EMAIL=your_email

# PayPal Configuration
PAYPAL_ENABLED=true
PAYPAL_CLIENT_ID=your_client_id
PAYPAL_CLIENT_SECRET=your_client_secret
PAYPAL_MODE=sandbox

# Stripe Configuration
STRIPE_ENABLED=true
STRIPE_PUBLISHABLE_KEY=your_publishable_key
STRIPE_SECRET_KEY=your_secret_key
STRIPE_WEBHOOK_SECRET=your_webhook_secret
```

## 💼 Basic Usage

### Initialize a Payment

```php
use Lisosoft\PaymentGateway\Facades\Payment;

// Initialize payment
$result = Payment::initializePayment('payfast', [
    'amount' => 100.00,
    'currency' => 'ZAR',
    'description' => 'Product Purchase',
    'customer' => [
        'email' => 'customer@example.com',
        'name' => 'John Doe',
        'phone' => '+27123456789',
    ],
    'metadata' => [
        'order_id' => 12345,
        'product_id' => 67890,
    ],
]);

// Redirect user to payment page
return redirect($result['payment_url']);
```

### Verify Payment Status

```php
use Lisosoft\PaymentGateway\Facades\Payment;

$status = Payment::getPaymentStatus('payfast', 'TXN-123456');
if (Payment::isPaymentSuccessful('payfast', 'TXN-123456')) {
    // Payment successful
}
```

### Handle Webhooks

```php
// Webhook routes are automatically registered
// Handle callbacks in your controller
public function handlePayFastWebhook(Request $request)
{
    $result = Payment::processCallback('payfast', $request->all());
    // Process the result
}
```

## 🔧 Advanced Features

### Custom Payment Gateway

Create your own payment gateway by extending the abstract class:

```php
namespace App\PaymentGateways;

use Lisosoft\PaymentGateway\Gateways\AbstractGateway;

class CustomGateway extends AbstractGateway
{
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'api_key' => '',
            'api_secret' => '',
            // ... other configuration
        ];
    }
    
    protected function processInitializePayment(array $paymentData): array
    {
        // Your payment initialization logic
    }
    
    // ... implement other required methods
}
```

### Event Listeners

Listen to payment events for business logic:

```php
// In your EventServiceProvider
protected $listen = [
    \Lisosoft\PaymentGateway\Events\PaymentCompleted::class => [
        \App\Listeners\ProcessOrder::class,
        \App\Listeners\SendConfirmationEmail::class,
    ],
    \Lisosoft\PaymentGateway\Events\PaymentFailed::class => [
        \App\Listeners\NotifyAdmin::class,
        \App\Listeners\RetryPayment::class,
    ],
];
```

### Subscription Management

```php
use Lisosoft\PaymentGateway\Facades\Payment;

// Create subscription
$subscription = Payment::gateway('payfast')->createSubscription([
    'amount' => 99.99,
    'currency' => 'ZAR',
    'description' => 'Monthly Subscription',
    'customer_email' => 'customer@example.com',
    'frequency' => 'monthly',
    'cycles' => 12, // 12 months
]);

// Cancel subscription
Payment::gateway('payfast')->cancelSubscription($subscription['id']);
```

## 📊 Analytics Dashboard

Access payment analytics through the built-in dashboard:

```bash
# Visit the dashboard at
/admin/payments/dashboard
```

## 🛡️ Security Features

- **Encryption**: Sensitive data encryption at rest
- **Signature Verification**: Webhook signature validation
- **Rate Limiting**: Built-in rate limiting for API endpoints
- **IP Whitelisting**: Optional IP whitelisting for webhooks
- **HTTPS Enforcement**: Requires HTTPS for production
- **Data Masking**: Sensitive data masking in logs

## 📈 Performance

- **Caching**: Intelligent caching of gateway configurations
- **Queue Support**: Webhook processing via queues
- **Retry Logic**: Automatic retry for failed payments
- **Bulk Operations**: Efficient batch processing
- **Async Processing**: Non-blocking payment verification

## 🧪 Testing

```bash
# Run tests
composer test

# Generate test coverage
composer test-coverage
```

## 📚 Documentation

Complete documentation is available in the `docs/` directory:

- [Installation Guide](docs/INSTALLATION.md)
- [API Reference](docs/API.md)
- [Deployment Guide](docs/DEPLOYMENT.md)
- [Advanced Usage](docs/ADVANCED.md)

## 🤝 Support

### Get Help

- **GitHub Issues**: [Report bugs or request features](https://github.com/lisosoft/laravel-payment-gateway/issues)
- **Documentation**: Complete API reference and guides
- **Examples**: Real-world integration examples

### Commercial Support

For enterprise support, custom integrations, or consulting services, contact [support@lisosoft.com](mailto:support@lisosoft.com).

## 📄 License

This package is open-source software licensed under the [MIT license](LICENSE).

## 🌟 Why Choose Lisosoft Payment Gateway?

- **Enterprise Ready**: Built for high-volume, mission-critical applications
- **South African Focus**: Optimized for South African payment ecosystems
- **Developer Friendly**: Clean API, comprehensive documentation, and examples
- **Active Maintenance**: Regular updates and security patches
- **Community Support**: Active community and commercial support options

## 🚀 Getting Started with Development

```bash
# Clone the repository
git clone https://github.com/lisosoft/laravel-payment-gateway.git

# Install dependencies
composer install

# Run tests
composer test

# Start development server
php artisan serve
```

## 📞 Contact

- **Website**: [https://lisosoft.com](https://lisosoft.com)
- **Email**: [info@lisosoft.com](mailto:info@lisosoft.com)
- **GitHub**: [lisosoft/laravel-payment-gateway](https://github.com/lisosoft/laravel-payment-gateway)

---

**Lisosoft Payment Gateway** - Powering payments for modern Laravel applications.