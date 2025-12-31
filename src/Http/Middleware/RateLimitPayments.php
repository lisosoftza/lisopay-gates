<?php

namespace Lisosoft\PaymentGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RateLimitPayments
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
        $key = $this->resolveRequestSignature($request);

        // Get rate limit configuration
        $maxAttempts = config('payment-gateway.security.rate_limit', 60);
        $decayMinutes = config('payment-gateway.security.rate_limit_period', 1);

        // Check if rate limiting is disabled
        if ($maxAttempts <= 0) {
            return $next($request);
        }

        // Check if IP is whitelisted
        if ($this->isIpWhitelisted($request->ip())) {
            return $next($request);
        }

        // Apply rate limiting
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            Log::warning('Payment rate limit exceeded', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
                'retry_after' => $retryAfter,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Too many payment attempts. Please try again later.',
                'retry_after' => $retryAfter,
                'retry_after_seconds' => $retryAfter,
            ], Response::HTTP_TOO_MANY_REQUESTS)->header('Retry-After', $retryAfter);
        }

        // Increment the rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);

        // Add headers to response
        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->add([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => RateLimiter::remaining($key, $maxAttempts),
                'X-RateLimit-Reset' => now()->addMinutes($decayMinutes)->getTimestamp(),
            ]);
        }

        return $response;
    }

    /**
     * Resolve request signature for rate limiting.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request): string
    {
        // Use IP address as the primary identifier
        $identifier = $request->ip();

        // Add user ID if authenticated
        if ($user = $request->user()) {
            $identifier .= '|' . $user->getAuthIdentifier();
        }

        // Add route information
        $route = $request->route();
        if ($route) {
            $identifier .= '|' . $route->getName() ?? $route->uri();
        }

        return 'payment-rate-limit:' . sha1($identifier);
    }

    /**
     * Check if IP address is whitelisted.
     *
     * @param  string  $ip
     * @return bool
     */
    protected function isIpWhitelisted(string $ip): bool
    {
        $whitelist = config('payment-gateway.security.ip_whitelist', []);

        if (empty($whitelist)) {
            return false;
        }

        // Convert string to array if needed
        if (is_string($whitelist)) {
            $whitelist = explode(',', $whitelist);
        }

        // Clean up IP addresses
        $whitelist = array_map('trim', $whitelist);

        // Check for exact match
        if (in_array($ip, $whitelist)) {
            return true;
        }

        // Check for CIDR notation
        foreach ($whitelist as $whitelistedIp) {
            if ($this->ipInRange($ip, $whitelistedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range.
     *
     * @param  string  $ip
     * @param  string  $range
     * @return bool
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        // Check for CIDR notation
        if (strpos($range, '/') !== false) {
            list($subnet, $bits) = explode('/', $range);

            // Convert IP addresses to long format
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - $bits);

            return ($ip & $mask) == ($subnet & $mask);
        }

        return false;
    }

    /**
     * Get the rate limiter key for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    public static function getKey(Request $request): string
    {
        return (new static)->resolveRequestSignature($request);
    }

    /**
     * Get the remaining attempts for a request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return int
     */
    public static function remainingAttempts(Request $request): int
    {
        $key = self::getKey($request);
        $maxAttempts = config('payment-gateway.security.rate_limit', 60);

        return RateLimiter::remaining($key, $maxAttempts);
    }

    /**
     * Clear the rate limiter for a request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public static function clear(Request $request): void
    {
        $key = self::getKey($request);
        RateLimiter::clear($key);
    }

    /**
     * Get the retry after time for a request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return int
     */
    public static function retryAfter(Request $request): int
    {
        $key = self::getKey($request);

        return RateLimiter::availableIn($key);
    }
}
