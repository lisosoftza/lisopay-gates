<?php

namespace Lisosoft\PaymentGateway\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GatewayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'category' => $this->category,
            'category_label' => $this->getCategoryLabel(),
            'enabled' => (bool) $this->enabled,
            'test_mode' => (bool) $this->test_mode,
            'supported_currencies' => $this->supported_currencies ? json_decode($this->supported_currencies, true) : [],
            'supported_countries' => $this->supported_countries ? json_decode($this->supported_countries, true) : [],
            'minimum_amount' => $this->minimum_amount ? (float) $this->minimum_amount : null,
            'maximum_amount' => $this->maximum_amount ? (float) $this->maximum_amount : null,
            'transaction_fee_type' => $this->transaction_fee_type,
            'transaction_fee_fixed' => $this->transaction_fee_fixed ? (float) $this->transaction_fee_fixed : null,
            'transaction_fee_percentage' => $this->transaction_fee_percentage ? (float) $this->transaction_fee_percentage : null,
            'settlement_days' => $this->settlement_days,
            'auto_refund_enabled' => (bool) $this->auto_refund_enabled,
            'auto_refund_days' => $this->auto_refund_days,
            'webhook_support' => (bool) $this->webhook_support,
            'recurring_payment_support' => (bool) $this->recurring_payment_support,
            'partial_refund_support' => (bool) $this->partial_refund_support,
            'instant_settlement' => (bool) $this->instant_settlement,
            'requires_redirect' => (bool) $this->requires_redirect,
            'requires_3ds' => (bool) $this->requires_3ds,
            'config' => $this->config ? json_decode($this->config, true) : [],
            'credentials' => $this->getMaskedCredentials(),
            'stats' => [
                'total_transactions' => $this->total_transactions ?? 0,
                'total_amount' => $this->total_amount ? (float) $this->total_amount : 0,
                'success_rate' => $this->success_rate ? (float) $this->success_rate : 0,
                'average_transaction_value' => $this->average_transaction_value ? (float) $this->average_transaction_value : 0,
                'pending_transactions' => $this->pending_transactions ?? 0,
                'failed_transactions' => $this->failed_transactions ?? 0,
                'refunded_transactions' => $this->refunded_transactions ?? 0,
            ],
            'status' => $this->getGatewayStatus(),
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
            'last_used_at' => $this->last_used_at ? $this->last_used_at->toISOString() : null,
            'links' => [
                'self' => route('api.gateways.show', $this->code),
                'test' => route('api.gateways.test', $this->code),
                'transactions' => route('api.gateways.transactions', $this->code),
                'config' => route('api.gateways.config', $this->code),
            ],
        ];
    }

    /**
     * Get human-readable type label.
     *
     * @return string
     */
    protected function getTypeLabel()
    {
        $labels = [
            'card' => 'Card Payments',
            'bank' => 'Bank Transfer',
            'wallet' => 'Digital Wallet',
            'crypto' => 'Cryptocurrency',
            'qr' => 'QR Code',
            'mobile' => 'Mobile Payment',
            'pos' => 'Point of Sale',
            'other' => 'Other',
        ];

        return $labels[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get human-readable category label.
     *
     * @return string
     */
    protected function getCategoryLabel()
    {
        $labels = [
            'international' => 'International',
            'local' => 'Local',
            'regional' => 'Regional',
            'global' => 'Global',
            'specialized' => 'Specialized',
        ];

        return $labels[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Get masked credentials for security.
     *
     * @return array
     */
    protected function getMaskedCredentials()
    {
        $credentials = $this->credentials ? json_decode($this->credentials, true) : [];

        foreach ($credentials as $key => $value) {
            if (is_string($value) && $this->shouldMask($key)) {
                $credentials[$key] = $this->maskValue($value);
            }
        }

        return $credentials;
    }

    /**
     * Determine if a credential key should be masked.
     *
     * @param  string  $key
     * @return bool
     */
    protected function shouldMask($key)
    {
        $sensitiveKeys = [
            'key', 'secret', 'password', 'token', 'passphrase',
            'private_key', 'api_secret', 'client_secret', 'webhook_secret',
        ];

        foreach ($sensitiveKeys as $sensitiveKey) {
            if (stripos($key, $sensitiveKey) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask a sensitive value.
     *
     * @param  string  $value
     * @return string
     */
    protected function maskValue($value)
    {
        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        $visibleChars = 4;
        $maskLength = strlen($value) - $visibleChars;

        return str_repeat('*', $maskLength) . substr($value, -$visibleChars);
    }

    /**
     * Get gateway status information.
     *
     * @return array
     */
    protected function getGatewayStatus()
    {
        return [
            'operational' => (bool) $this->enabled,
            'test_mode' => (bool) $this->test_mode,
            'configured' => $this->isConfigured(),
            'connected' => $this->isConnected(),
            'last_check' => $this->last_check_at ? $this->last_check_at->toISOString() : null,
            'health_status' => $this->health_status ?? 'unknown',
            'health_message' => $this->getHealthMessage(),
            'maintenance_mode' => (bool) $this->maintenance_mode,
            'maintenance_message' => $this->maintenance_message,
            'maintenance_until' => $this->maintenance_until ? $this->maintenance_until->toISOString() : null,
        ];
    }

    /**
     * Check if gateway is properly configured.
     *
     * @return bool
     */
    protected function isConfigured()
    {
        $config = $this->config ? json_decode($this->config, true) : [];
        $requiredFields = $this->getRequiredConfigFields();

        foreach ($requiredFields as $field) {
            if (empty($config[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get required configuration fields for this gateway.
     *
     * @return array
     */
    protected function getRequiredConfigFields()
    {
        $fieldsByGateway = [
            'payfast' => ['merchant_id', 'merchant_key'],
            'paystack' => ['public_key', 'secret_key'],
            'paypal' => ['client_id', 'client_secret'],
            'stripe' => ['publishable_key', 'secret_key'],
            'ozow' => ['site_code', 'private_key'],
            'zapper' => ['merchant_id', 'site_id', 'api_key'],
            'crypto' => ['api_key', 'api_secret'],
            'eft' => ['account_name', 'account_number', 'bank_name'],
            'vodapay' => ['merchant_id', 'api_key'],
            'snapscan' => ['merchant_id', 'api_key'],
        ];

        return $fieldsByGateway[$this->code] ?? [];
    }

    /**
     * Check if gateway is connected (API test).
     *
     * @return bool
     */
    protected function isConnected()
    {
        return $this->last_check_successful ?? false;
    }

    /**
     * Get health status message.
     *
     * @return string
     */
    protected function getHealthMessage()
    {
        $messages = [
            'healthy' => 'Gateway is operating normally',
            'degraded' => 'Gateway is experiencing issues',
            'unhealthy' => 'Gateway is not responding',
            'maintenance' => 'Gateway is under maintenance',
            'unknown' => 'Gateway status unknown',
        ];

        return $messages[$this->health_status] ?? 'Status unknown';
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        return [
            'meta' => [
                'version' => '1.0',
                'api_version' => config('payment-gateway.api_version', 'v1'),
                'timestamp' => now()->toISOString(),
                'capabilities' => $this->getCapabilities(),
                'security' => [
                    'credentials_masked' => true,
                    'pci_compliant' => $this->isPciCompliant(),
                ],
            ],
        ];
    }

    /**
     * Get gateway capabilities.
     *
     * @return array
     */
    protected function getCapabilities()
    {
        return [
            'payment_methods' => $this->getSupportedPaymentMethods(),
            'features' => $this->getSupportedFeatures(),
            'limits' => [
                'minimum_amount' => $this->minimum_amount ? (float) $this->minimum_amount : null,
                'maximum_amount' => $this->maximum_amount ? (float) $this->maximum_amount : null,
                'daily_limit' => $this->daily_limit ? (float) $this->daily_limit : null,
                'monthly_limit' => $this->monthly_limit ? (float) $this->monthly_limit : null,
            ],
            'settlement' => [
                'instant' => (bool) $this->instant_settlement,
                'days' => $this->settlement_days,
                'schedule' => $this->settlement_schedule,
            ],
        ];
    }

    /**
     * Get supported payment methods.
     *
     * @return array
     */
    protected function getSupportedPaymentMethods()
    {
        $methods = [];

        if ($this->type === 'card') {
            $methods[] = 'credit_card';
            $methods[] = 'debit_card';
        }

        if ($this->type === 'bank') {
            $methods[] = 'bank_transfer';
            $methods[] = 'eft';
        }

        if ($this->type === 'wallet') {
            $methods[] = 'digital_wallet';
        }

        if ($this->type === 'crypto') {
            $methods[] = 'cryptocurrency';
        }

        if ($this->type === 'qr') {
            $methods[] = 'qr_code';
        }

        if ($this->type === 'mobile') {
            $methods[] = 'mobile_payment';
        }

        // Add gateway-specific methods
        switch ($this->code) {
            case 'payfast':
                $methods = array_merge($methods, ['credit_card', 'debit_card', 'eft', 'instant_eft']);
                break;
            case 'paystack':
                $methods = array_merge($methods, ['credit_card', 'debit_card', 'bank_transfer']);
                break;
            case 'paypal':
                $methods = array_merge($methods, ['paypal_balance', 'credit_card', 'bank_account']);
                break;
        }

        return array_unique($methods);
    }

    /**
     * Get supported features.
     *
     * @return array
     */
    protected function getSupportedFeatures()
    {
        $features = [];

        if ($this->webhook_support) {
            $features[] = 'webhooks';
        }

        if ($this->recurring_payment_support) {
            $features[] = 'subscriptions';
        }

        if ($this->partial_refund_support) {
            $features[] = 'partial_refunds';
        }

        if ($this->auto_refund_enabled) {
            $features[] = 'auto_refunds';
        }

        if ($this->requires_3ds) {
            $features[] = '3d_secure';
        }

        return $features;
    }

    /**
     * Check if gateway is PCI compliant.
     *
     * @return bool
     */
    protected function isPciCompliant()
    {
        $pciCompliantGateways = [
            'payfast', 'paystack', 'paypal', 'stripe', 'ozow', 'zapper',
        ];

        return in_array($this->code, $pciCompliantGateways);
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        $response->header('X-Payment-API-Version', config('payment-gateway.api_version', 'v1'));
        $response->header('X-Gateway-Code', $this->code);
        $response->header('X-Gateway-Status', $this->health_status ?? 'unknown');
        $response->header('X-Gateway-Enabled', $this->enabled ? 'true' : 'false');

        // Add cache headers
        $response->header('Cache-Control', 'private, max-age=300'); // Cache for 5 minutes
    }
}
