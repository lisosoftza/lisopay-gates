<?php

namespace Lisosoft\PaymentGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
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
        // Check if signature verification is enabled
        if (!Config::get('payment-gateway.webhooks.signature_verification', true)) {
            return $next($request);
        }

        // Determine gateway from request path
        $gateway = $this->extractGatewayFromPath($request->path());

        if (!$gateway) {
            Log::warning('Could not determine gateway from webhook path', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook endpoint',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Get gateway configuration
        $gatewayConfig = Config::get("payment-gateway.gateways.{$gateway}", []);

        if (empty($gatewayConfig) || !($gatewayConfig['enabled'] ?? false)) {
            Log::warning('Webhook received for disabled or non-existent gateway', [
                'gateway' => $gateway,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gateway not enabled or configured',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify webhook signature based on gateway
        $signatureValid = $this->verifyGatewaySignature($gateway, $request, $gatewayConfig);

        if (!$signatureValid) {
            Log::warning('Webhook signature verification failed', [
                'gateway' => $gateway,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Log successful verification
        Log::info('Webhook signature verified successfully', [
            'gateway' => $gateway,
            'ip' => $request->ip(),
            'timestamp' => now()->toISOString(),
        ]);

        // Add gateway information to request for controller use
        $request->merge([
            'verified_gateway' => $gateway,
            'webhook_verified_at' => now()->toISOString(),
        ]);

        return $next($request);
    }

    /**
     * Extract gateway name from request path.
     *
     * @param  string  $path
     * @return string|null
     */
    protected function extractGatewayFromPath(string $path): ?string
    {
        // Common webhook path patterns
        $patterns = [
            '/payment\/webhook\/([a-z]+)/i',
            '/api\/v1\/payments\/webhook\/([a-z]+)/i',
            '/webhook\/([a-z]+)/i',
            '/callback\/([a-z]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path, $matches)) {
                return strtolower($matches[1]);
            }
        }

        return null;
    }

    /**
     * Verify webhook signature based on gateway.
     *
     * @param  string  $gateway
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $gatewayConfig
     * @return bool
     */
    protected function verifyGatewaySignature(string $gateway, Request $request, array $gatewayConfig): bool
    {
        // Gateway-specific signature verification methods
        $verificationMethods = [
            'payfast' => 'verifyPayFastSignature',
            'paystack' => 'verifyPayStackSignature',
            'paypal' => 'verifyPayPalSignature',
            'stripe' => 'verifyStripeSignature',
            'ozow' => 'verifyOzowSignature',
            'zapper' => 'verifyZapperSignature',
            'crypto' => 'verifyCryptoSignature',
            'vodapay' => 'verifyVodaPaySignature',
            'snapscan' => 'verifySnapScanSignature',
        ];

        $method = $verificationMethods[$gateway] ?? null;

        if ($method && method_exists($this, $method)) {
            return $this->{$method}($request, $gatewayConfig);
        }

        // Default signature verification for gateways without specific implementation
        return $this->verifyGenericSignature($request, $gatewayConfig);
    }

    /**
     * Verify PayFast signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @return bool
     */
    protected function verifyPayFastSignature(Request $request, array $config): bool
    {
        // PayFast uses MD5 hash of all parameters plus passphrase
        $data = $request->all();
        $passphrase = $config['passphrase'] ?? '';

        // Remove signature from data
        $signature = $data['signature'] ?? null;
        unset($data['signature']);

        // Sort data alphabetically
        ksort($data);

        // Create parameter string
        $paramString = '';
        foreach ($data as $key => $value) {
            if ($value !== '' && $key !== 'signature') {
                $paramString .= $key . '=' . urlencode(trim($value)) . '&';
            }
        }

        // Remove last &
        $paramString = substr($paramString, 0, -1);

        // Add passphrase if set
        if ($passphrase) {
            $paramString .= '&passphrase=' . urlencode(trim($passphrase));
        }

        // Generate signature
        $generatedSignature = md5($paramString);

        return hash_equals($generatedSignature, $signature);
    }

    /**
     * Verify PayStack signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @return bool
     */
    protected function verifyPayStackSignature(Request $request, array $config): bool
    {
        $signature = $request->header('x-paystack-signature');
        $secretKey = $config['secret_key'] ?? '';

        if (!$signature || !$secretKey) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha512', $payload, $secretKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify PayPal signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @return bool
     */
    protected function verifyPayPalSignature(Request $request, array $config): bool
    {
        // PayPal webhook verification requires calling their API
        // For simplicity, we'll implement basic header verification
        $transmissionId = $request->header('paypal-transmission-id');
        $transmissionTime = $request->header('paypal-transmission-time');
        $transmissionSig = $request->header('paypal-transmission-sig');
        $certUrl = $request->header('paypal-cert-url');
        $authAlgo = $request->header('paypal-auth-algo');

        if (!$transmissionId || !$transmissionSig || !$certUrl) {
            return false;
        }

        // In production, you should implement full PayPal webhook verification
        // This is a simplified version
        $webhookId = $config['webhook_id'] ?? '';

        if ($webhookId) {
            // Basic check: verify webhook ID matches
            $body = json_decode($request->getContent(), true);
            $receivedWebhookId = $body['resource']['webhook_id'] ?? null;

            return $receivedWebhookId === $webhookId;
        }

        // If no webhook ID configured, accept the webhook (not recommended for production)
        return Config::get('app.env') === 'local' || Config::get('app.env') === 'testing';
    }

    /**
     * Verify Stripe signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @return bool
     */
    protected function verifyStripeSignature(Request $request, array $config): bool
    {
        $signature = $request->header('stripe-signature');
        $webhookSecret = $config['webhook_secret'] ?? '';

        if (!$signature || !$webhookSecret) {
            return false;
        }

        $payload = $request->getContent();
        $timestamp = null;

        // Extract timestamp from signature
        $parts = explode(',', $signature);
        foreach ($parts as $part) {
            if (strpos($part, 't=') === 0) {
                $timestamp = substr($part, 2);
                break;
            }
        }

        if (!$timestamp) {
            return false;
        }

        // Check if timestamp is within tolerance (5 minutes)
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        // Generate signature
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        // Extract signature from header
        $signatureParts = [];
        foreach ($parts as $part) {
            if (strpos($part, 'v1=') === 0) {
                $signatureParts[] = $part;
            }
        }

        if (empty($signatureParts)) {
            return false;
        }

        $receivedSignature = substr($signatureParts[0], 3);

        return hash_equals($expectedSignature, $receivedSignature);
    }

    /**
     * Verify Ozow signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @return bool
     */
    protected function verifyOzowSignature(Request $request, array $config): bool
    {
        // Ozow uses SHA512 HMAC with private key
        $data = $request->all();
        $signature = $data['Hash'] ?? null;
        $privateKey = $config['private_key'] ?? '';

        if (!$signature || !$privateKey) {
            return false;
        }

        // Remove hash from data
        unset($data['Hash']);

        // Sort data alphabetically
        ksort($data);

        // Create parameter string
        $paramString = '';
        foreach ($data as $key => $value) {
            if ($value !== '') {
                $paramString .= $key . '=' . urlencode(trim($value)) . '&';
            }
        }

        // Remove last &
        $paramString = substr($paramString, 0, -1);

        // Generate signature
        $generatedSignature = hash_hmac('sha512', $paramString, $privateKey);

        return hash_equals($generatedSignature, $signature);
    }

    /**
     * Verify Zapper signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @return bool
     */
    protected function verifyZapperSignature(Request $request, array $config): bool
    {
        return $this->verifyGenericSignature($request, $config, 'X-Zapper-Signature');
    }

    /**
     * Verify Crypto signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @return bool
     */
    protected function verifyCryptoSignature(Request $request, array $config): bool
    {
        return $this->verifyGenericSignature($request, $config, 'X-Crypto-Signature');
    }

    /**
     * Verify VodaPay signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @return bool
     */
    protected function verifyVodaPaySignature(Request $request, array $config): bool
    {
        return $this->verifyGenericSignature($request, $config, 'X-VodaPay-Signature');
    }

    /**
     * Verify SnapScan signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @return bool
     */
    protected function verifySnapScanSignature(Request $request, array $config): bool
    {
        return $this->verifyGenericSignature($request, $config, 'X-SnapScan-Signature');
    }

    /**
     * Verify generic signature using HMAC.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @param  string  $headerName
     * @return bool
     */
    protected function verifyGenericSignature(Request $request, array $config, string $headerName = 'X-Signature'): bool
    {
        $signature = $request->header($headerName);
        $secret = $config['api_secret'] ?? $config['webhook_secret'] ?? '';

        if (!$signature || !$secret) {
            // If no signature or secret, check if we're in test mode
            return $config['test_mode'] ?? false;
        }

        $payload = $request->getContent();
        $timestamp = $request->header('X-Timestamp');

        // If timestamp is provided, include it in signature
        if ($timestamp) {
            $dataToSign = $timestamp . $payload;
        } else {
            $dataToSign = $payload;
        }

        $expectedSignature = hash_hmac('sha256', $dataToSign, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get the list of supported gateways for webhook verification.
     *
     * @return array
     */
    public static function getSupportedGateways(): array
    {
        return [
            'payfast',
            'paystack',
            'paypal',
            'stripe',
            'ozow',
            'zapper',
            'crypto',
            'vodapay',
            'snapscan',
        ];
    }

    /**
     * Check if a gateway supports webhook signature verification.
     *
     * @param  string  $gateway
     * @return bool
     */
    public static function supportsSignatureVerification(string $gateway): bool
    {
        return in_array($gateway, self::getSupportedGateways());
    }

    /**
     * Get the required configuration for a gateway's webhook verification.
     *
     * @param  string  $gateway
     * @return array
     */
    public static function getRequiredConfig(string $gateway): array
    {
        $configs = [
            'payfast' => ['passphrase'],
            'paystack' => ['secret_key'],
            'paypal' => ['webhook_id'],
            'stripe' => ['webhook_secret'],
            'ozow' => ['private_key'],
            'zapper' => ['api_secret'],
            'crypto' => ['webhook_secret'],
            'vodapay' => ['api_secret'],
            'snapscan' => ['api_secret'],
        ];

        return $configs[$gateway] ?? ['api_secret', 'webhook_secret'];
    }
}
