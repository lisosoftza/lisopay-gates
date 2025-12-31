<?php

namespace Lisosoft\PaymentGateway\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Config;

class ValidPaymentGateway implements Rule
{
    /**
     * The error message.
     *
     * @var string
     */
    protected $message;

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        // Ensure value is a string
        if (!is_string($value)) {
            $this->message = 'The payment gateway must be a string.';
            return false;
        }

        // Convert to lowercase for consistency
        $gateway = strtolower(trim($value));

        // Get all configured gateways
        $configuredGateways = Config::get('payment-gateway.gateways', []);

        // Check if gateway exists in configuration
        if (!array_key_exists($gateway, $configuredGateways)) {
            $this->message = "The selected payment gateway '{$value}' is not supported.";
            return false;
        }

        // Check if gateway is enabled
        $gatewayConfig = $configuredGateways[$gateway];
        if (!($gatewayConfig['enabled'] ?? false)) {
            $this->message = "The payment gateway '{$value}' is currently disabled.";
            return false;
        }

        // Check for required configuration
        $requiredConfig = $this->getRequiredConfigForGateway($gateway);
        foreach ($requiredConfig as $configKey) {
            if (empty($gatewayConfig[$configKey] ?? '')) {
                $this->message = "The payment gateway '{$value}' is not properly configured.";
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return $this->message ?? 'The selected payment gateway is invalid.';
    }

    /**
     * Get required configuration for a gateway.
     *
     * @param  string  $gateway
     * @return array
     */
    protected function getRequiredConfigForGateway(string $gateway): array
    {
        $requiredConfig = [
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

        return $requiredConfig[$gateway] ?? [];
    }

    /**
     * Get all available gateways.
     *
     * @return array
     */
    public static function getAvailableGateways(): array
    {
        $configuredGateways = Config::get('payment-gateway.gateways', []);
        $availableGateways = [];

        foreach ($configuredGateways as $name => $config) {
            if ($config['enabled'] ?? false) {
                $availableGateways[] = $name;
            }
        }

        return $availableGateways;
    }

    /**
     * Get all supported gateways (enabled and disabled).
     *
     * @return array
     */
    public static function getSupportedGateways(): array
    {
        return array_keys(Config::get('payment-gateway.gateways', []));
    }

    /**
     * Check if a gateway is available.
     *
     * @param  string  $gateway
     * @return bool
     */
    public static function isGatewayAvailable(string $gateway): bool
    {
        $gateway = strtolower(trim($gateway));
        $configuredGateways = Config::get('payment-gateway.gateways', []);

        if (!array_key_exists($gateway, $configuredGateways)) {
            return false;
        }

        $gatewayConfig = $configuredGateways[$gateway];
        return $gatewayConfig['enabled'] ?? false;
    }

    /**
     * Get gateway display name.
     *
     * @param  string  $gateway
     * @return string
     */
    public static function getGatewayDisplayName(string $gateway): string
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

    /**
     * Get gateway configuration.
     *
     * @param  string  $gateway
     * @return array|null
     */
    public static function getGatewayConfig(string $gateway): ?array
    {
        $gateway = strtolower(trim($gateway));
        $configuredGateways = Config::get('payment-gateway.gateways', []);

        return $configuredGateways[$gateway] ?? null;
    }

    /**
     * Validate gateway configuration.
     *
     * @param  string  $gateway
     * @return array
     */
    public static function validateGatewayConfig(string $gateway): array
    {
        $gateway = strtolower(trim($gateway));
        $errors = [];

        // Get gateway configuration
        $config = self::getGatewayConfig($gateway);

        if (!$config) {
            $errors[] = "Gateway '{$gateway}' is not configured.";
            return ['valid' => false, 'errors' => $errors];
        }

        // Check if enabled
        if (!($config['enabled'] ?? false)) {
            $errors[] = "Gateway '{$gateway}' is disabled.";
        }

        // Check required configuration
        $rule = new self();
        $requiredConfig = $rule->getRequiredConfigForGateway($gateway);

        foreach ($requiredConfig as $configKey) {
            if (empty($config[$configKey] ?? '')) {
                $errors[] = "Required configuration '{$configKey}' is missing for gateway '{$gateway}'.";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'gateway' => $gateway,
            'display_name' => self::getGatewayDisplayName($gateway),
            'enabled' => $config['enabled'] ?? false,
            'test_mode' => $config['test_mode'] ?? true,
        ];
    }
}
