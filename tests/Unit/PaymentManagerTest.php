<?php

namespace Lisosoft\PaymentGateway\Tests\Unit;

use Lisosoft\PaymentGateway\Tests\TestCase;
use Lisosoft\PaymentGateway\Services\PaymentManager;
use Lisosoft\PaymentGateway\Gateways\AbstractGateway;
use Mockery;

class PaymentManagerTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated()
    {
        $manager = app(PaymentManager::class);

        $this->assertInstanceOf(PaymentManager::class, $manager);
    }

    /** @test */
    public function it_can_get_default_gateway()
    {
        $manager = app(PaymentManager::class);

        $gateway = $manager->gateway();

        $this->assertInstanceOf(AbstractGateway::class, $gateway);
        $this->assertEquals('payfast', $gateway->getGatewayCode());
    }

    /** @test */
    public function it_can_get_specific_gateway()
    {
        $manager = app(PaymentManager::class);

        $gateway = $manager->gateway('paystack');

        $this->assertInstanceOf(AbstractGateway::class, $gateway);
        $this->assertEquals('paystack', $gateway->getGatewayCode());
    }

    /** @test */
    public function it_throws_exception_for_invalid_gateway()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment gateway [invalid_gateway] is not supported.');

        $manager = app(PaymentManager::class);
        $manager->gateway('invalid_gateway');
    }

    /** @test */
    public function it_throws_exception_for_disabled_gateway()
    {
        config(['payment-gateway.gateways.payfast.enabled' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment gateway [payfast] is disabled.');

        $manager = app(PaymentManager::class);
        $manager->gateway('payfast');
    }

    /** @test */
    public function it_can_get_all_gateways()
    {
        $manager = app(PaymentManager::class);

        $gateways = $manager->getGateways();

        $this->assertIsArray($gateways);
        $this->assertNotEmpty($gateways);

        foreach ($gateways as $gateway) {
            $this->assertInstanceOf(AbstractGateway::class, $gateway);
        }
    }

    /** @test */
    public function it_can_get_enabled_gateways()
    {
        $manager = app(PaymentManager::class);

        $gateways = $manager->getEnabledGateways();

        $this->assertIsArray($gateways);

        foreach ($gateways as $gateway) {
            $this->assertTrue($gateway->isEnabled());
        }
    }

    /** @test */
    public function it_can_check_if_gateway_exists()
    {
        $manager = app(PaymentManager::class);

        $this->assertTrue($manager->hasGateway('payfast'));
        $this->assertTrue($manager->hasGateway('paystack'));
        $this->assertFalse($manager->hasGateway('invalid_gateway'));
    }

    /** @test */
    public function it_can_check_if_gateway_is_enabled()
    {
        $manager = app(PaymentManager::class);

        $this->assertTrue($manager->isGatewayEnabled('payfast'));

        config(['payment-gateway.gateways.payfast.enabled' => false]);

        $manager = app(PaymentManager::class);
        $this->assertFalse($manager->isGatewayEnabled('payfast'));
    }

    /** @test */
    public function it_can_initialize_payment()
    {
        $manager = app(PaymentManager::class);

        $paymentData = [
            'amount' => 100.00,
            'currency' => 'ZAR',
            'description' => 'Test Payment',
            'customer' => [
                'email' => 'test@example.com',
                'name' => 'Test Customer',
            ],
        ];

        $result = $manager->initializePayment('payfast', $paymentData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('data', $result);
    }

    /** @test */
    public function it_can_verify_payment()
    {
        $manager = app(PaymentManager::class);

        $transaction = $this->createTransaction([
            'gateway_transaction_id' => 'TEST123',
            'status' => 'pending',
        ]);

        $result = $manager->verifyPayment('payfast', $transaction->reference);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('status', $result);
    }

    /** @test */
    public function it_can_process_callback()
    {
        $manager = app(PaymentManager::class);

        $callbackData = [
            'payment_status' => 'COMPLETE',
            'pt' => 'TEST123',
            'amount' => '100.00',
        ];

        $result = $manager->processCallback('payfast', $callbackData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('transaction', $result);
    }

    /** @test */
    public function it_can_get_payment_status()
    {
        $manager = app(PaymentManager::class);

        $transaction = $this->createTransaction([
            'gateway_transaction_id' => 'TEST123',
            'status' => 'completed',
        ]);

        $status = $manager->getPaymentStatus('payfast', $transaction->reference);

        $this->assertIsString($status);
        $this->assertEquals('completed', $status);
    }

    /** @test */
    public function it_can_check_if_payment_is_successful()
    {
        $manager = app(PaymentManager::class);

        $transaction = $this->createTransaction([
            'gateway_transaction_id' => 'TEST123',
            'status' => 'completed',
        ]);

        $isSuccessful = $manager->isPaymentSuccessful('payfast', $transaction->reference);

        $this->assertIsBool($isSuccessful);
        $this->assertTrue($isSuccessful);
    }

    /** @test */
    public function it_can_refund_payment()
    {
        $manager = app(PaymentManager::class);

        $transaction = $this->createTransaction([
            'gateway_transaction_id' => 'TEST123',
            'status' => 'completed',
            'amount' => 100.00,
        ]);

        $result = $manager->refundPayment('payfast', $transaction->reference, 50.00, 'Partial refund');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('refund_id', $result);
    }

    /** @test */
    public function it_can_create_subscription()
    {
        $manager = app(PaymentManager::class);

        $subscriptionData = [
            'amount' => 99.99,
            'currency' => 'ZAR',
            'description' => 'Test Subscription',
            'customer_email' => 'test@example.com',
            'frequency' => 'monthly',
            'cycles' => 12,
        ];

        $result = $manager->createSubscription('payfast', $subscriptionData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('subscription_id', $result);
    }

    /** @test */
    public function it_can_cancel_subscription()
    {
        $manager = app(PaymentManager::class);

        $subscription = $this->createSubscription([
            'gateway_subscription_id' => 'SUB123',
            'status' => 'active',
        ]);

        $result = $manager->cancelSubscription('payfast', $subscription->reference);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('cancelled', $result);
    }

    /** @test */
    public function it_can_get_gateway_config()
    {
        $manager = app(PaymentManager::class);

        $config = $manager->getGatewayConfig('payfast');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('merchant_id', $config);
        $this->assertArrayHasKey('merchant_key', $config);
        $this->assertArrayHasKey('test_mode', $config);
    }

    /** @test */
    public function it_can_update_gateway_config()
    {
        $manager = app(PaymentManager::class);

        $newConfig = [
            'enabled' => true,
            'test_mode' => false,
            'merchant_id' => 'new_merchant_id',
        ];

        $result = $manager->updateGatewayConfig('payfast', $newConfig);

        $this->assertTrue($result);

        $updatedConfig = $manager->getGatewayConfig('payfast');
        $this->assertEquals('new_merchant_id', $updatedConfig['merchant_id']);
        $this->assertFalse($updatedConfig['test_mode']);
    }

    /** @test */
    public function it_can_validate_payment_data()
    {
        $manager = app(PaymentManager::class);

        $validData = [
            'amount' => 100.00,
            'currency' => 'ZAR',
            'description' => 'Test Payment',
            'customer' => [
                'email' => 'test@example.com',
            ],
        ];

        $this->assertTrue($manager->validatePaymentData($validData));

        $invalidData = [
            'amount' => 0, // Invalid amount
        ];

        $this->assertFalse($manager->validatePaymentData($invalidData));
    }

    /** @test */
    public function it_can_generate_reference()
    {
        $manager = app(PaymentManager::class);

        $reference1 = $manager->generateReference();
        $reference2 = $manager->generateReference();

        $this->assertIsString($reference1);
        $this->assertIsString($reference2);
        $this->assertNotEquals($reference1, $reference2);
        $this->assertStringStartsWith('PAY-', $reference1);
    }

    /** @test */
    public function it_can_format_amount()
    {
        $manager = app(PaymentManager::class);

        $formatted = $manager->formatAmount(100.50, 'ZAR');

        $this->assertEquals('100.50', $formatted);

        $formatted = $manager->formatAmount(100, 'ZAR');
        $this->assertEquals('100.00', $formatted);
    }

    /** @test */
    public function it_can_calculate_fee()
    {
        $manager = app(PaymentManager::class);

        $fee = $manager->calculateFee('payfast', 100.00);

        $this->assertIsFloat($fee);
        $this->assertGreaterThanOrEqual(0, $fee);
    }

    /** @test */
    public function it_can_get_supported_currencies()
    {
        $manager = app(PaymentManager::class);

        $currencies = $manager->getSupportedCurrencies('payfast');

        $this->assertIsArray($currencies);
        $this->assertContains('ZAR', $currencies);
    }

    /** @test */
    public function it_can_get_gateway_info()
    {
        $manager = app(PaymentManager::class);

        $info = $manager->getGatewayInfo('payfast');

        $this->assertIsArray($info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('icon', $info);
        $this->assertArrayHasKey('type', $info);
    }

    /** @test */
    public function it_can_test_gateway_connection()
    {
        $manager = app(PaymentManager::class);

        $result = $manager->testGatewayConnection('payfast');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('response_time', $result);
    }

    /** @test */
    public function it_can_get_transaction_by_reference()
    {
        $manager = app(PaymentManager::class);

        $transaction = $this->createTransaction([
            'reference' => 'TEST-REF-123',
        ]);

        $found = $manager->getTransactionByReference('TEST-REF-123');

        $this->assertNotNull($found);
        $this->assertEquals($transaction->id, $found->id);
        $this->assertEquals('TEST-REF-123', $found->reference);
    }

    /** @test */
    public function it_returns_null_for_invalid_transaction_reference()
    {
        $manager = app(PaymentManager::class);

        $found = $manager->getTransactionByReference('INVALID-REF');

        $this->assertNull($found);
    }

    /** @test */
    public function it_can_get_gateway_statistics()
    {
        $manager = app(PaymentManager::class);

        // Create some test transactions
        $this->createTransaction(['gateway' => 'payfast', 'status' => 'completed', 'amount' => 100]);
        $this->createTransaction(['gateway' => 'payfast', 'status' => 'completed', 'amount' => 200]);
        $this->createTransaction(['gateway' => 'payfast', 'status' => 'failed', 'amount' => 50]);
        $this->createTransaction(['gateway' => 'paystack', 'status' => 'completed', 'amount' => 150]);

        $stats = $manager->getGatewayStatistics('payfast');

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_transactions', $stats);
        $this->assertArrayHasKey('total_amount', $stats);
        $this->assertArrayHasKey('success_rate', $stats);
        $this->assertArrayHasKey('average_amount', $stats);

        $this->assertEquals(3, $stats['total_transactions']);
        $this->assertEquals(300.00, $stats['total_amount']); // Only completed transactions
        $this->assertEquals(66.67, round($stats['success_rate'], 2)); // 2 out of 3 successful
    }

    /** @test */
    public function it_can_get_all_gateway_statistics()
    {
        $manager = app(PaymentManager::class);

        // Create test transactions for multiple gateways
        $this->createTransaction(['gateway' => 'payfast', 'status' => 'completed', 'amount' => 100]);
        $this->createTransaction(['gateway' => 'paystack', 'status' => 'completed', 'amount' => 200]);
        $this->createTransaction(['gateway' => 'paypal', 'status' => 'completed', 'amount' => 150]);

        $allStats = $manager->getAllGatewayStatistics();

        $this->assertIsArray($allStats);
        $this->assertArrayHasKey('payfast', $allStats);
        $this->assertArrayHasKey('paystack', $allStats);
        $this->assertArrayHasKey('paypal', $allStats);

        $this->assertArrayHasKey('total_transactions', $allStats['payfast']);
        $this->assertArrayHasKey('total_amount', $allStats['payfast']);
    }

    /** @test */
    public function it_can_encrypt_and_decrypt_sensitive_data()
    {
        $manager = app(PaymentManager::class);

        $sensitiveData = 'This is a secret API key';

        $encrypted = $manager->encryptSensitiveData($sensitiveData);
        $decrypted = $manager->decryptSensitiveData($encrypted);

        $this->assertNotEquals($sensitiveData, $encrypted);
        $this->assertEquals($sensitiveData, $decrypted);
    }

    /** @test */
    public function it_can_log_payment_activity()
    {
        $manager = app(PaymentManager::class);

        $transaction = $this->createTransaction();

        $result = $manager->logActivity(
            $transaction->id,
            'payment_initialized',
            'Payment initialized successfully',
            ['amount' => 100, 'currency' => 'ZAR']
        );

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_get_payment_activity_log()
    {
        $manager = app(PaymentManager::class);

        $transaction = $this->createTransaction();

        // Log some activities
        $manager->logActivity($transaction->id, 'payment_initialized', 'Payment initialized');
        $manager->logActivity($transaction->id, 'payment_processed', 'Payment processed');

        $activities = $manager->getActivityLog($transaction->id);

        $this->assertIsArray($activities);
        $this->assertCount(2, $activities);

        foreach ($activities as $activity) {
            $this->assertArrayHasKey('event', $activity);
            $this->assertArrayHasKey('message', $activity);
            $this->assertArrayHasKey('timestamp', $activity);
        }
    }
}
