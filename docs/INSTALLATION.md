# Lisosoft Payment Gateway - Installation Guide

## Table of Contents
1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Database Setup](#database-setup)
5. [Environment Variables](#environment-variables)
6. [Publishing Assets](#publishing-assets)
7. [Testing Installation](#testing-installation)
8. [Troubleshooting](#troubleshooting)
9. [Upgrading](#upgrading)

## Requirements

### Server Requirements
- PHP 8.1 or higher
- Laravel 10.x or 11.x
- Composer 2.0 or higher
- MySQL 5.7+ / PostgreSQL 9.5+ / SQLite 3.8.8+
- OpenSSL PHP Extension
- PDO PHP Extension
- Mbstring PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
- Ctype PHP Extension
- JSON PHP Extension

### Recommended Server Configuration
```ini
; php.ini recommendations
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 10M
post_max_size = 10M
```

## Installation

### Step 1: Install via Composer

Install the package using Composer:

```bash
composer require lisosoft/laravel-payment-gateway
```

### Step 2: Register Service Provider (Laravel < 11)

For Laravel 10 and below, add the service provider to your `config/app.php`:

```php
'providers' => [
    // ...
    Lisosoft\PaymentGateway\PaymentGatewayServiceProvider::class,
],
```

For Laravel 11, the package will be auto-discovered.

### Step 3: Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=payment-gateway-config
```

This will create `config/payment-gateway.php` with all gateway configurations.

### Step 4: Publish Migrations

Publish the database migrations:

```bash
php artisan vendor:publish --tag=payment-gateway-migrations
```

### Step 5: Run Migrations

Run the migrations to create the necessary tables:

```bash
php artisan migrate
```

### Step 6: Publish Views (Optional)

If you want to customize the views, publish them:

```bash
php artisan vendor:publish --tag=payment-gateway-views
```

## Configuration

### Basic Configuration

Edit `config/payment-gateway.php` to configure your payment gateways:

```php
return [
    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'payfast'),
    
    'gateways' => [
        'payfast' => [
            'enabled' => env('PAYFAST_ENABLED', true),
            'merchant_id' => env('PAYFAST_MERCHANT_ID'),
            'merchant_key' => env('PAYFAST_MERCHANT_KEY'),
            'passphrase' => env('PAYFAST_PASSPHRASE'),
            'test_mode' => env('PAYFAST_TEST_MODE', true),
        ],
        // ... other gateways
    ],
];
```

### Environment Variables

Add the following to your `.env` file:

```env
# Default Gateway
PAYMENT_GATEWAY_DEFAULT=payfast

# PayFast Configuration
PAYFAST_ENABLED=true
PAYFAST_MERCHANT_ID=your_merchant_id
PAYFAST_MERCHANT_KEY=your_merchant_key
PAYFAST_PASSPHRASE=your_passphrase
PAYFAST_TEST_MODE=true
PAYFAST_RETURN_URL=/payment/success
PAYFAST_CANCEL_URL=/payment/cancel
PAYFAST_NOTIFY_URL=/payment/webhook/payfast

# PayStack Configuration
PAYSTACK_ENABLED=true
PAYSTACK_PUBLIC_KEY=your_public_key
PAYSTACK_SECRET_KEY=your_secret_key
PAYSTACK_MERCHANT_EMAIL=your_email
PAYSTACK_TEST_MODE=true
PAYSTACK_CALLBACK_URL=/payment/callback/paystack

# PayPal Configuration
PAYPAL_ENABLED=true
PAYPAL_CLIENT_ID=your_client_id
PAYPAL_CLIENT_SECRET=your_client_secret
PAYPAL_MODE=sandbox
PAYPAL_RETURN_URL=/payment/success
PAYPAL_CANCEL_URL=/payment/cancel
PAYPAL_WEBHOOK_ID=your_webhook_id

# Stripe Configuration
STRIPE_ENABLED=true
STRIPE_PUBLISHABLE_KEY=your_publishable_key
STRIPE_SECRET_KEY=your_secret_key
STRIPE_WEBHOOK_SECRET=your_webhook_secret
STRIPE_TEST_MODE=true
STRIPE_RETURN_URL=/payment/success

# Ozow Configuration
OZOW_ENABLED=true
OZOW_SITE_CODE=your_site_code
OZOW_PRIVATE_KEY=your_private_key
OZOW_API_KEY=your_api_key
OZOW_TEST_MODE=true
OZOW_CALLBACK_URL=/payment/callback/ozow
OZOW_ERROR_URL=/payment/error

# Zapper Configuration
ZAPPER_ENABLED=true
ZAPPER_MERCHANT_ID=your_merchant_id
ZAPPER_SITE_ID=your_site_id
ZAPPER_API_KEY=your_api_key
ZAPPER_TEST_MODE=true
ZAPPER_CALLBACK_URL=/payment/callback/zapper

# Crypto Configuration
CRYPTO_ENABLED=true
CRYPTO_PROVIDER=coinbase
CRYPTO_API_KEY=your_api_key
CRYPTO_API_SECRET=your_api_secret
CRYPTO_WEBHOOK_SECRET=your_webhook_secret

# EFT Configuration
EFT_ENABLED=true
EFT_BANK_NAME="Standard Bank"
EFT_ACCOUNT_NAME="Your Business Name"
EFT_ACCOUNT_NUMBER=1234567890
EFT_BRANCH_CODE=051001
EFT_REFERENCE_PREFIX=LISO
EFT_PAYMENT_WINDOW_HOURS=24

# VodaPay Configuration
VODAPAY_ENABLED=true
VODAPAY_MERCHANT_ID=your_merchant_id
VODAPAY_API_KEY=your_api_key
VODAPAY_TEST_MODE=true
VODAPAY_CALLBACK_URL=/payment/callback/vodapay

# SnapScan Configuration
SNAPSCAN_ENABLED=true
SNAPSCAN_MERCHANT_ID=your_merchant_id
SNAPSCAN_API_KEY=your_api_key
SNAPSCAN_TEST_MODE=true
SNAPSCAN_CALLBACK_URL=/payment/callback/snapscan

# Global Settings
PAYMENT_CURRENCY=ZAR
PAYMENT_TIMEZONE=Africa/Johannesburg
PAYMENT_DECIMAL_PLACES=2
PAYMENT_MINIMUM_AMOUNT=1.00
PAYMENT_MAXIMUM_AMOUNT=1000000.00
PAYMENT_DEFAULT_DESCRIPTION="Payment"

# Webhook Settings
PAYMENT_WEBHOOKS_ENABLED=true
PAYMENT_WEBHOOK_ROUTE_PREFIX=payment/webhook
PAYMENT_WEBHOOK_SIGNATURE_VERIFICATION=true
PAYMENT_WEBHOOK_QUEUE=default
PAYMENT_WEBHOOK_TIMEOUT=30

# Security Settings
PAYMENT_RATE_LIMIT=60
PAYMENT_RATE_LIMIT_PERIOD=1
PAYMENT_IP_WHITELIST=
PAYMENT_REQUIRE_HTTPS=true
PAYMENT_ENCRYPT_SENSITIVE_DATA=true

# Notification Settings
PAYMENT_EMAIL_NOTIFICATIONS=true
PAYMENT_SENDER_EMAIL=noreply@example.com
PAYMENT_SENDER_NAME="Payment System"
PAYMENT_SMS_NOTIFICATIONS=false
PAYMENT_SMS_PROVIDER=twilio
PAYMENT_SLACK_NOTIFICATIONS=false
PAYMENT_SLACK_WEBHOOK_URL=

# Analytics Settings
PAYMENT_ANALYTICS_ENABLED=true
PAYMENT_ANALYTICS_RETENTION_DAYS=365
PAYMENT_DASHBOARD_ENABLED=true
PAYMENT_EXPORT_ENABLED=true

# Recurring Payments
PAYMENT_RECURRING_ENABLED=true
PAYMENT_GRACE_PERIOD_DAYS=3
PAYMENT_RETRY_ATTEMPTS=3
PAYMENT_RETRY_INTERVAL_HOURS=24

# Logging Settings
PAYMENT_LOGGING_ENABLED=true
PAYMENT_LOG_LEVEL=info
PAYMENT_LOG_CHANNEL=stack
PAYMENT_SENSITIVE_DATA_MASKING=true
```

## Database Setup

### Database Tables

The package creates the following tables:

1. **payment_transactions** - Stores all payment transactions
2. **payment_subscriptions** - Stores subscription information
3. **payment_webhook_events** - Stores webhook events for debugging
4. **payment_gateway_logs** - Stores gateway API calls and responses

### Database Schema Overview

```sql
-- Payment Transactions
CREATE TABLE payment_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(255) UNIQUE NOT NULL,
    gateway VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',
    description TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    customer_address TEXT,
    metadata JSON,
    gateway_response JSON,
    gateway_transaction_id VARCHAR(255),
    gateway_reference VARCHAR(255),
    payment_method VARCHAR(50),
    payment_method_details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    webhook_received BOOLEAN DEFAULT FALSE,
    webhook_processed BOOLEAN DEFAULT FALSE,
    webhook_response JSON,
    refunded BOOLEAN DEFAULT FALSE,
    refund_amount DECIMAL(10,2),
    refund_reason TEXT,
    refunded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    INDEX idx_reference (reference),
    INDEX idx_gateway (gateway),
    INDEX idx_status (status),
    INDEX idx_customer_email (customer_email),
    INDEX idx_created_at (created_at)
);

-- Payment Subscriptions
CREATE TABLE payment_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(255) UNIQUE NOT NULL,
    gateway VARCHAR(50) NOT NULL,
    gateway_subscription_id VARCHAR(255),
    gateway_customer_id VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',
    description TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    frequency VARCHAR(20) DEFAULT 'monthly',
    interval INT DEFAULT 1,
    interval_unit VARCHAR(10) DEFAULT 'month',
    billing_cycle VARCHAR(50),
    total_cycles INT,
    cycles_completed INT DEFAULT 0,
    next_billing_date DATE,
    last_billing_date DATE,
    start_date DATE,
    end_date DATE,
    trial_ends_at TIMESTAMP NULL,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    customer_address TEXT,
    metadata JSON,
    gateway_response JSON,
    payment_method VARCHAR(50),
    payment_method_details JSON,
    auto_renew BOOLEAN DEFAULT TRUE,
    grace_period_days INT DEFAULT 3,
    retry_count INT DEFAULT 0,
    max_retries INT DEFAULT 3,
    cancelled_at TIMESTAMP NULL,
    cancelled_by VARCHAR(50),
    cancellation_reason TEXT,
    paused_at TIMESTAMP NULL,
    resumed_at TIMESTAMP NULL,
    total_paid DECIMAL(10,2) DEFAULT 0,
    total_attempts INT DEFAULT 0,
    successful_payments INT DEFAULT 0,
    failed_payments INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reference (reference),
    INDEX idx_gateway (gateway),
    INDEX idx_status (status),
    INDEX idx_customer_email (customer_email),
    INDEX idx_next_billing_date (next_billing_date)
);
```

### Database Seeding (Optional)

Create a seeder for testing data:

```bash
php artisan make:seeder PaymentGatewaySeeder
```

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lisosoft\PaymentGateway\Models\Transaction;
use Lisosoft\PaymentGateway\Models\Subscription;

class PaymentGatewaySeeder extends Seeder
{
    public function run()
    {
        // Create test transactions
        Transaction::create([
            'reference' => 'TEST-' . uniqid(),
            'gateway' => 'payfast',
            'amount' => 100.00,
            'currency' => 'ZAR',
            'description' => 'Test Transaction',
            'status' => 'completed',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'customer_phone' => '+27123456789',
        ]);

        // Create test subscription
        Subscription::create([
            'reference' => 'SUB-' . uniqid(),
            'gateway' => 'payfast',
            'amount' => 99.99,
            'currency' => 'ZAR',
            'description' => 'Test Subscription',
            'status' => 'active',
            'frequency' => 'monthly',
            'total_cycles' => 12,
            'next_billing_date' => now()->addMonth(),
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'auto_renew' => true,
        ]);
    }
}
```

Run the seeder:

```bash
php artisan db:seed --class=PaymentGatewaySeeder
```

## Publishing Assets

### Routes

The package automatically registers API and web routes. You can view the routes with:

```bash
php artisan route:list --name=payment
```

### Views

Customize published views:

```bash
php artisan vendor:publish --tag=payment-gateway-views --force
```

This will publish views to `resources/views/vendor/payment-gateway/`.

### Translations (Optional)

If you need translations, publish them:

```bash
php artisan vendor:publish --tag=payment-gateway-translations
```

## Testing Installation

### Step 1: Run Installation Command

Use the built-in installation command to verify setup:

```bash
php artisan payment:install
```

This command will:
1. Check PHP version and extensions
2. Verify Laravel version compatibility
3. Check database connection
4. Verify configuration files
5. Test gateway connections (optional)

### Step 2: Test Gateway Connection

Test a specific gateway connection:

```bash
php artisan payment:test payfast
```

Or test all gateways:

```bash
php artisan payment:test --all
```

### Step 3: Create Test Payment

Create a test payment to verify everything works:

```bash
php artisan payment:test-transaction --gateway=payfast --amount=1.00
```

### Step 4: Check System Status

View system status:

```bash
php artisan payment:status
```

## Troubleshooting

### Common Issues

#### 1. "Class not found" errors
```bash
composer dump-autoload
php artisan optimize:clear
```

#### 2. Database migration errors
```bash
php artisan migrate:fresh
php artisan migrate:status
```

#### 3. Gateway connection errors
- Verify API keys and credentials
- Check internet connectivity
- Ensure test mode is enabled for testing
- Check gateway-specific requirements

#### 4. Webhook issues
- Ensure your application is publicly accessible
- Verify webhook URLs are correct
- Check signature verification settings
- Monitor webhook logs

### Debug Mode

Enable debug mode for troubleshooting:

```env
APP_DEBUG=true
PAYMENT_LOG_LEVEL=debug
```

Check logs:
```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/payment-gateway.log
```

### Support

If you encounter issues:
1. Check the [GitHub Issues](https://github.com/lisosoft/laravel-payment-gateway/issues)
2. Review the documentation
3. Contact support: support@lisosoft.com

## Upgrading

### From Previous Versions

#### Backup First
```bash
# Backup database
php artisan backup:run

# Backup configuration
cp config/payment-gateway.php config/payment-gateway.php.backup
```

#### Update Package
```bash
composer update lisosoft/laravel-payment-gateway
```

#### Run Migrations
```bash
php artisan migrate
```

#### Clear Cache
```bash
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### Verify Upgrade
```bash
php artisan payment:status
php artisan payment:test --all
```

### Version Compatibility

| Package Version | Laravel Version | PHP Version |
|----------------|-----------------|-------------|
| 1.x            | 10.x, 11.x      | 8.1+        |
| 0.x            | 9.x, 10.x       | 8.0+        |

### Breaking Changes

Check the [CHANGELOG.md](CHANGELOG.md) for breaking changes between versions.

## Next Steps

After successful installation:

1. **Configure your payment gateways** with real API credentials
2. **Set up webhooks** for production use
3. **Implement payment flows** in your application
4. **Test thoroughly** in sandbox/staging environment
5. **Monitor transactions** using the admin dashboard
6. **Set up notifications** for payment events

## Additional Resources

- [API Documentation](API.md)
- [Advanced Usage Guide](ADVANCED.md)
- [Deployment Guide](DEPLOYMENT.md)
- [Security Best Practices](SECURITY.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)

---

**Need Help?**
- Documentation: [https://docs.lisosoft.com/payment-gateway](https://docs.lisosoft.com/payment-gateway)
- GitHub: [https://github.com/lisosoft/laravel-payment-gateway](https://github.com/lisosoft/laravel-payment-gateway)
- Support: support@lisosoft.com
- Commercial Support: enterprise@lisosoft.com