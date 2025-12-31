<?php

namespace Lisosoft\PaymentGateway\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Lisosoft\PaymentGateway\PaymentGatewayServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [PaymentGatewayServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app["config"]->set("database.default", "testbench");
        $app["config"]->set("database.connections.testbench", [
            "driver" => "sqlite",
            "database" => ":memory:",
            "prefix" => "",
        ]);

        // Set APP_KEY for encryption
        $app["config"]->set(
            "app.key",
            "base64:" . base64_encode(random_bytes(32)),
        );

        // Setup payment gateway configuration
        $app["config"]->set("payment-gateway", [
            "default" => "payfast",
            "gateways" => [
                "payfast" => [
                    "enabled" => true,
                    "merchant_id" => "test_merchant_id",
                    "merchant_key" => "test_merchant_key",
                    "passphrase" => "test_passphrase",
                    "test_mode" => true,
                ],
                "paystack" => [
                    "enabled" => true,
                    "public_key" => "test_public_key",
                    "secret_key" => "test_secret_key",
                    "test_mode" => true,
                ],
                "paypal" => [
                    "enabled" => true,
                    "client_id" => "test_client_id",
                    "client_secret" => "test_client_secret",
                    "mode" => "sandbox",
                ],
                "stripe" => [
                    "enabled" => true,
                    "publishable_key" => "test_publishable_key",
                    "secret_key" => "test_secret_key",
                    "test_mode" => true,
                ],
                "ozow" => [
                    "enabled" => true,
                    "site_code" => "test_site_code",
                    "private_key" => "test_private_key",
                    "test_mode" => true,
                ],
                "zapper" => [
                    "enabled" => true,
                    "merchant_id" => "test_merchant_id",
                    "site_id" => "test_site_id",
                    "api_key" => "test_api_key",
                    "test_mode" => true,
                ],
                "crypto" => [
                    "enabled" => true,
                    "api_key" => "test_api_key",
                    "api_secret" => "test_api_secret",
                    "test_mode" => true,
                ],
                "eft" => [
                    "enabled" => true,
                    "bank_name" => "Test Bank",
                    "account_name" => "Test Account",
                    "account_number" => "1234567890",
                    "branch_code" => "051001",
                ],
                "vodapay" => [
                    "enabled" => true,
                    "merchant_id" => "test_merchant_id",
                    "api_key" => "test_api_key",
                    "test_mode" => true,
                ],
                "snapscan" => [
                    "enabled" => true,
                    "merchant_id" => "test_merchant_id",
                    "api_key" => "test_api_key",
                    "test_mode" => true,
                ],
            ],
            "transaction" => [
                "currency" => "ZAR",
                "timezone" => "Africa/Johannesburg",
                "decimal_places" => 2,
                "minimum_amount" => 1.0,
                "maximum_amount" => 1000000.0,
                "default_description" => "Test Payment",
            ],
            "webhooks" => [
                "enabled" => true,
                "route_prefix" => "payment/webhook",
                "signature_verification" => true,
                "queue" => "default",
                "timeout" => 30,
            ],
            "security" => [
                "rate_limit" => 60,
                "rate_limit_period" => 1,
                "ip_whitelist" => [],
                "require_https" => false,
                "encrypt_sensitive_data" => true,
            ],
            "notifications" => [
                "email" => [
                    "enabled" => false,
                    "sender_email" => "test@example.com",
                    "sender_name" => "Test System",
                ],
                "sms" => [
                    "enabled" => false,
                    "provider" => "twilio",
                ],
                "slack" => [
                    "enabled" => false,
                    "webhook_url" => "",
                ],
            ],
            "analytics" => [
                "enabled" => true,
                "retention_days" => 365,
                "dashboard_enabled" => true,
                "export_enabled" => true,
            ],
            "recurring" => [
                "enabled" => true,
                "grace_period_days" => 3,
                "retry_attempts" => 3,
                "retry_interval_hours" => 24,
                "webhook_events" => [
                    "subscription_created",
                    "subscription_updated",
                    "subscription_cancelled",
                    "payment_succeeded",
                    "payment_failed",
                ],
            ],
            "logging" => [
                "enabled" => true,
                "level" => "debug",
                "channel" => "stack",
                "sensitive_data_masking" => true,
            ],
        ]);
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . "/../database/migrations");
        $this->artisan("migrate", ["--database" => "testbench"])->run();
    }

    /**
     * Create a test transaction.
     *
     * @param  array  $attributes
     * @return \Lisosoft\PaymentGateway\Models\Transaction
     */
    protected function createTransaction(array $attributes = [])
    {
        $defaults = [
            "reference" => "TEST-" . uniqid(),
            "gateway" => "payfast",
            "amount" => 100.0,
            "currency" => "ZAR",
            "description" => "Test Transaction",
            "status" => "pending",
            "customer_name" => "Test Customer",
            "customer_email" => "test@example.com",
            "customer_phone" => "+27123456789",
            "metadata" => json_encode(["test" => true]),
        ];

        $attributes = array_merge($defaults, $attributes);

        return \Lisosoft\PaymentGateway\Models\Transaction::create($attributes);
    }

    /**
     * Create a test subscription.
     *
     * @param  array  $attributes
     * @return \Lisosoft\PaymentGateway\Models\Subscription
     */
    protected function createSubscription(array $attributes = [])
    {
        $defaults = [
            "reference" => "SUB-" . uniqid(),
            "gateway" => "payfast",
            "amount" => 99.99,
            "currency" => "ZAR",
            "description" => "Test Subscription",
            "status" => "active",
            "frequency" => "monthly",
            "interval" => 1,
            "interval_unit" => "month",
            "total_cycles" => 12,
            "cycles_completed" => 0,
            "customer_name" => "Test Customer",
            "customer_email" => "test@example.com",
            "next_billing_date" => now()->addMonth(),
            "auto_renew" => true,
        ];

        $attributes = array_merge($defaults, $attributes);

        return \Lisosoft\PaymentGateway\Models\Subscription::create(
            $attributes,
        );
    }

    /**
     * Mock HTTP client for gateway testing.
     *
     * @param  array  $responses
     * @return \Mockery\MockInterface
     */
    protected function mockHttpClient(array $responses = [])
    {
        $mock = \Mockery::mock(\GuzzleHttp\Client::class);

        foreach ($responses as $response) {
            $mockResponse = \Mockery::mock(
                \Psr\Http\Message\ResponseInterface::class,
            );
            $mockResponse
                ->shouldReceive("getStatusCode")
                ->andReturn($response["status"] ?? 200);
            $mockResponse
                ->shouldReceive("getBody")
                ->andReturn(
                    \GuzzleHttp\Psr7\Utils::streamFor(
                        json_encode($response["body"] ?? []),
                    ),
                );

            $mock
                ->shouldReceive("request")
                ->withArgs([
                    $response["method"] ?? "POST",
                    $response["url"] ?? \Mockery::any(),
                    \Mockery::any(),
                ])
                ->andReturn($mockResponse);
        }

        return $mock;
    }

    /**
     * Assert that a transaction has the expected status.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Transaction  $transaction
     * @param  string  $status
     * @param  string  $message
     * @return void
     */
    protected function assertTransactionStatus(
        $transaction,
        $status,
        $message = "",
    ) {
        $this->assertEquals($status, $transaction->status, $message);
    }

    /**
     * Assert that a subscription has the expected status.
     *
     * @param  \Lisosoft\PaymentGateway\Models\Subscription  $subscription
     * @param  string  $status
     * @param  string  $message
     * @return void
     */
    protected function assertSubscriptionStatus(
        $subscription,
        $status,
        $message = "",
    ) {
        $this->assertEquals($status, $subscription->status, $message);
    }

    /**
     * Clean up after tests.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
