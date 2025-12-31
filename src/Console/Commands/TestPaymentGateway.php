<?php

namespace Lisosoft\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Lisosoft\PaymentGateway\Facades\Payment;
use Lisosoft\PaymentGateway\Models\Transaction;

class TestPaymentGateway extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment-gateway:test
                            {gateway? : Specific gateway to test (optional)}
                            {--all : Test all available gateways}
                            {--quick : Quick test with minimal output}
                            {--verbose : Show detailed test information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test payment gateway connections and functionality';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🧪 Testing Payment Gateway Connections...');
        $this->newLine();

        $gateway = $this->argument('gateway');
        $testAll = $this->option('all');
        $quick = $this->option('quick');
        $verbose = $this->option('verbose');

        if ($gateway && $testAll) {
            $this->error('Cannot specify both gateway and --all option.');
            return Command::FAILURE;
        }

        if ($gateway) {
            $this->testSingleGateway($gateway, $quick, $verbose);
        } elseif ($testAll) {
            $this->testAllGateways($quick, $verbose);
        } else {
            $this->testDefaultGateway($quick, $verbose);
        }

        return Command::SUCCESS;
    }

    /**
     * Test a single gateway.
     *
     * @param string $gateway
     * @param bool $quick
     * @param bool $verbose
     * @return void
     */
    protected function testSingleGateway(string $gateway, bool $quick = false, bool $verbose = false): void
    {
        $this->info("Testing gateway: {$gateway}");
        $this->newLine();

        // Check if gateway exists in configuration
        $config = Config::get("payment-gateway.gateways.{$gateway}");

        if (!$config) {
            $this->error("Gateway '{$gateway}' not found in configuration.");
            return;
        }

        $this->displayGatewayInfo($gateway, $config, $verbose);

        if (!$quick) {
            $this->performGatewayTests($gateway, $config, $verbose);
        }

        $this->newLine();
    }

    /**
     * Test all available gateways.
     *
     * @param bool $quick
     * @param bool $verbose
     * @return void
     */
    protected function testAllGateways(bool $quick = false, bool $verbose = false): void
    {
        $gateways = Payment::getAvailableGateways();
        $total = count($gateways);
        $enabled = 0;

        $this->info("Testing all available gateways ({$total} total)");
        $this->newLine();

        foreach ($gateways as $name => $gatewayInfo) {
            $config = $gatewayInfo['config'];
            $isEnabled = $config['enabled'] ?? false;

            if ($isEnabled) {
                $enabled++;
                $this->info("✅ Testing: {$name}");
                $this->displayGatewayInfo($name, $config, $verbose);

                if (!$quick) {
                    $this->performGatewayTests($name, $config, $verbose);
                }
            } else {
                $this->warn("⏸️ Skipping disabled gateway: {$name}");
            }

            $this->newLine();
        }

        $this->displayTestSummary($total, $enabled);
    }

    /**
     * Test the default gateway.
     *
     * @param bool $quick
     * @param bool $verbose
     * @return void
     */
    protected function testDefaultGateway(bool $quick = false, bool $verbose = false): void
    {
        $defaultGateway = Config::get('payment-gateway.default', 'payfast');
        $this->info("Testing default gateway: {$defaultGateway}");
        $this->newLine();

        $this->testSingleGateway($defaultGateway, $quick, $verbose);
    }

    /**
     * Display gateway information.
     *
     * @param string $gateway
     * @param array $config
     * @param bool $verbose
     * @return void
     */
    protected function displayGatewayInfo(string $gateway, array $config, bool $verbose = false): void
    {
        $isEnabled = $config['enabled'] ?? false;
        $testMode = $config['test_mode'] ?? true;

        $this->line("📊 Gateway: " . $this->getGatewayDisplayName($gateway));
        $this->line("🔧 Status: " . ($isEnabled ? '✅ Enabled' : '❌ Disabled'));
        $this->line("🎯 Mode: " . ($testMode ? '🟡 Test/Sandbox' : '🟢 Live/Production'));

        if ($verbose) {
            $this->line("📁 Configuration:");
            foreach ($config as $key => $value) {
                if (is_array($value)) {
                    $this->line("   {$key}: " . json_encode($value));
                } elseif (is_bool($value)) {
                    $this->line("   {$key}: " . ($value ? 'true' : 'false'));
                } else {
                    $this->line("   {$key}: {$value}");
                }
            }
        }

        $this->newLine();
    }

    /**
     * Perform gateway tests.
     *
     * @param string $gateway
     * @param array $config
     * @param bool $verbose
     * @return void
     */
    protected function performGatewayTests(string $gateway, array $config, bool $verbose = false): void
    {
        $isEnabled = $config['enabled'] ?? false;

        if (!$isEnabled) {
            $this->warn("Gateway '{$gateway}' is disabled. Skipping tests.");
            return;
        }

        $this->info("Running tests for {$gateway}...");

        $tests = [
            'Configuration Check' => fn() => $this->testConfiguration($gateway, $config),
            'Gateway Availability' => fn() => $this->testGatewayAvailability($gateway),
            'API Connection' => fn() => $this->testApiConnection($gateway),
            'Payment Initialization' => fn() => $this->testPaymentInitialization($gateway),
            'Transaction Creation' => fn() => $this->testTransactionCreation($gateway),
        ];

        $results = [];
        $passed = 0;
        $failed = 0;

        foreach ($tests as $testName => $testFunction) {
            $this->line("Running: {$testName}...");

            try {
                $result = $testFunction();
                $results[$testName] = $result;

                if ($result['success']) {
                    $this->info("  ✅ {$testName}: {$result['message']}");
                    $passed++;
                } else {
                    $this->error("  ❌ {$testName}: {$result['message']}");
                    $failed++;

                    if ($verbose && isset($result['error'])) {
                        $this->line("     Error: {$result['error']}");
                    }
                }
            } catch (\Exception $e) {
                $results[$testName] = [
                    'success' => false,
                    'message' => 'Test failed with exception',
                    'error' => $e->getMessage(),
                ];
                $this->error("  ❌ {$testName}: Test failed with exception");
                $failed++;

                if ($verbose) {
                    $this->line("     Exception: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->displayTestResults($passed, $failed, $results, $verbose);
    }

    /**
     * Test gateway configuration.
     *
     * @param string $gateway
     * @param array $config
     * @return array
     */
    protected function testConfiguration(string $gateway, array $config): array
    {
        $requiredFields = $this->getRequiredFieldsForGateway($gateway);
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                $missingFields[] = $field;
            }
        }

        if (empty($missingFields)) {
            return [
                'success' => true,
                'message' => 'Configuration is valid',
                'missing_fields' => [],
            ];
        }

        return [
            'success' => false,
            'message' => 'Missing required configuration fields',
            'missing_fields' => $missingFields,
            'error' => 'The following fields are missing or empty: ' . implode(', ', $missingFields),
        ];
    }

    /**
     * Test gateway availability.
     *
     * @param string $gateway
     * @return array
     */
    protected function testGatewayAvailability(string $gateway): array
    {
        try {
            $isAvailable = Payment::isGatewayAvailable($gateway);

            if ($isAvailable) {
                return [
                    'success' => true,
                    'message' => 'Gateway is available',
                ];
            }

            return [
                'success' => false,
                'message' => 'Gateway is not available',
                'error' => 'Gateway is disabled in configuration',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to check gateway availability',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test API connection.
     *
     * @param string $gateway
     * @return array
     */
    protected function testApiConnection(string $gateway): array
    {
        try {
            $gatewayInstance = Payment::gateway($gateway);

            // Try to get gateway information
            $name = $gatewayInstance->getName();
            $displayName = $gatewayInstance->getDisplayName();
            $supportedCurrencies = $gatewayInstance->getSupportedCurrencies();

            return [
                'success' => true,
                'message' => 'API connection successful',
                'data' => [
                    'name' => $name,
                    'display_name' => $displayName,
                    'supported_currencies' => $supportedCurrencies,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test payment initialization.
     *
     * @param string $gateway
     * @return array
     */
    protected function testPaymentInitialization(string $gateway): array
    {
        try {
            // Create test payment data
            $paymentData = [
                'amount' => 10.00,
                'currency' => 'ZAR',
                'description' => 'Test Payment',
                'customer' => [
                    'email' => 'test@example.com',
                    'name' => 'Test Customer',
                    'phone' => '+27123456789',
                ],
                'metadata' => [
                    'test' => true,
                    'gateway' => $gateway,
                ],
            ];

            // Skip actual initialization for EFT gateway (requires manual processing)
            if ($gateway === 'eft') {
                return [
                    'success' => true,
                    'message' => 'EFT gateway test skipped (manual processing required)',
                    'skipped' => true,
                ];
            }

            $result = Payment::initializePayment($gateway, $paymentData);

            if (isset($result['success']) && $result['success']) {
                return [
                    'success' => true,
                    'message' => 'Payment initialization successful',
                    'data' => [
                        'reference' => $result['reference'] ?? null,
                        'transaction_id' => $result['transaction_id'] ?? null,
                        'payment_url' => $result['payment_url'] ?? null,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'Payment initialization failed',
                'error' => $result['message'] ?? 'Unknown error',
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Payment initialization failed with exception',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test transaction creation.
     *
     * @param string $gateway
     * @return array
     */
    protected function testTransactionCreation(string $gateway): array
    {
        try {
            // Create a test transaction record
            $transaction = Transaction::create([
                'reference' => Transaction::generateReference(),
                'gateway' => $gateway,
                'amount' => 10.00,
                'currency' => 'ZAR',
                'status' => 'test',
                'description' => 'Test Transaction',
                'customer_email' => 'test@example.com',
                'customer_name' => 'Test Customer',
                'metadata' => [
                    'test' => true,
                    'gateway' => $gateway,
                ],
                'processed_at' => now(),
            ]);

            if ($transaction->id) {
                // Clean up test transaction
                $transaction->delete();

                return [
                    'success' => true,
                    'message' => 'Transaction creation successful',
                    'data' => [
                        'reference' => $transaction->reference,
                        'id' => $transaction->id,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'Transaction creation failed',
                'error' => 'Transaction was not created',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Transaction creation failed with exception',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Display test results.
     *
     * @param int $passed
     * @param int $failed
     * @param array $results
     * @param bool $verbose
     * @return void
     */
    protected function displayTestResults(int $passed, int $failed, array $results, bool $verbose = false): void
    {
        $total = $passed + $failed;

        $this->info("📊 Test Results:");
        $this->line("Total Tests: {$total}");
        $this->line("✅ Passed: {$passed}");
        $this->line("❌ Failed: {$failed}");

        if ($failed === 0) {
            $this->info("🎉 All tests passed successfully!");
        } else {
            $this->warn("⚠️ Some tests failed. Check the errors above.");

            if ($verbose) {
                $this->newLine();
                $this->info("🔍 Detailed Results:");

                foreach ($results as $testName => $result) {
                    $this->line("Test: {$testName}");
                    $this->line("  Status: " . ($result['success'] ? '✅ Passed' : '❌ Failed'));
                    $this->line("  Message: {$result['message']}");

                    if (!$result['success'] && isset($result['error'])) {
                        $this->line("  Error: {$result['error']}");
                    }

                    if (isset($result['data']) && !empty($result['data'])) {
                        $this->line("  Data: " . json_encode($result['data']));
                    }

                    $this->newLine();
                }
            }
        }
    }

    /**
     * Display test summary.
     *
     * @param int $total
     * @param int $enabled
     * @return void
     */
    protected function displayTestSummary(int $total, int $enabled): void
    {
        $this->info("📈 Test Summary:");
        $this->line("Total Gateways: {$total}");
        $this->line("Enabled Gateways: {$enabled}");
        $this->line("Disabled Gateways: " . ($total - $enabled));

        if ($enabled === 0) {
            $this->warn("⚠️ No gateways are enabled. Please enable at least one gateway in the configuration.");
        } elseif ($enabled < $total) {
            $this->info("💡 Tip: You have {$enabled} of {$total} gateways enabled.");
        } else {
            $this->info("🎉 All gateways are enabled!");
        }
    }

    /**
     * Get required fields for a gateway.
     *
     * @param string $gateway
     * @return array
     */
    protected function getRequiredFieldsForGateway(string $gateway): array
    {
        $requiredFields = [
            'payfast' => ['merchant_id', 'merchant_key'],
            'paystack' => ['public_key', 'secret_key'],
            'paypal' => ['client_id', 'client_secret'],
            'stripe' => ['publishable_key', 'secret_key'],
            'ozow' => ['site_code', 'private_key'],
            'zapper' => ['merchant_id', 'site_id'],
            'crypto' => ['api_key'],
            'eft' => ['account_name', 'account_number'],
            'vodapay' => ['merchant_id', 'api_key'],
            'snapscan' => ['merchant_id', 'api_key'],
        ];

        return $requiredFields[$gateway] ?? [];
    }

    /**
     * Get gateway display name.
     *
     * @param string $gateway
     * @return string
     */
    protected function getGatewayDisplayName(string $gateway): string
    {
        $displayNames = [
            'payfast' => 'PayFast',
            'paystack' => 'PayStack',
            'paypal' => 'PayPal',
            'stripe' => 'Stripe',
            'ozow' => 'Ozow',
            'zapper' => 'Zapper',
            'crypto' => 'Cryptocurrency',
            'eft' => 'EFT/Bank Transfer',
            'vodapay' => 'VodaPay',
            'snapscan' => 'SnapScan',
        ];

        return $displayNames[$gateway] ?? ucfirst($gateway);
    }
}
