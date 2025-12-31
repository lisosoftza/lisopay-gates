<?php

namespace Lisosoft\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class InstallPaymentGateway extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment-gateway:install
                            {--force : Force overwrite existing files}
                            {--no-migrations : Skip running migrations}
                            {--no-config : Skip publishing configuration}
                            {--no-views : Skip publishing views}
                            {--quick : Quick install with default options}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Lisosoft Payment Gateway package';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🚀 Installing Lisosoft Payment Gateway...');
        $this->newLine();

        // Check if installation is forced
        $force = $this->option('force');
        $quick = $this->option('quick');

        // Display installation options
        if (!$quick) {
            $this->displayInstallationOptions();
        }

        // Publish configuration
        if (!$this->option('no-config') || $quick) {
            $this->publishConfiguration($force);
        }

        // Publish migrations
        if (!$this->option('no-migrations') || $quick) {
            $this->publishMigrations($force);
        }

        // Publish views
        if (!$this->option('no-views') || $quick) {
            $this->publishViews($force);
        }

        // Run migrations
        if (!$this->option('no-migrations') || $quick) {
            $this->runMigrations();
        }

        // Create environment variables
        $this->setupEnvironmentVariables();

        // Display completion message
        $this->displayCompletionMessage();

        return Command::SUCCESS;
    }

    /**
     * Display installation options.
     *
     * @return void
     */
    protected function displayInstallationOptions(): void
    {
        $this->info('📦 Installation Options:');
        $this->line('1. Publish configuration files');
        $this->line('2. Publish database migrations');
        $this->line('3. Publish view files');
        $this->line('4. Run database migrations');
        $this->line('5. Setup environment variables');
        $this->newLine();

        if (!$this->confirm('Do you want to proceed with the installation?', true)) {
            $this->error('Installation cancelled.');
            exit(Command::FAILURE);
        }
    }

    /**
     * Publish configuration files.
     *
     * @param bool $force
     * @return void
     */
    protected function publishConfiguration(bool $force = false): void
    {
        $this->info('📝 Publishing configuration files...');

        $params = ['--provider' => 'Lisosoft\\PaymentGateway\\PaymentGatewayServiceProvider', '--tag' => 'payment-gateway-config'];

        if ($force) {
            $params['--force'] = true;
        }

        try {
            Artisan::call('vendor:publish', $params);
            $this->info('✅ Configuration files published successfully.');
        } catch (\Exception $e) {
            $this->error('❌ Failed to publish configuration files: ' . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * Publish migration files.
     *
     * @param bool $force
     * @return void
     */
    protected function publishMigrations(bool $force = false): void
    {
        $this->info('🗄️ Publishing migration files...');

        $params = ['--provider' => 'Lisosoft\\PaymentGateway\\PaymentGatewayServiceProvider', '--tag' => 'payment-gateway-migrations'];

        if ($force) {
            $params['--force'] = true;
        }

        try {
            Artisan::call('vendor:publish', $params);
            $this->info('✅ Migration files published successfully.');
        } catch (\Exception $e) {
            $this->error('❌ Failed to publish migration files: ' . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * Publish view files.
     *
     * @param bool $force
     * @return void
     */
    protected function publishViews(bool $force = false): void
    {
        $this->info('🎨 Publishing view files...');

        $params = ['--provider' => 'Lisosoft\\PaymentGateway\\PaymentGatewayServiceProvider', '--tag' => 'payment-gateway-views'];

        if ($force) {
            $params['--force'] = true;
        }

        try {
            Artisan::call('vendor:publish', $params);
            $this->info('✅ View files published successfully.');
        } catch (\Exception $e) {
            $this->error('❌ Failed to publish view files: ' . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * Run database migrations.
     *
     * @return void
     */
    protected function runMigrations(): void
    {
        $this->info('⚙️ Running database migrations...');

        if ($this->confirm('Do you want to run the database migrations now?', true)) {
            try {
                Artisan::call('migrate');
                $this->info('✅ Database migrations completed successfully.');
            } catch (\Exception $e) {
                $this->error('❌ Failed to run migrations: ' . $e->getMessage());
                $this->warn('You can run migrations manually with: php artisan migrate');
            }
        } else {
            $this->warn('⚠️ Skipping database migrations.');
            $this->line('You can run migrations later with: php artisan migrate');
        }

        $this->newLine();
    }

    /**
     * Setup environment variables.
     *
     * @return void
     */
    protected function setupEnvironmentVariables(): void
    {
        $this->info('🔧 Setting up environment variables...');

        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        if (!File::exists($envPath)) {
            $this->error('❌ .env file not found.');
            return;
        }

        // Read current .env file
        $envContent = File::get($envPath);
        $newVariables = [];

        // Payment Gateway Configuration
        $paymentVariables = [
            'PAYMENT_GATEWAY_DEFAULT' => 'payfast',
            'PAYMENT_CURRENCY' => 'ZAR',
            'PAYMENT_TIMEZONE' => 'Africa/Johannesburg',
            'PAYMENT_DECIMAL_PLACES' => '2',
            'PAYMENT_MINIMUM_AMOUNT' => '1.00',
            'PAYMENT_MAXIMUM_AMOUNT' => '1000000.00',
            'PAYMENT_DEFAULT_DESCRIPTION' => 'Payment',
            'PAYMENT_WEBHOOKS_ENABLED' => 'true',
            'PAYMENT_WEBHOOK_ROUTE_PREFIX' => 'payment/webhook',
            'PAYMENT_WEBHOOK_SIGNATURE_VERIFICATION' => 'true',
            'PAYMENT_WEBHOOK_QUEUE' => 'default',
            'PAYMENT_WEBHOOK_TIMEOUT' => '30',
            'PAYMENT_RATE_LIMIT' => '60',
            'PAYMENT_RATE_LIMIT_PERIOD' => '1',
            'PAYMENT_IP_WHITELIST' => '',
            'PAYMENT_REQUIRE_HTTPS' => 'true',
            'PAYMENT_ENCRYPT_SENSITIVE_DATA' => 'true',
            'PAYMENT_EMAIL_NOTIFICATIONS' => 'true',
            'PAYMENT_SENDER_EMAIL' => 'noreply@example.com',
            'PAYMENT_SENDER_NAME' => 'Payment System',
            'PAYMENT_SMS_NOTIFICATIONS' => 'false',
            'PAYMENT_SMS_PROVIDER' => 'twilio',
            'PAYMENT_SLACK_NOTIFICATIONS' => 'false',
            'PAYMENT_ANALYTICS_ENABLED' => 'true',
            'PAYMENT_ANALYTICS_RETENTION_DAYS' => '365',
            'PAYMENT_DASHBOARD_ENABLED' => 'true',
            'PAYMENT_EXPORT_ENABLED' => 'true',
            'PAYMENT_RECURRING_ENABLED' => 'true',
            'PAYMENT_GRACE_PERIOD_DAYS' => '3',
            'PAYMENT_RETRY_ATTEMPTS' => '3',
            'PAYMENT_RETRY_INTERVAL_HOURS' => '24',
            'PAYMENT_LOGGING_ENABLED' => 'true',
            'PAYMENT_LOG_LEVEL' => 'info',
            'PAYMENT_LOG_CHANNEL' => 'stack',
            'PAYMENT_SENSITIVE_DATA_MASKING' => 'true',
        ];

        // Gateway-specific variables
        $gatewayVariables = [
            // PayFast
            'PAYFAST_ENABLED' => 'true',
            'PAYFAST_MERCHANT_ID' => '',
            'PAYFAST_MERCHANT_KEY' => '',
            'PAYFAST_PASSPHRASE' => '',
            'PAYFAST_TEST_MODE' => 'true',
            'PAYFAST_RETURN_URL' => '/payment/success',
            'PAYFAST_CANCEL_URL' => '/payment/cancel',
            'PAYFAST_NOTIFY_URL' => '/payment/webhook/payfast',

            // PayStack
            'PAYSTACK_ENABLED' => 'true',
            'PAYSTACK_PUBLIC_KEY' => '',
            'PAYSTACK_SECRET_KEY' => '',
            'PAYSTACK_MERCHANT_EMAIL' => '',
            'PAYSTACK_TEST_MODE' => 'true',
            'PAYSTACK_CALLBACK_URL' => '/payment/callback/paystack',

            // PayPal
            'PAYPAL_ENABLED' => 'true',
            'PAYPAL_CLIENT_ID' => '',
            'PAYPAL_CLIENT_SECRET' => '',
            'PAYPAL_MODE' => 'sandbox',
            'PAYPAL_RETURN_URL' => '/payment/success',
            'PAYPAL_CANCEL_URL' => '/payment/cancel',
            'PAYPAL_WEBHOOK_ID' => '',

            // Stripe
            'STRIPE_ENABLED' => 'true',
            'STRIPE_PUBLISHABLE_KEY' => '',
            'STRIPE_SECRET_KEY' => '',
            'STRIPE_WEBHOOK_SECRET' => '',
            'STRIPE_TEST_MODE' => 'true',
            'STRIPE_RETURN_URL' => '/payment/success',

            // Ozow
            'OZOW_ENABLED' => 'true',
            'OZOW_SITE_CODE' => '',
            'OZOW_PRIVATE_KEY' => '',
            'OZOW_API_KEY' => '',
            'OZOW_TEST_MODE' => 'true',
            'OZOW_CALLBACK_URL' => '/payment/callback/ozow',
            'OZOW_ERROR_URL' => '/payment/error',

            // Zapper
            'ZAPPER_ENABLED' => 'true',
            'ZAPPER_MERCHANT_ID' => '',
            'ZAPPER_SITE_ID' => '',
            'ZAPPER_API_KEY' => '',
            'ZAPPER_TEST_MODE' => 'true',
            'ZAPPER_CALLBACK_URL' => '/payment/callback/zapper',

            // Crypto
            'CRYPTO_ENABLED' => 'true',
            'CRYPTO_PROVIDER' => 'coinbase',
            'CRYPTO_API_KEY' => '',
            'CRYPTO_API_SECRET' => '',
            'CRYPTO_WEBHOOK_SECRET' => '',

            // EFT
            'EFT_ENABLED' => 'true',
            'EFT_BANK_NAME' => 'Standard Bank',
            'EFT_ACCOUNT_NAME' => '',
            'EFT_ACCOUNT_NUMBER' => '',
            'EFT_BRANCH_CODE' => '',
            'EFT_REFERENCE_PREFIX' => 'LISO',
            'EFT_PAYMENT_WINDOW_HOURS' => '24',

            // VodaPay
            'VODAPAY_ENABLED' => 'true',
            'VODAPAY_MERCHANT_ID' => '',
            'VODAPAY_API_KEY' => '',
            'VODAPAY_TEST_MODE' => 'true',
            'VODAPAY_CALLBACK_URL' => '/payment/callback/vodapay',

            // SnapScan
            'SNAPSCAN_ENABLED' => 'true',
            'SNAPSCAN_MERCHANT_ID' => '',
            'SNAPSCAN_API_KEY' => '',
            'SNAPSCAN_TEST_MODE' => 'true',
            'SNAPSCAN_CALLBACK_URL' => '/payment/callback/snapscan',
        ];

        // Combine all variables
        $allVariables = array_merge($paymentVariables, $gatewayVariables);

        // Check which variables are missing
        foreach ($allVariables as $key => $defaultValue) {
            if (!str_contains($envContent, $key . '=')) {
                $newVariables[$key] = $defaultValue;
            }
        }

        // Add new variables to .env file
        if (!empty($newVariables)) {
            $envContent .= "\n\n# Payment Gateway Configuration\n";
            foreach ($newVariables as $key => $value) {
                $envContent .= "{$key}={$value}\n";
                $this->line("Added: {$key}={$value}");
            }

            File::put($envPath, $envContent);
            $this->info('✅ Environment variables added successfully.');
        } else {
            $this->info('✅ All environment variables are already configured.');
        }

        $this->newLine();
    }

    /**
     * Display completion message.
     *
     * @return void
     */
    protected function displayCompletionMessage(): void
    {
        $this->info('🎉 Lisosoft Payment Gateway installed successfully!');
        $this->newLine();

        $this->info('📋 Next Steps:');
        $this->line('1. Configure your payment gateway credentials in the .env file');
        $this->line('2. Review the configuration file at config/payment-gateway.php');
        $this->line('3. Test the payment gateway with: php artisan payment-gateway:test');
        $this->line('4. Visit the admin dashboard at /admin/payments/dashboard');
        $this->newLine();

        $this->info('🔧 Available Commands:');
        $this->line('• php artisan payment-gateway:install    - Install/update the package');
        $this->line('• php artisan payment-gateway:test       - Test payment gateway connections');
        $this->line('• php artisan payment-gateway:transactions - List recent transactions');
        $this->line('• php artisan payment-gateway:process-recurring - Process recurring payments');
        $this->newLine();

        $this->info('📚 Documentation:');
        $this->line('• Complete documentation: https://github.com/lisosoft/laravel-payment-gateway');
        $this->line('• API Reference: https://github.com/lisosoft/laravel-payment-gateway/docs/API.md');
        $this->newLine();

        $this->info('💼 Support:');
        $this->line('• Email: support@lisosoft.com');
        $this->line('• GitHub Issues: https://github.com/lisosoft/laravel-payment-gateway/issues');
        $this->newLine();

        $this->info('🌟 Thank you for choosing Lisosoft Payment Gateway!');
    }
}
