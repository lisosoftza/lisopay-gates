<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway that gets used when
    | processing payments. You can change this to any of the supported
    | gateways: 'payfast', 'paystack', 'paypal', 'stripe', 'ozow', etc.
    |
    */

    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'payfast'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for each payment gateway.
    | Make sure to set the appropriate credentials for each gateway
    | you plan to use in your application.
    |
    */

    'gateways' => [

        'payfast' => [
            'enabled' => env('PAYFAST_ENABLED', true),
            'merchant_id' => env('PAYFAST_MERCHANT_ID'),
            'merchant_key' => env('PAYFAST_MERCHANT_KEY'),
            'passphrase' => env('PAYFAST_PASSPHRASE'),
            'test_mode' => env('PAYFAST_TEST_MODE', true),
            'return_url' => env('PAYFAST_RETURN_URL', '/payment/success'),
            'cancel_url' => env('PAYFAST_CANCEL_URL', '/payment/cancel'),
            'notify_url' => env('PAYFAST_NOTIFY_URL', '/payment/webhook/payfast'),
        ],

        'paystack' => [
            'enabled' => env('PAYSTACK_ENABLED', true),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL'),
            'test_mode' => env('PAYSTACK_TEST_MODE', true),
            'callback_url' => env('PAYSTACK_CALLBACK_URL', '/payment/callback/paystack'),
        ],

        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', true),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'return_url' => env('PAYPAL_RETURN_URL', '/payment/success'),
            'cancel_url' => env('PAYPAL_CANCEL_URL', '/payment/cancel'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        ],

        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', true),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'test_mode' => env('STRIPE_TEST_MODE', true),
            'return_url' => env('STRIPE_RETURN_URL', '/payment/success'),
        ],

        'ozow' => [
            'enabled' => env('OZOW_ENABLED', true),
            'site_code' => env('OZOW_SITE_CODE'),
            'private_key' => env('OZOW_PRIVATE_KEY'),
            'api_key' => env('OZOW_API_KEY'),
            'test_mode' => env('OZOW_TEST_MODE', true),
            'callback_url' => env('OZOW_CALLBACK_URL', '/payment/callback/ozow'),
            'error_url' => env('OZOW_ERROR_URL', '/payment/error'),
        ],

        'zapper' => [
            'enabled' => env('ZAPPER_ENABLED', true),
            'merchant_id' => env('ZAPPER_MERCHANT_ID'),
            'site_id' => env('ZAPPER_SITE_ID'),
            'api_key' => env('ZAPPER_API_KEY'),
            'test_mode' => env('ZAPPER_TEST_MODE', true),
            'callback_url' => env('ZAPPER_CALLBACK_URL', '/payment/callback/zapper'),
        ],

        'crypto' => [
            'enabled' => env('CRYPTO_ENABLED', true),
            'provider' => env('CRYPTO_PROVIDER', 'coinbase'),
            'api_key' => env('CRYPTO_API_KEY'),
            'api_secret' => env('CRYPTO_API_SECRET'),
            'webhook_secret' => env('CRYPTO_WEBHOOK_SECRET'),
            'currencies' => ['BTC', 'ETH', 'USDT', 'USDC'],
        ],

        'eft' => [
            'enabled' => env('EFT_ENABLED', true),
            'bank_name' => env('EFT_BANK_NAME', 'Standard Bank'),
            'account_name' => env('EFT_ACCOUNT_NAME'),
            'account_number' => env('EFT_ACCOUNT_NUMBER'),
            'branch_code' => env('EFT_BRANCH_CODE'),
            'reference_prefix' => env('EFT_REFERENCE_PREFIX', 'LISO'),
            'payment_window_hours' => env('EFT_PAYMENT_WINDOW_HOURS', 24),
        ],

        'vodapay' => [
            'enabled' => env('VODAPAY_ENABLED', true),
            'merchant_id' => env('VODAPAY_MERCHANT_ID'),
            'api_key' => env('VODAPAY_API_KEY'),
            'test_mode' => env('VODAPAY_TEST_MODE', true),
            'callback_url' => env('VODAPAY_CALLBACK_URL', '/payment/callback/vodapay'),
        ],

        'snapscan' => [
            'enabled' => env('SNAPSCAN_ENABLED', true),
            'merchant_id' => env('SNAPSCAN_MERCHANT_ID'),
            'api_key' => env('SNAPSCAN_API_KEY'),
            'test_mode' => env('SNAPSCAN_TEST_MODE', true),
            'callback_url' => env('SNAPSCAN_CALLBACK_URL', '/payment/callback/snapscan'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Settings
    |--------------------------------------------------------------------------
    |
    | Global transaction settings that apply to all payment gateways.
    |
    */

    'transaction' => [
        'currency' => env('PAYMENT_CURRENCY', 'ZAR'),
        'timezone' => env('PAYMENT_TIMEZONE', 'Africa/Johannesburg'),
        'decimal_places' => env('PAYMENT_DECIMAL_PLACES', 2),
        'minimum_amount' => env('PAYMENT_MINIMUM_AMOUNT', 1.00),
        'maximum_amount' => env('PAYMENT_MAXIMUM_AMOUNT', 1000000.00),
        'default_description' => env('PAYMENT_DEFAULT_DESCRIPTION', 'Payment'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for payment gateway webhooks.
    |
    */

    'webhooks' => [
        'enabled' => env('PAYMENT_WEBHOOKS_ENABLED', true),
        'route_prefix' => env('PAYMENT_WEBHOOK_ROUTE_PREFIX', 'payment/webhook'),
        'signature_verification' => env('PAYMENT_WEBHOOK_SIGNATURE_VERIFICATION', true),
        'queue' => env('PAYMENT_WEBHOOK_QUEUE', 'default'),
        'timeout' => env('PAYMENT_WEBHOOK_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration for payment processing.
    |
    */

    'security' => [
        'rate_limit' => env('PAYMENT_RATE_LIMIT', 60),
        'rate_limit_period' => env('PAYMENT_RATE_LIMIT_PERIOD', 1),
        'ip_whitelist' => explode(',', env('PAYMENT_IP_WHITELIST', '')),
        'require_https' => env('PAYMENT_REQUIRE_HTTPS', true),
        'encrypt_sensitive_data' => env('PAYMENT_ENCRYPT_SENSITIVE_DATA', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for payment notifications.
    |
    */

    'notifications' => [
        'email' => [
            'enabled' => env('PAYMENT_EMAIL_NOTIFICATIONS', true),
            'sender_email' => env('PAYMENT_SENDER_EMAIL', 'noreply@example.com'),
            'sender_name' => env('PAYMENT_SENDER_NAME', 'Payment System'),
        ],
        'sms' => [
            'enabled' => env('PAYMENT_SMS_NOTIFICATIONS', false),
            'provider' => env('PAYMENT_SMS_PROVIDER', 'twilio'),
        ],
        'slack' => [
            'enabled' => env('PAYMENT_SLACK_NOTIFICATIONS', false),
            'webhook_url' => env('PAYMENT_SLACK_WEBHOOK_URL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics & Reporting
    |--------------------------------------------------------------------------
    |
    | Configuration for payment analytics and reporting.
    |
    */

    'analytics' => [
        'enabled' => env('PAYMENT_ANALYTICS_ENABLED', true),
        'retention_days' => env('PAYMENT_ANALYTICS_RETENTION_DAYS', 365),
        'dashboard_enabled' => env('PAYMENT_DASHBOARD_ENABLED', true),
        'export_enabled' => env('PAYMENT_EXPORT_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recurring Payments
    |--------------------------------------------------------------------------
    |
    | Configuration for recurring/subscription payments.
    |
    */

    'recurring' => [
        'enabled' => env('PAYMENT_RECURRING_ENABLED', true),
        'grace_period_days' => env('PAYMENT_GRACE_PERIOD_DAYS', 3),
        'retry_attempts' => env('PAYMENT_RETRY_ATTEMPTS', 3),
        'retry_interval_hours' => env('PAYMENT_RETRY_INTERVAL_HOURS', 24),
        'webhook_events' => [
            'subscription_created',
            'subscription_updated',
            'subscription_cancelled',
            'payment_succeeded',
            'payment_failed',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for payment logging.
    |
    */

    'logging' => [
        'enabled' => env('PAYMENT_LOGGING_ENABLED', true),
        'level' => env('PAYMENT_LOG_LEVEL', 'info'),
        'channel' => env('PAYMENT_LOG_CHANNEL', 'stack'),
        'sensitive_data_masking' => env('PAYMENT_SENSITIVE_DATA_MASKING', true),
    ],

];
