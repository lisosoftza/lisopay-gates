<?php

namespace Lisosoft\PaymentGateway\Tests\Feature;

use Lisosoft\PaymentGateway\Tests\TestCase;
use Lisosoft\PaymentGateway\Models\Transaction;
use Lisosoft\PaymentGateway\Models\Subscription;

class PaymentApiTest extends TestCase
{
    /** @test */
    public function it_can_initialize_payment()
    {
        $response = $this->postJson('/api/payments/initialize', [
            'gateway' => 'payfast',
            'amount' => 100.00,
            'currency' => 'ZAR',
            'description' => 'Test Payment',
            'customer' => [
                'email' => 'test@example.com',
                'name' => 'Test Customer',
                'phone' => '+27123456789',
            ],
            'metadata' => [
                'order_id' => 12345,
                'product_id' => 67890,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'reference',
                    'payment_url',
                    'transaction_id',
                    'amount',
                    'currency',
                    'gateway',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 100.00,
                    'currency' => 'ZAR',
                    'gateway' => 'payfast',
                ],
            ]);
    }

    /** @test */
    public function it_validates_payment_initialization_data()
    {
        $response = $this->postJson('/api/payments/initialize', [
            'gateway' => 'invalid_gateway',
            'amount' => 0,
            'currency' => 'INVALID',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'gateway',
                'amount',
                'currency',
                'customer.email',
            ]);
    }

    /** @test */
    public function it_can_get_payment_status()
    {
        $transaction = $this->createTransaction([
            'reference' => 'TEST-REF-123',
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/payments/status/TEST-REF-123');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'reference',
                    'status',
                    'amount',
                    'currency',
                    'gateway',
                    'customer',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'reference' => 'TEST-REF-123',
                    'status' => 'completed',
                ],
            ]);
    }

    /** @test */
    public function it_returns_error_for_invalid_payment_reference()
    {
        $response = $this->getJson('/api/payments/status/INVALID-REF');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Transaction not found',
            ]);
    }

    /** @test */
    public function it_can_verify_payment()
    {
        $transaction = $this->createTransaction([
            'reference' => 'TEST-REF-456',
            'gateway_transaction_id' => 'GATEWAY-123',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/payments/verify', [
            'gateway' => 'payfast',
            'reference' => 'TEST-REF-456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'reference',
                    'status',
                    'gateway_status',
                    'verified',
                    'transaction',
                ],
            ]);
    }

    /** @test */
    public function it_can_refund_payment()
    {
        $transaction = $this->createTransaction([
            'reference' => 'TEST-REF-789',
            'status' => 'completed',
            'amount' => 100.00,
            'gateway_transaction_id' => 'GATEWAY-456',
        ]);

        $response = $this->postJson('/api/payments/refund', [
            'gateway' => 'payfast',
            'reference' => 'TEST-REF-789',
            'amount' => 50.00,
            'reason' => 'Customer request',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'refund_id',
                    'reference',
                    'amount',
                    'status',
                    'transaction',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 50.00,
                ],
            ]);
    }

    /** @test */
    public function it_cannot_refund_non_completed_payment()
    {
        $transaction = $this->createTransaction([
            'reference' => 'TEST-REF-999',
            'status' => 'pending',
            'amount' => 100.00,
        ]);

        $response = $this->postJson('/api/payments/refund', [
            'gateway' => 'payfast',
            'reference' => 'TEST-REF-999',
            'amount' => 50.00,
            'reason' => 'Customer request',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot refund a payment that is not completed',
            ]);
    }

    /** @test */
    public function it_can_create_subscription()
    {
        $response = $this->postJson('/api/subscriptions/create', [
            'gateway' => 'payfast',
            'amount' => 99.99,
            'currency' => 'ZAR',
            'description' => 'Monthly Subscription',
            'customer' => [
                'email' => 'subscriber@example.com',
                'name' => 'Subscription Customer',
            ],
            'frequency' => 'monthly',
            'interval' => 1,
            'total_cycles' => 12,
            'start_date' => now()->addDay()->toDateString(),
            'auto_renew' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'subscription_id',
                    'reference',
                    'gateway_subscription_id',
                    'amount',
                    'currency',
                    'frequency',
                    'next_billing_date',
                    'status',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 99.99,
                    'currency' => 'ZAR',
                    'frequency' => 'monthly',
                    'auto_renew' => true,
                ],
            ]);
    }

    /** @test */
    public function it_can_cancel_subscription()
    {
        $subscription = $this->createSubscription([
            'reference' => 'SUB-REF-123',
            'gateway_subscription_id' => 'GATEWAY-SUB-123',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/subscriptions/cancel', [
            'gateway' => 'payfast',
            'reference' => 'SUB-REF-123',
            'reason' => 'Customer cancellation',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'cancelled',
                    'cancelled_at',
                    'subscription',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'cancelled' => true,
                ],
            ]);
    }

    /** @test */
    public function it_can_get_subscription_details()
    {
        $subscription = $this->createSubscription([
            'reference' => 'SUB-REF-456',
            'status' => 'active',
            'next_billing_date' => now()->addMonth(),
        ]);

        $response = $this->getJson('/api/subscriptions/SUB-REF-456');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'reference',
                    'status',
                    'amount',
                    'currency',
                    'frequency',
                    'next_billing_date',
                    'cycles_completed',
                    'total_cycles',
                    'customer',
                    'created_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'reference' => 'SUB-REF-456',
                    'status' => 'active',
                ],
            ]);
    }

    /** @test */
    public function it_can_list_transactions()
    {
        // Create test transactions
        $this->createTransaction(['status' => 'completed', 'amount' => 100]);
        $this->createTransaction(['status' => 'completed', 'amount' => 200]);
        $this->createTransaction(['status' => 'failed', 'amount' => 50]);

        $response = $this->getJson('/api/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'reference',
                        'amount',
                        'currency',
                        'status',
                        'gateway',
                        'customer',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'total',
                    'count',
                    'per_page',
                    'current_page',
                    'total_pages',
                    'links',
                ],
                'summary' => [
                    'total_amount',
                    'total_transactions',
                    'status_counts',
                    'gateway_counts',
                    'success_rate',
                ],
            ]);
    }

    /** @test */
    public function it_can_filter_transactions()
    {
        // Create test transactions with different statuses and gateways
        $this->createTransaction(['gateway' => 'payfast', 'status' => 'completed', 'amount' => 100]);
        $this->createTransaction(['gateway' => 'payfast', 'status' => 'failed', 'amount' => 50]);
        $this->createTransaction(['gateway' => 'paystack', 'status' => 'completed', 'amount' => 200]);

        $response = $this->getJson('/api/transactions?gateway=payfast&status=completed');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'summary' => [
                    'total_transactions' => 1,
                    'gateway_counts' => [
                        'payfast' => 1,
                    ],
                    'status_counts' => [
                        'completed' => 1,
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_get_gateway_list()
    {
        $response = $this->getJson('/api/gateways');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'code',
                        'name',
                        'description',
                        'enabled',
                        'type',
                        'category',
                        'supported_currencies',
                        'stats',
                        'status',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_get_gateway_details()
    {
        $response = $this->getJson('/api/gateways/payfast');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'code',
                    'name',
                    'description',
                    'enabled',
                    'config',
                    'credentials',
                    'stats',
                    'status',
                    'capabilities',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'payfast',
                    'enabled' => true,
                ],
            ]);
    }

    /** @test */
    public function it_can_test_gateway_connection()
    {
        $response = $this->postJson('/api/gateways/payfast/test');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'connected',
                    'response_time',
                    'gateway_status',
                    'test_transaction',
                ],
            ]);
    }

    /** @test */
    public function it_can_process_webhook()
    {
        $transaction = $this->createTransaction([
            'reference' => 'WEBHOOK-REF-123',
            'gateway_transaction_id' => 'WEBHOOK-GATEWAY-123',
            'status' => 'pending',
        ]);

        $webhookData = [
            'payment_status' => 'COMPLETE',
            'pt' => 'WEBHOOK-GATEWAY-123',
            'amount_gross' => '100.00',
            'm_payment_id' => 'WEBHOOK-REF-123',
            'signature' => 'test_signature',
        ];

        $response = $this->postJson('/api/webhooks/payfast', $webhookData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'processed',
                    'transaction',
                    'webhook_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'processed' => true,
                ],
            ]);
    }

    /** @test */
    public function it_validates_webhook_signature()
    {
        $webhookData = [
            'payment_status' => 'COMPLETE',
            'pt' => 'INVALID-GATEWAY-123',
            'amount_gross' => '100.00',
            'm_payment_id' => 'INVALID-REF-123',
            'signature' => 'invalid_signature',
        ];

        $response = $this->postJson('/api/webhooks/payfast', $webhookData);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid webhook signature',
            ]);
    }

    /** @test */
    public function it_can_get_payment_statistics()
    {
        // Create test data
        $this->createTransaction(['status' => 'completed', 'amount' => 100, 'created_at' => now()->subDay()]);
        $this->createTransaction(['status' => 'completed', 'amount' => 200, 'created_at' => now()]);
        $this->createTransaction(['status' => 'failed', 'amount' => 50, 'created_at' => now()]);

        $response = $this->getJson('/api/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'overview',
                    'daily_totals',
                    'gateway_distribution',
                    'status_distribution',
                    'revenue_trend',
                    'conversion_rate',
                ],
            ]);
    }

    /** @test */
    public function it_can_export_transactions()
    {
        // Create test transactions
        $this->createTransaction(['status' => 'completed', 'amount' => 100]);
        $this->createTransaction(['status' => 'completed', 'amount' => 200]);

        $response = $this->getJson('/api/transactions/export?format=csv');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="transactions.csv"');
    }

    /** @test */
    public function it_requires_authentication_for_admin_endpoints()
    {
        $response = $this->getJson('/api/admin/transactions');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_can_generate_payment_receipt()
    {
        $transaction = $this->createTransaction([
            'reference' => 'RECEIPT-REF-123',
            'status' => 'completed',
            'amount' => 150.00,
        ]);

        $response = $this->getJson('/api/payments/RECEIPT-REF-123/receipt');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'receipt',
                    'transaction',
                    'download_url',
                    'qr_code',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'transaction' => [
                        'reference' => 'RECEIPT-REF-123',
                        'amount' => 150.00,
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_handle_payment_retry()
    {
        $transaction = $this->createTransaction([
            'reference' => 'RETRY-REF-123',
            'status' => 'failed',
            'gateway_transaction_id' => 'FAILED-GATEWAY-123',
        ]);

        $response = $this->postJson('/api/payments/retry', [
            'gateway' => 'payfast',
            'reference' => 'RETRY-REF-123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'retry_initiated',
                    'new_reference',
                    'payment_url',
                    'original_transaction',
                ],
            ]);
    }

    /** @test */
    public function it_cannot_retry_successful_payment()
    {
        $transaction = $this->createTransaction([
            'reference' => 'SUCCESS-REF-123',
            'status' => 'completed',
        ]);

        $response = $this->postJson('/api/payments/retry', [
            'gateway' => 'payfast',
            'reference' => 'SUCCESS-REF-123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot retry a successful payment',
            ]);
    }
}
