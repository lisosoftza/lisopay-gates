<?php

if (!function_exists('payment')) {
    /**
     * Get the payment manager instance or a specific gateway.
     *
     * @param  string|null  $gateway
     * @return \Lisosoft\PaymentGateway\Services\PaymentManager|\Lisosoft\PaymentGateway\Gateways\AbstractGateway
     */
    function payment($gateway = null)
    {
        if (is_null($gateway)) {
            return app('payment.manager');
        }

        return app('payment.manager')->gateway($gateway);
    }
}

if (!function_exists('payment_initialize')) {
    /**
     * Initialize a new payment.
     *
     * @param  string  $gateway
     * @param  array  $paymentData
     * @return array
     */
    function payment_initialize($gateway, array $paymentData)
    {
        return payment($gateway)->initializePayment($paymentData);
    }
}

if (!function_exists('payment_status')) {
    /**
     * Get payment status.
     *
     * @param  string  $gateway
     * @param  string  $reference
     * @return string
     */
    function payment_status($gateway, $reference)
    {
        return payment($gateway)->getPaymentStatus($reference);
    }
}

if (!function_exists('payment_verify')) {
    /**
     * Verify a payment.
     *
     * @param  string  $gateway
     * @param  string  $reference
     * @return array
     */
    function payment_verify($gateway, $reference)
    {
        return payment($gateway)->verifyPayment($reference);
    }
}

if (!function_exists('payment_refund')) {
    /**
     * Refund a payment.
     *
     * @param  string  $gateway
     * @param  string  $reference
     * @param  float|null  $amount
     * @param  string|null  $reason
     * @return array
     */
    function payment_refund($gateway, $reference, $amount = null, $reason = null)
    {
        return payment($gateway)->refundPayment($reference, $amount, $reason);
    }
}

if (!function_exists('payment_successful')) {
    /**
     * Check if payment is successful.
     *
     * @param  string  $gateway
     * @param  string  $reference
     * @return bool
     */
    function payment_successful($gateway, $reference)
    {
        return payment($gateway)->isPaymentSuccessful($reference);
    }
}

if (!function_exists('payment_reference')) {
    /**
     * Generate a unique payment reference.
     *
     * @param  string|null  $prefix
     * @return string
     */
    function payment_reference($prefix = null)
    {
        $prefix = $prefix ?: config('payment-gateway.transaction.reference_prefix', 'PAY');
        return $prefix . '-' . uniqid() . '-' . strtoupper(substr(md5(microtime()), 0, 6));
    }
}

if (!function_exists('payment_amount')) {
    /**
     * Format payment amount.
     *
     * @param  float  $amount
     * @param  string|null  $currency
     * @return string
     */
    function payment_amount($amount, $currency = null)
    {
        $currency = $currency ?: config('payment-gateway.transaction.currency', 'ZAR');
        $decimalPlaces = config('payment-gateway.transaction.decimal_places', 2);

        return number_format($amount, $decimalPlaces) . ' ' . $currency;
    }
}

if (!function_exists('payment_currency')) {
    /**
     * Get the default currency.
     *
     * @return string
     */
    function payment_currency()
    {
        return config('payment-gateway.transaction.currency', 'ZAR');
    }
}

if (!function_exists('payment_gateways')) {
    /**
     * Get all available gateways.
     *
     * @param  bool  $enabledOnly
     * @return array
     */
    function payment_gateways($enabledOnly = true)
    {
        $manager = payment();

        if ($enabledOnly) {
            return $manager->getEnabledGateways();
        }

        return $manager->getGateways();
    }
}

if (!function_exists('payment_gateway_info')) {
    /**
     * Get gateway information.
     *
     * @param  string  $gateway
     * @return array
     */
    function payment_gateway_info($gateway)
    {
        return payment()->getGatewayInfo($gateway);
    }
}

if (!function_exists('payment_config')) {
    /**
     * Get payment gateway configuration.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    function payment_config($key = null, $default = null)
    {
        if (is_null($key)) {
            return config('payment-gateway');
        }

        return config('payment-gateway.' . $key, $default);
    }
}

if (!function_exists('payment_transaction')) {
    /**
     * Get transaction by reference.
     *
     * @param  string  $reference
     * @return \Lisosoft\PaymentGateway\Models\Transaction|null
     */
    function payment_transaction($reference)
    {
        return \Lisosoft\PaymentGateway\Models\Transaction::where('reference', $reference)->first();
    }
}

if (!function_exists('payment_subscription')) {
    /**
     * Get subscription by reference.
     *
     * @param  string  $reference
     * @return \Lisosoft\PaymentGateway\Models\Subscription|null
     */
    function payment_subscription($reference)
    {
        return \Lisosoft\PaymentGateway\Models\Subscription::where('reference', $reference)->first();
    }
}

if (!function_exists('payment_create_subscription')) {
    /**
     * Create a new subscription.
     *
     * @param  string  $gateway
     * @param  array  $subscriptionData
     * @return array
     */
    function payment_create_subscription($gateway, array $subscriptionData)
    {
        return payment($gateway)->createSubscription($subscriptionData);
    }
}

if (!function_exists('payment_cancel_subscription')) {
    /**
     * Cancel a subscription.
     *
     * @param  string  $gateway
     * @param  string  $reference
     * @param  string|null  $reason
     * @return array
     */
    function payment_cancel_subscription($gateway, $reference, $reason = null)
    {
        return payment($gateway)->cancelSubscription($reference, $reason);
    }
}

if (!function_exists('payment_webhook_url')) {
    /**
     * Get webhook URL for a gateway.
     *
     * @param  string  $gateway
     * @return string
     */
    function payment_webhook_url($gateway)
    {
        $prefix = config('payment-gateway.webhooks.route_prefix', 'payment/webhook');
        return url($prefix . '/' . $gateway);
    }
}

if (!function_exists('payment_callback_url')) {
    /**
     * Get callback URL for a gateway.
     *
     * @param  string  $gateway
     * @param  string  $type
     * @return string
     */
    function payment_callback_url($gateway, $type = 'success')
    {
        $config = payment()->getGatewayConfig($gateway);

        if ($type === 'success' && isset($config['return_url'])) {
            return url($config['return_url']);
        }

        if ($type === 'cancel' && isset($config['cancel_url'])) {
            return url($config['cancel_url']);
        }

        if ($type === 'callback' && isset($config['callback_url'])) {
            return url($config['callback_url']);
        }

        // Default URLs
        $routes = [
            'success' => route('payment.success'),
            'cancel' => route('payment.cancel'),
            'callback' => route('payment.callback', ['gateway' => $gateway]),
        ];

        return $routes[$type] ?? $routes['success'];
    }
}

if (!function_exists('payment_minimum_amount')) {
    /**
     * Get minimum payment amount.
     *
     * @param  string|null  $gateway
     * @return float
     */
    function payment_minimum_amount($gateway = null)
    {
        if ($gateway) {
            $config = payment()->getGatewayConfig($gateway);
            if (isset($config['minimum_amount'])) {
                return (float) $config['minimum_amount'];
            }
        }

        return (float) config('payment-gateway.transaction.minimum_amount', 1.00);
    }
}

if (!function_exists('payment_maximum_amount')) {
    /**
     * Get maximum payment amount.
     *
     * @param  string|null  $gateway
     * @return float
     */
    function payment_maximum_amount($gateway = null)
    {
        if ($gateway) {
            $config = payment()->getGatewayConfig($gateway);
            if (isset($config['maximum_amount'])) {
                return (float) $config['maximum_amount'];
            }
        }

        return (float) config('payment-gateway.transaction.maximum_amount', 1000000.00);
    }
}

if (!function_exists('payment_validate_amount')) {
    /**
     * Validate payment amount.
     *
     * @param  float  $amount
     * @param  string|null  $gateway
     * @return bool
     */
    function payment_validate_amount($amount, $gateway = null)
    {
        $min = payment_minimum_amount($gateway);
        $max = payment_maximum_amount($gateway);

        return $amount >= $min && $amount <= $max;
    }
}

if (!function_exists('payment_fee')) {
    /**
     * Calculate payment fee.
     *
     * @param  string  $gateway
     * @param  float  $amount
     * @return float
     */
    function payment_fee($gateway, $amount)
    {
        return payment()->calculateFee($gateway, $amount);
    }
}

if (!function_exists('payment_net_amount')) {
    /**
     * Calculate net amount after fees.
     *
     * @param  string  $gateway
     * @param  float  $amount
     * @return float
     */
    function payment_net_amount($gateway, $amount)
    {
        $fee = payment_fee($gateway, $amount);
        return $amount - $fee;
    }
}

if (!function_exists('payment_supported_currencies')) {
    /**
     * Get supported currencies for a gateway.
     *
     * @param  string  $gateway
     * @return array
     */
    function payment_supported_currencies($gateway)
    {
        return payment()->getSupportedCurrencies($gateway);
    }
}

if (!function_exists('payment_test_connection')) {
    /**
     * Test gateway connection.
     *
     * @param  string  $gateway
     * @return array
     */
    function payment_test_connection($gateway)
    {
        return payment()->testGatewayConnection($gateway);
    }
}

if (!function_exists('payment_statistics')) {
    /**
     * Get gateway statistics.
     *
     * @param  string|null  $gateway
     * @return array
     */
    function payment_statistics($gateway = null)
    {
        if ($gateway) {
            return payment()->getGatewayStatistics($gateway);
        }

        return payment()->getAllGatewayStatistics();
    }
}

if (!function_exists('payment_log_activity')) {
    /**
     * Log payment activity.
     *
     * @param  int  $transactionId
     * @param  string  $event
     * @param  string  $message
     * @param  array  $data
     * @return bool
     */
    function payment_log_activity($transactionId, $event, $message, array $data = [])
    {
        return payment()->logActivity($transactionId, $event, $message, $data);
    }
}

if (!function_exists('payment_encrypt')) {
    /**
     * Encrypt sensitive data.
     *
     * @param  string  $data
     * @return string
     */
    function payment_encrypt($data)
    {
        return payment()->encryptSensitiveData($data);
    }
}

if (!function_exists('payment_decrypt')) {
    /**
     * Decrypt sensitive data.
     *
     * @param  string  $encryptedData
     * @return string
     */
    function payment_decrypt($encryptedData)
    {
        return payment()->decryptSensitiveData($encryptedData);
    }
}

if (!function_exists('payment_format_phone')) {
    /**
     * Format phone number for payment gateways.
     *
     * @param  string  $phone
     * @return string
     */
    function payment_format_phone($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // South African phone number formatting
        if (strlen($phone) === 9 && substr($phone, 0, 1) === '0') {
            $phone = '27' . substr($phone, 1);
        }

        // Ensure it starts with +
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}

if (!function_exists('payment_sanitize_amount')) {
    /**
     * Sanitize amount for payment gateways.
     *
     * @param  mixed  $amount
     * @return float
     */
    function payment_sanitize_amount($amount)
    {
        $amount = (float) $amount;
        $decimalPlaces = config('payment-gateway.transaction.decimal_places', 2);

        return round($amount, $decimalPlaces);
    }
}

if (!function_exists('payment_generate_signature')) {
    /**
     * Generate payment signature.
     *
     * @param  array  $data
     * @param  string  $secret
     * @return string
     */
    function payment_generate_signature(array $data, $secret)
    {
        // Sort data by key
        ksort($data);

        // Create parameter string
        $paramString = '';
        foreach ($data as $key => $value) {
            if ($key !== 'signature' && !empty($value)) {
                $paramString .= $key . '=' . urlencode($value) . '&';
            }
        }

        // Remove trailing &
        $paramString = rtrim($paramString, '&');

        // Add passphrase
        $paramString .= $secret;

        // Generate signature
        return md5($paramString);
    }
}

if (!function_exists('payment_verify_signature')) {
    /**
     * Verify payment signature.
     *
     * @param  array  $data
     * @param  string  $secret
     * @param  string  $signatureField
     * @return bool
     */
    function payment_verify_signature(array $data, $secret, $signatureField = 'signature')
    {
        if (!isset($data[$signatureField])) {
            return false;
        }

        $receivedSignature = $data[$signatureField];
        unset($data[$signatureField]);

        $generatedSignature = payment_generate_signature($data, $secret);

        return hash_equals($generatedSignature, $receivedSignature);
    }
}

if (!function_exists('payment_redirect_url')) {
    /**
     * Get payment redirect URL.
     *
     * @param  string  $gateway
     * @param  array  $paymentData
     * @return string|null
     */
    function payment_redirect_url($gateway, array $paymentData)
    {
        $result = payment_initialize($gateway, $paymentData);

        return $result['data']['payment_url'] ?? null;
    }
}

if (!function_exists('payment_is_test_mode')) {
    /**
     * Check if gateway is in test mode.
     *
     * @param  string  $gateway
     * @return bool
     */
    function payment_is_test_mode($gateway)
    {
        $config = payment()->getGatewayConfig($gateway);

        return (bool) ($config['test_mode'] ?? true);
    }
}

if (!function_exists('payment_is_enabled')) {
    /**
     * Check if gateway is enabled.
     *
     * @param  string  $gateway
     * @return bool
     */
    function payment_is_enabled($gateway)
    {
        return payment()->isGatewayEnabled($gateway);
    }
}

if (!function_exists('payment_default_gateway')) {
    /**
     * Get default payment gateway.
     *
     * @return string
     */
    function payment_default_gateway()
    {
        return config('payment-gateway.default', 'payfast');
    }
}

if (!function_exists('payment_available_gateways')) {
    /**
     * Get list of available gateways.
     *
     * @param  bool  $enabledOnly
     * @return array
     */
    function payment_available_gateways($enabledOnly = true)
    {
        $gateways = payment_gateways($enabledOnly);
        $available = [];

        foreach ($gateways as $gateway) {
            $available[] = $gateway->getGatewayCode();
        }

        return $available;
    }
}

if (!function_exists('payment_gateway_display_name')) {
    /**
     * Get gateway display name.
     *
     * @param  string  $gatewayCode
     * @return string
     */
    function payment_gateway_display_name($gatewayCode)
    {
        $info = payment_gateway_info($gatewayCode);

        return $info['name'] ?? ucfirst($gatewayCode);
    }
}

if (!function_exists('payment_gateway_icon')) {
    /**
     * Get gateway icon class.
     *
     * @param  string  $gatewayCode
     * @return string
     */
    function payment_gateway_icon($gatewayCode)
    {
        $info = payment_gateway_info($gatewayCode);

        return $info['icon'] ?? 'fa-credit-card';
    }
}

if (!function_exists('payment_gateway_color')) {
    /**
     * Get gateway color.
     *
     * @param  string  $gatewayCode
     * @return string
     */
    function payment_gateway_color($gatewayCode)
    {
        $info = payment_gateway_info($gatewayCode);

        return $info['color'] ?? '#667eea';
    }
}

if (!function_exists('payment_create_customer')) {
    /**
     * Create customer data array.
     *
     * @param  array  $customerData
     * @return array
     */
    function payment_create_customer(array $customerData)
    {
        $defaults = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'country' => 'ZA',
            'postal_code' => '',
        ];

        return array_merge($defaults, $customerData);
    }
}

if (!function_exists('payment_create_metadata')) {
    /**
     * Create payment metadata.
     *
     * @param  array  $metadata
     * @return array
     */
    function payment_create_metadata(array $metadata)
    {
        $defaults = [
            'source' => 'web',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ];

        return array_merge($defaults, $metadata);
    }
}

if (!function_exists('payment_response')) {
    /**
     * Create standardized payment response.
     *
     * @param  bool  $success
     * @param  string  $message
     * @param  array  $data
     * @param  int  $status
     * @return \Illuminate\Http\JsonResponse
     */
    function payment_response($success, $message, $data = [], $status = 200)
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ], $status);
    }
}

if (!function_exists('payment_success_response')) {
    /**
     * Create success payment response.
     *
     * @param  string  $message
     * @param  array  $data
     * @param  int  $status
     * @return \Illuminate\Http\JsonResponse
     */
    function payment_success_response($message, $data = [], $status = 200)
    {
        return payment_response(true, $message, $data, $status);
    }
}

if (!function_exists('payment_error_response')) {
    /**
     * Create error payment response.
     *
     * @param  string  $message
     * @param  array  $errors
     * @param  int  $status
     * @return \Illuminate\Http\JsonResponse
     */
    function payment_error_response($message, $errors = [], $status = 400)
    {
        $data = ['errors' => $errors];
        return payment_response(false, $message, $data, $status);
    }
}

if (!function_exists('payment_validation_response')) {
    /**
     * Create validation error response.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return \Illuminate\Http\JsonResponse
     */
    function payment_validation_response($validator)
    {
        return payment_error_response(
            'Validation failed',
            $validator->errors()->toArray(),
            422
        );
    }
}

if (!function_exists('payment_not_found_response')) {
    /**
     * Create not found response.
     *
     * @param  string  $message
     * @return \Illuminate\Http\JsonResponse
     */
    function payment_not_found_response($message = 'Resource not found')
    {
        return payment_error_response($message, [], 404);
    }
}

if (!function_exists('payment_unauthorized_response')) {
    /**
     * Create unauthorized response.
     *
     * @param  string  $message
     * @return \Illuminate\Http\JsonResponse
     */
    function payment_unauthorized_response($message = 'Unauthorized')
    {
        return payment_error_response($message, [], 401);
    }
}

if (!function_exists('payment_forbidden_response')) {
    /**
     * Create forbidden response.
     *
     * @param  string  $message
     * @return \Illuminate\Http\JsonResponse
     */
    function payment_forbidden_response($message = 'Forbidden')
    {
        return payment_error_response($message, [], 403);
    }
}

if (!function_exists('payment_internal_error_response')) {
    /**
     * Create internal error response.
     *
     * @param  string  $message
     * @param  array  $errors
     * @return \Illuminate\Http\JsonResponse
     */
    function payment_internal_error_response($message = 'Internal server error', $errors = [])
    {
        return payment_error_response($message, $errors, 500);
    }
}
