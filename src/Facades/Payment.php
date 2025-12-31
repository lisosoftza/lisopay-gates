<?php

namespace Lisosoft\PaymentGateway\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Lisosoft\PaymentGateway\Contracts\PaymentGatewayInterface gateway(string|null $driver = null)
 * @method static \Lisosoft\PaymentGateway\Contracts\PaymentGatewayInterface driver(string|null $driver = null)
 * @method static string getDefaultDriver()
 * @method static \Lisosoft\PaymentGateway\Services\PaymentManager setDefaultDriver(string $driver)
 * @method static array getAvailableGateways()
 * @method static bool isGatewayAvailable(string $gateway)
 * @method static array initializePayment(string $gateway, array $paymentData)
 * @method static array verifyPayment(string $gateway, string $transactionId)
 * @method static array processCallback(string $gateway, array $callbackData)
 * @method static array refundPayment(string $gateway, string $transactionId, float|null $amount = null)
 * @method static string getPaymentStatus(string $gateway, string $transactionId)
 * @method static bool isPaymentSuccessful(string $gateway, string $transactionId)
 * @method static array getSupportedCurrencies(string $gateway)
 * @method static array getAllSupportedCurrencies()
 * @method static \Lisosoft\PaymentGateway\Contracts\PaymentGatewayInterface gatewayWithConfig(string $gateway, array $customConfig)
 * @method static array getStatistics()
 *
 * @see \Lisosoft\PaymentGateway\Services\PaymentManager
 */
class Payment extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'payment.manager';
    }
}
