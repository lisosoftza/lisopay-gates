<?php

namespace Lisosoft\PaymentGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ValidatePaymentAmount
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only validate for payment-related routes
        if (!$this->shouldValidate($request)) {
            return $next($request);
        }

        // Get payment amount from request
        $amount = $request->input('amount');

        // If no amount is provided, skip validation
        if ($amount === null) {
            return $next($request);
        }

        // Validate amount format
        $validator = Validator::make(['amount' => $amount], [
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        if ($validator->fails()) {
            Log::warning('Invalid payment amount format', [
                'ip' => $request->ip(),
                'amount' => $amount,
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid payment amount',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Convert to float for validation
        $amount = floatval($amount);

        // Get validation rules from configuration
        $minimumAmount = config('payment-gateway.transaction.minimum_amount', 1.00);
        $maximumAmount = config('payment-gateway.transaction.maximum_amount', 1000000.00);
        $decimalPlaces = config('payment-gateway.transaction.decimal_places', 2);

        // Validate minimum amount
        if ($amount < $minimumAmount) {
            Log::warning('Payment amount below minimum', [
                'ip' => $request->ip(),
                'amount' => $amount,
                'minimum_amount' => $minimumAmount,
            ]);

            return response()->json([
                'success' => false,
                'message' => "Payment amount must be at least {$minimumAmount}",
                'minimum_amount' => $minimumAmount,
                'provided_amount' => $amount,
            ], 422);
        }

        // Validate maximum amount
        if ($amount > $maximumAmount) {
            Log::warning('Payment amount above maximum', [
                'ip' => $request->ip(),
                'amount' => $amount,
                'maximum_amount' => $maximumAmount,
            ]);

            return response()->json([
                'success' => false,
                'message' => "Payment amount cannot exceed {$maximumAmount}",
                'maximum_amount' => $maximumAmount,
                'provided_amount' => $amount,
            ], 422);
        }

        // Validate decimal places
        $decimalPart = $amount - floor($amount);
        $decimalDigits = strlen(substr(strrchr((string) $decimalPart, '.'), 1));

        if ($decimalDigits > $decimalPlaces) {
            Log::warning('Payment amount has too many decimal places', [
                'ip' => $request->ip(),
                'amount' => $amount,
                'decimal_digits' => $decimalDigits,
                'allowed_decimal_places' => $decimalPlaces,
            ]);

            return response()->json([
                'success' => false,
                'message' => "Payment amount can have at most {$decimalPlaces} decimal places",
                'allowed_decimal_places' => $decimalPlaces,
                'provided_decimal_digits' => $decimalDigits,
            ], 422);
        }

        // Validate for specific gateway if provided
        $gateway = $request->input('gateway');
        if ($gateway) {
            $gatewayConfig = config("payment-gateway.gateways.{$gateway}", []);

            if (!empty($gatewayConfig)) {
                $gatewayMinAmount = $gatewayConfig['minimum_amount'] ?? $minimumAmount;
                $gatewayMaxAmount = $gatewayConfig['maximum_amount'] ?? $maximumAmount;

                if ($amount < $gatewayMinAmount) {
                    Log::warning('Payment amount below gateway minimum', [
                        'ip' => $request->ip(),
                        'gateway' => $gateway,
                        'amount' => $amount,
                        'gateway_minimum_amount' => $gatewayMinAmount,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "Payment amount for {$gateway} must be at least {$gatewayMinAmount}",
                        'gateway' => $gateway,
                        'gateway_minimum_amount' => $gatewayMinAmount,
                        'provided_amount' => $amount,
                    ], 422);
                }

                if ($amount > $gatewayMaxAmount) {
                    Log::warning('Payment amount above gateway maximum', [
                        'ip' => $request->ip(),
                        'gateway' => $gateway,
                        'amount' => $amount,
                        'gateway_maximum_amount' => $gatewayMaxAmount,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "Payment amount for {$gateway} cannot exceed {$gatewayMaxAmount}",
                        'gateway' => $gateway,
                        'gateway_maximum_amount' => $gatewayMaxAmount,
                        'provided_amount' => $amount,
                    ], 422);
                }
            }
        }

        // Add validated amount to request for later use
        $request->merge(['validated_amount' => $amount]);

        return $next($request);
    }

    /**
     * Determine if the request should be validated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldValidate(Request $request): bool
    {
        $path = $request->path();
        $method = $request->method();

        // Validate only for payment initialization and related endpoints
        $paymentPaths = [
            'api/v1/payments/initialize',
            'api/v1/pay',
            'payment/initialize',
            'api/v1/payments/subscriptions',
            'api/v1/subscriptions',
        ];

        foreach ($paymentPaths as $paymentPath) {
            if (strpos($path, $paymentPath) !== false) {
                return true;
            }
        }

        // Also validate for POST requests to payment endpoints
        if ($method === 'POST' && (
            strpos($path, 'payment') !== false ||
            strpos($path, 'pay') !== false ||
            strpos($path, 'subscribe') !== false
        )) {
            return true;
        }

        return false;
    }

    /**
     * Format amount for display.
     *
     * @param  float  $amount
     * @param  string  $currency
     * @return string
     */
    public static function formatAmount(float $amount, string $currency = 'ZAR'): string
    {
        $decimalPlaces = config('payment-gateway.transaction.decimal_places', 2);
        $formattedAmount = number_format($amount, $decimalPlaces);

        // Add currency symbol based on currency code
        $symbols = [
            'ZAR' => 'R',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];

        $symbol = $symbols[$currency] ?? $currency . ' ';

        return $symbol . $formattedAmount;
    }

    /**
     * Validate amount against configuration.
     *
     * @param  float  $amount
     * @param  string|null  $gateway
     * @return array
     */
    public static function validateAmount(float $amount, ?string $gateway = null): array
    {
        $minimumAmount = config('payment-gateway.transaction.minimum_amount', 1.00);
        $maximumAmount = config('payment-gateway.transaction.maximum_amount', 1000000.00);
        $decimalPlaces = config('payment-gateway.transaction.decimal_places', 2);

        $errors = [];

        // Validate minimum amount
        if ($amount < $minimumAmount) {
            $errors[] = "Amount must be at least {$minimumAmount}";
        }

        // Validate maximum amount
        if ($amount > $maximumAmount) {
            $errors[] = "Amount cannot exceed {$maximumAmount}";
        }

        // Validate decimal places
        $decimalPart = $amount - floor($amount);
        $decimalDigits = strlen(substr(strrchr((string) $decimalPart, '.'), 1));

        if ($decimalDigits > $decimalPlaces) {
            $errors[] = "Amount can have at most {$decimalPlaces} decimal places";
        }

        // Validate for specific gateway if provided
        if ($gateway) {
            $gatewayConfig = config("payment-gateway.gateways.{$gateway}", []);

            if (!empty($gatewayConfig)) {
                $gatewayMinAmount = $gatewayConfig['minimum_amount'] ?? $minimumAmount;
                $gatewayMaxAmount = $gatewayConfig['maximum_amount'] ?? $maximumAmount;

                if ($amount < $gatewayMinAmount) {
                    $errors[] = "Amount for {$gateway} must be at least {$gatewayMinAmount}";
                }

                if ($amount > $gatewayMaxAmount) {
                    $errors[] = "Amount for {$gateway} cannot exceed {$gatewayMaxAmount}";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'minimum_amount' => $minimumAmount,
            'maximum_amount' => $maximumAmount,
            'decimal_places' => $decimalPlaces,
            'formatted_amount' => self::formatAmount($amount),
        ];
    }

    /**
     * Get the minimum amount for a gateway.
     *
     * @param  string|null  $gateway
     * @return float
     */
    public static function getMinimumAmount(?string $gateway = null): float
    {
        $minimumAmount = config('payment-gateway.transaction.minimum_amount', 1.00);

        if ($gateway) {
            $gatewayConfig = config("payment-gateway.gateways.{$gateway}", []);
            if (!empty($gatewayConfig) && isset($gatewayConfig['minimum_amount'])) {
                return floatval($gatewayConfig['minimum_amount']);
            }
        }

        return $minimumAmount;
    }

    /**
     * Get the maximum amount for a gateway.
     *
     * @param  string|null  $gateway
     * @return float
     */
    public static function getMaximumAmount(?string $gateway = null): float
    {
        $maximumAmount = config('payment-gateway.transaction.maximum_amount', 1000000.00);

        if ($gateway) {
            $gatewayConfig = config("payment-gateway.gateways.{$gateway}", []);
            if (!empty($gatewayConfig) && isset($gatewayConfig['maximum_amount'])) {
                return floatval($gatewayConfig['maximum_amount']);
            }
        }

        return $maximumAmount;
    }
}
