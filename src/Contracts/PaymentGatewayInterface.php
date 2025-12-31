<?php

namespace Lisosoft\PaymentGateway\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Initialize a payment transaction
     *
     * @param array $paymentData
     * @return array
     */
    public function initializePayment(array $paymentData): array;

    /**
     * Verify a payment transaction
     *
     * @param string $transactionId
     * @return array
     */
    public function verifyPayment(string $transactionId): array;

    /**
     * Process a payment callback/webhook
     *
     * @param array $callbackData
     * @return array
     */
    public function processCallback(array $callbackData): array;

    /**
     * Refund a payment
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     */
    public function refundPayment(string $transactionId, ?float $amount = null): array;

    /**
     * Check if gateway is available
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get gateway configuration
     *
     * @return array
     */
    public function getConfig(): array;

    /**
     * Get gateway name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get gateway display name
     *
     * @return string
     */
    public function getDisplayName(): string;

    /**
     * Get supported currencies
     *
     * @return array
     */
    public function getSupportedCurrencies(): array;

    /**
     * Check if currency is supported
     *
     * @param string $currency
     * @return bool
     */
    public function supportsCurrency(string $currency): bool;

    /**
     * Get minimum payment amount
     *
     * @return float
     */
    public function getMinimumAmount(): float;

    /**
     * Get maximum payment amount
     *
     * @return float
     */
    public function getMaximumAmount(): float;

    /**
     * Check if test mode is enabled
     *
     * @return bool
     */
    public function isTestMode(): bool;

    /**
     * Get payment form URL
     *
     * @return string
     */
    public function getPaymentUrl(): string;

    /**
     * Get webhook URL
     *
     * @return string
     */
    public function getWebhookUrl(): string;

    /**
     * Get return URL
     *
     * @return string
     */
    public function getReturnUrl(): string;

    /**
     * Get cancel URL
     *
     * @return string
     */
    public function getCancelUrl(): string;

    /**
     * Generate payment reference
     *
     * @param array $paymentData
     * @return string
     */
    public function generateReference(array $paymentData): string;

    /**
     * Validate payment data
     *
     * @param array $paymentData
     * @return array
     */
    public function validatePaymentData(array $paymentData): array;

    /**
     * Create subscription/recurring payment
     *
     * @param array $subscriptionData
     * @return array
     */
    public function createSubscription(array $subscriptionData): array;

    /**
     * Cancel subscription
     *
     * @param string $subscriptionId
     * @return array
     */
    public function cancelSubscription(string $subscriptionId): array;

    /**
     * Get subscription details
     *
     * @param string $subscriptionId
     * @return array
     */
    public function getSubscription(string $subscriptionId): array;

    /**
     * Update subscription
     *
     * @param string $subscriptionId
     * @param array $subscriptionData
     * @return array
     */
    public function updateSubscription(string $subscriptionId, array $subscriptionData): array;

    /**
     * Get transaction history
     *
     * @param array $filters
     * @return array
     */
    public function getTransactionHistory(array $filters = []): array;

    /**
     * Get payment status
     *
     * @param string $transactionId
     * @return string
     */
    public function getPaymentStatus(string $transactionId): string;

    /**
     * Check if payment is successful
     *
     * @param string $transactionId
     * @return bool
     */
    public function isPaymentSuccessful(string $transactionId): bool;

    /**
     * Check if payment is pending
     *
     * @param string $transactionId
     * @return bool
     */
    public function isPaymentPending(string $transactionId): bool;

    /**
     * Check if payment is failed
     *
     * @param string $transactionId
     * @return bool
     */
    public function isPaymentFailed(string $transactionId): bool;

    /**
     * Get error message
     *
     * @param string $errorCode
     * @return string
     */
    public function getErrorMessage(string $errorCode): string;

    /**
     * Get gateway logo URL
     *
     * @return string
     */
    public function getLogoUrl(): string;

    /**
     * Get gateway documentation URL
     *
     * @return string
     */
    public function getDocumentationUrl(): string;

    /**
     * Get gateway API endpoint
     *
     * @return string
     */
    public function getApiEndpoint(): string;

    /**
     * Get required fields for payment
     *
     * @return array
     */
    public function getRequiredFields(): array;

    /**
     * Get optional fields for payment
     *
     * @return array
     */
    public function getOptionalFields(): array;

    /**
     * Get payment methods supported by gateway
     *
     * @return array
     */
    public function getPaymentMethods(): array;

    /**
     * Check if payment method is supported
     *
     * @param string $method
     * @return bool
     */
    public function supportsPaymentMethod(string $method): bool;

    /**
     * Get gateway version
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Get last error
     *
     * @return array|null
     */
    public function getLastError(): ?array;

    /**
     * Clear last error
     *
     * @return void
     */
    public function clearLastError(): void;
}
