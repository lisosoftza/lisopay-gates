<?php

namespace Lisosoft\PaymentGateway\Services;

use Lisosoft\PaymentGateway\Contracts\PaymentGatewayInterface;
use Lisosoft\PaymentGateway\Exceptions\PaymentGatewayException;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class PaymentManager extends Manager
{
    /**
     * The default gateway driver.
     *
     * @var string
     */
    protected $defaultDriver;

    /**
     * Create a new PaymentManager instance.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        parent::__construct($app);
        $this->defaultDriver = Config::get('payment-gateway.default', 'payfast');
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Set the default driver name.
     *
     * @param  string  $driver
     * @return $this
     */
    public function setDefaultDriver(string $driver): self
    {
        $this->defaultDriver = $driver;
        return $this;
    }

    /**
     * Get a gateway instance.
     *
     * @param  string|null  $driver
     * @return PaymentGatewayInterface
     * @throws PaymentGatewayException
     */
    public function gateway(?string $driver = null): PaymentGatewayInterface
    {
        try {
            return $this->driver($driver);
        } catch (\InvalidArgumentException $e) {
            throw new PaymentGatewayException(
                "Payment gateway '{$driver}' is not supported.",
                [],
                0,
                $e
            );
        }
    }

    /**
     * Create PayFast gateway driver.
     *
     * @return PaymentGatewayInterface
     */
    protected function createPayfastDriver(): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig('payfast');
        return new \Lisosoft\PaymentGateway\Gateways\PayFastGateway($config);
    }

    /**
     * Create PayStack gateway driver.
     *
     * @return PaymentGatewayInterface
     */
    protected function createPaystackDriver(): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig('paystack');
        return new \Lisosoft\PaymentGateway\Gateways\PayStackGateway($config);
    }

    /**
     * Create PayPal gateway driver.
     *
     * @return PaymentGatewayInterface
     */
    protected function createPaypalDriver(): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig('paypal');
        return new \Lisosoft\PaymentGateway\Gateways\PayPalGateway($config);
    }

    /**
     * Create Stripe gateway driver.
     *
     * @return PaymentGatewayInterface
     */
    protected function createStripeDriver(): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig('stripe');
        return new \Lisosoft\PaymentGateway\Gateways\StripeGateway($config);
    }

    /**
     * Create Ozow gateway driver.
     *
     * @return PaymentGatewayInterface
     */
    protected function createOzowDriver(): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig('ozow');
        return new \Lisosoft\PaymentGateway\Gateways\OzowGateway($config);
    }

    /**
     * Create Zapper gateway driver.
     *
     * @return PaymentGatewayInterface
     */
    protected function createZapperDriver(): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig('zapper');
        return new \Lisosoft\PaymentGateway\Gateways\ZapperGateway($config);
    }

    /**
     * Create Crypto gateway driver.
     *
     * @return PaymentGatewayInterface
     */
    protected function createCryptoDriver(): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig('crypto');
        return new \Lisosoft\PaymentGateway\Gateways\CryptoGateway($config);
    }

    /**
     * Create EFT gateway driver.
     *
     * @return PaymentGatewayInterface
     */
    protected function createEftDriver(): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig('eft');
        return new \Lisosoft\PaymentGateway\Gateways\EftGateway($config);
    }

    /**
     * Create VodaPay gateway driver.
     *
     * @return PaymentGatewayInterface
     */
    protected function createVodapayDriver(): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig('vodapay');
        return new \Lisosoft\PaymentGateway\Gateways\VodaPayGateway($config);
    }

    /**
     * Create SnapScan gateway driver.
     *
     * @return PaymentGatewayInterface
     */
    protected function createSnapscanDriver(): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig('snapscan');
        return new \Lisosoft\PaymentGateway\Gateways\SnapScanGateway($config);
    }

    /**
     * Get configuration for a specific gateway.
     *
     * @param  string  $gateway
     * @return array
     */
    protected function getGatewayConfig(string $gateway): array
    {
        $config = Config::get("payment-gateway.gateways.{$gateway}", []);

        // Add global transaction settings
        $globalConfig = Config::get('payment-gateway.transaction', []);

        return array_merge($globalConfig, $config);
    }

    /**
     * Get all available gateways.
     *
     * @return array
     */
    public function getAvailableGateways(): array
    {
        $gateways = [];
        $config = Config::get('payment-gateway.gateways', []);

        foreach ($config as $name => $gatewayConfig) {
            if ($gatewayConfig['enabled'] ?? false) {
                $gateways[$name] = [
                    'name' => $name,
                    'display_name' => $this->getGatewayDisplayName($name),
                    'config' => $gatewayConfig,
                ];
            }
        }

        return $gateways;
    }

    /**
     * Get gateway display name.
     *
     * @param  string  $gateway
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

        return $displayNames[$gateway] ?? Str::title($gateway);
    }

    /**
     * Check if a gateway is available.
     *
     * @param  string  $gateway
     * @return bool
     */
    public function isGatewayAvailable(string $gateway): bool
    {
        $config = Config::get("payment-gateway.gateways.{$gateway}", []);
        return $config['enabled'] ?? false;
    }

    /**
     * Initialize a payment with the specified gateway.
     *
     * @param  string  $gateway
     * @param  array  $paymentData
     * @return array
     * @throws PaymentGatewayException
     */
    public function initializePayment(string $gateway, array $paymentData): array
    {
        try {
            $gatewayInstance = $this->gateway($gateway);

            // Add gateway-specific metadata
            $paymentData['gateway'] = $gateway;
            $paymentData['gateway_reference'] = $gatewayInstance->generateReference($paymentData);

            // Log payment initialization
            $this->logPaymentActivity('initialize', $gateway, $paymentData);

            return $gatewayInstance->initializePayment($paymentData);

        } catch (PaymentGatewayException $e) {
            $this->logPaymentError('initialize', $gateway, $e, $paymentData);
            throw $e;
        }
    }

    /**
     * Verify a payment with the specified gateway.
     *
     * @param  string  $gateway
     * @param  string  $transactionId
     * @return array
     * @throws PaymentGatewayException
     */
    public function verifyPayment(string $gateway, string $transactionId): array
    {
        try {
            $gatewayInstance = $this->gateway($gateway);

            // Log verification attempt
            $this->logPaymentActivity('verify', $gateway, ['transaction_id' => $transactionId]);

            return $gatewayInstance->verifyPayment($transactionId);

        } catch (PaymentGatewayException $e) {
            $this->logPaymentError('verify', $gateway, $e, ['transaction_id' => $transactionId]);
            throw $e;
        }
    }

    /**
     * Process a payment callback/webhook.
     *
     * @param  string  $gateway
     * @param  array  $callbackData
     * @return array
     * @throws PaymentGatewayException
     */
    public function processCallback(string $gateway, array $callbackData): array
    {
        try {
            $gatewayInstance = $this->gateway($gateway);

            // Log callback processing
            $this->logPaymentActivity('callback', $gateway, $callbackData);

            return $gatewayInstance->processCallback($callbackData);

        } catch (PaymentGatewayException $e) {
            $this->logPaymentError('callback', $gateway, $e, $callbackData);
            throw $e;
        }
    }

    /**
     * Refund a payment.
     *
     * @param  string  $gateway
     * @param  string  $transactionId
     * @param  float|null  $amount
     * @return array
     * @throws PaymentGatewayException
     */
    public function refundPayment(string $gateway, string $transactionId, ?float $amount = null): array
    {
        try {
            $gatewayInstance = $this->gateway($gateway);

            // Log refund attempt
            $this->logPaymentActivity('refund', $gateway, [
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ]);

            return $gatewayInstance->refundPayment($transactionId, $amount);

        } catch (PaymentGatewayException $e) {
            $this->logPaymentError('refund', $gateway, $e, [
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ]);
            throw $e;
        }
    }

    /**
     * Get payment status.
     *
     * @param  string  $gateway
     * @param  string  $transactionId
     * @return string
     */
    public function getPaymentStatus(string $gateway, string $transactionId): string
    {
        try {
            $gatewayInstance = $this->gateway($gateway);
            return $gatewayInstance->getPaymentStatus($transactionId);
        } catch (\Exception $e) {
            Log::error("Failed to get payment status", [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return 'error';
        }
    }

    /**
     * Check if payment is successful.
     *
     * @param  string  $gateway
     * @param  string  $transactionId
     * @return bool
     */
    public function isPaymentSuccessful(string $gateway, string $transactionId): bool
    {
        try {
            $gatewayInstance = $this->gateway($gateway);
            return $gatewayInstance->isPaymentSuccessful($transactionId);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get supported currencies for a gateway.
     *
     * @param  string  $gateway
     * @return array
     */
    public function getSupportedCurrencies(string $gateway): array
    {
        try {
            $gatewayInstance = $this->gateway($gateway);
            return $gatewayInstance->getSupportedCurrencies();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get all supported currencies across all gateways.
     *
     * @return array
     */
    public function getAllSupportedCurrencies(): array
    {
        $currencies = [];
        $gateways = $this->getAvailableGateways();

        foreach ($gateways as $gatewayName => $gatewayInfo) {
            try {
                $gatewayCurrencies = $this->getSupportedCurrencies($gatewayName);
                $currencies = array_merge($currencies, $gatewayCurrencies);
            } catch (\Exception $e) {
                // Skip gateway if we can't get currencies
                continue;
            }
        }

        return array_unique($currencies);
    }

    /**
     * Log payment activity.
     *
     * @param  string  $action
     * @param  string  $gateway
     * @param  array  $data
     * @return void
     */
    protected function logPaymentActivity(string $action, string $gateway, array $data = []): void
    {
        $logData = [
            'action' => $action,
            'gateway' => $gateway,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];

        Log::info("Payment Manager Activity: {$action}", $logData);
    }

    /**
     * Log payment error.
     *
     * @param  string  $action
     * @param  string  $gateway
     * @param  PaymentGatewayException  $exception
     * @param  array  $context
     * @return void
     */
    protected function logPaymentError(string $action, string $gateway, PaymentGatewayException $exception, array $context = []): void
    {
        $logData = [
            'action' => $action,
            'gateway' => $gateway,
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'error_errors' => $exception->getErrors(),
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ];

        Log::error("Payment Manager Error: {$action}", $logData);
    }

    /**
     * Get gateway instance with custom configuration.
     *
     * @param  string  $gateway
     * @param  array  $customConfig
     * @return PaymentGatewayInterface
     * @throws PaymentGatewayException
     */
    public function gatewayWithConfig(string $gateway, array $customConfig): PaymentGatewayInterface
    {
        $baseConfig = $this->getGatewayConfig($gateway);
        $config = array_merge($baseConfig, $customConfig);

        $driverMethod = 'create' . Str::studly($gateway) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            // Temporarily override config for this instance
            $originalConfig = Config::get("payment-gateway.gateways.{$gateway}");
            Config::set("payment-gateway.gateways.{$gateway}", $config);

            try {
                $gatewayInstance = $this->{$driverMethod}();
                return $gatewayInstance;
            } finally {
                // Restore original config
                Config::set("payment-gateway.gateways.{$gateway}", $originalConfig);
            }
        }

        throw new PaymentGatewayException("Gateway '{$gateway}' is not supported.");
    }

    /**
     * Get statistics about payment processing.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $gateways = $this->getAvailableGateways();

        return [
            'total_gateways' => count($gateways),
            'available_gateways' => array_keys($gateways),
            'default_gateway' => $this->getDefaultDriver(),
            'supported_currencies' => $this->getAllSupportedCurrencies(),
            'timestamp' => now()->toISOString(),
        ];
    }
}
