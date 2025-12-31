<?php

namespace Lisosoft\PaymentGateway\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Support\Facades\Config;
use Lisosoft\PaymentGateway\Models\Transaction;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The notification data.
     *
     * @var array
     */
    protected $data;

    /**
     * Create a new notification instance.
     *
     * @param array $data
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        $channels = ["database"];

        // Add email if enabled
        if (Config::get("payment-gateway.notifications.email.enabled", true)) {
            $channels[] = "mail";
        }

        // Add Slack if enabled
        if (Config::get("payment-gateway.notifications.slack.enabled", false)) {
            $channels[] = "slack";
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        $transaction = $this->data["transaction"] ?? null;
        $subject = $this->data["subject"] ?? "Payment Failed";
        $retryAttempts = $this->data["retry_attempts"] ?? 0;
        $maxAttempts = $this->data["max_attempts"] ?? 3;

        $mailMessage = (new MailMessage())
            ->subject($subject)
            ->greeting("Payment Failed");

        if ($transaction) {
            $mailMessage
                ->line("A payment has failed to process.")
                ->line("**Transaction Details:**")
                ->line("- Reference: {$transaction->reference}")
                ->line(
                    "- Amount: " .
                        number_format($transaction->amount, 2) .
                        " {$transaction->currency}",
                )
                ->line("- Gateway: " . ucfirst($transaction->gateway))
                ->line(
                    "- Customer: {$transaction->customer_name} ({$transaction->customer_email})",
                )
                ->line(
                    "- Date: " .
                        ($transaction->failed_at
                            ? $transaction->failed_at->format("Y-m-d H:i:s")
                            : now()->format("Y-m-d H:i:s")),
                )
                ->line("- Error: {$transaction->error_message}")
                ->line("- Attempt: {$retryAttempts}/{$maxAttempts}")
                ->line("**Description:** {$transaction->description}");

            // Add retry information if applicable
            if ($retryAttempts < $maxAttempts) {
                $mailMessage
                    ->line("**Retry Information:**")
                    ->line("- Next retry will be attempted automatically.")
                    ->line(
                        "- Remaining attempts: " .
                            ($maxAttempts - $retryAttempts),
                    );
            } else {
                $mailMessage->line(
                    "**Status:** Maximum retry attempts reached. Manual intervention required.",
                );
            }

            $mailMessage
                ->action(
                    "View Transaction",
                    $this->getTransactionUrl($transaction),
                )
                ->line(
                    "Please review the payment details and take appropriate action.",
                );
        } else {
            $mailMessage
                ->line("A payment has failed to process.")
                ->line("**Details:**")
                ->line("- Type: {$this->data["type"]}")
                ->line("- Timestamp: {$this->data["timestamp"]}")
                ->line(
                    "- Error: " .
                        ($this->data["error_message"] ?? "Unknown error"),
                )
                ->line(
                    "Please review the payment details and take appropriate action.",
                );
        }

        return $mailMessage;
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\SlackMessage
     */
    public function toSlack($notifiable): SlackMessage
    {
        $transaction = $this->data["transaction"] ?? null;
        $retryAttempts = $this->data["retry_attempts"] ?? 0;
        $maxAttempts = $this->data["max_attempts"] ?? 3;

        return (new SlackMessage())
            ->error()
            ->content("❌ Payment Failed!")
            ->attachment(function (SlackAttachment $attachment) use (
                $transaction,
                $retryAttempts,
                $maxAttempts,
            ) {
                if ($transaction) {
                    $attachment
                        ->title(
                            "Transaction Details",
                            $this->getTransactionUrl($transaction),
                        )
                        ->fields([
                            "Reference" => $transaction->reference,
                            "Amount" =>
                                number_format($transaction->amount, 2) .
                                " " .
                                $transaction->currency,
                            "Gateway" => ucfirst($transaction->gateway),
                            "Customer" => $transaction->customer_email,
                            "Status" => "Failed",
                            "Error" => $transaction->error_message,
                            "Attempt" => "{$retryAttempts}/{$maxAttempts}",
                        ]);

                    if ($retryAttempts >= $maxAttempts) {
                        $attachment->field("Alert", "MAX ATTEMPTS REACHED");
                    }
                } else {
                    $attachment->title("Payment Failed")->fields([
                        "Type" => $this->data["type"] ?? "payment_failed",
                        "Timestamp" =>
                            $this->data["timestamp"] ?? now()->toISOString(),
                        "Error" =>
                            $this->data["error_message"] ?? "Unknown error",
                    ]);
                }
            });
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        $transaction = $this->data["transaction"] ?? null;

        $arrayData = [
            "type" => "payment_failed",
            "timestamp" => $this->data["timestamp"] ?? now()->toISOString(),
            "retry_attempts" => $this->data["retry_attempts"] ?? 0,
            "max_attempts" => $this->data["max_attempts"] ?? 3,
            "data" => $this->data,
        ];

        if ($transaction) {
            $arrayData["transaction"] = [
                "id" => $transaction->id,
                "reference" => $transaction->reference,
                "gateway" => $transaction->gateway,
                "amount" => $transaction->amount,
                "currency" => $transaction->currency,
                "customer_email" => $transaction->customer_email,
                "customer_name" => $transaction->customer_name,
                "failed_at" => $transaction->failed_at?->toISOString(),
                "error_message" => $transaction->error_message,
                "error_code" => $transaction->error_code,
            ];
        }

        return $arrayData;
    }

    /**
     * Get the notification's unique identifier.
     *
     * @return string
     */
    public function getId(): string
    {
        return "payment_failed_" .
            ($this->data["transaction"]->reference ?? uniqid());
    }

    /**
     * Get the transaction URL for the notification.
     *
     * @param mixed $transaction
     * @return string
     */
    protected function getTransactionUrl($transaction): string
    {
        if (isset($transaction->id)) {
            return url("/admin/payments/transactions/" . $transaction->id);
        }

        if (isset($transaction->reference)) {
            return url(
                "/admin/payments/transactions?reference=" .
                    $transaction->reference,
            );
        }

        return url("/admin/payments/dashboard");
    }

    /**
     * Get the notification data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the transaction from the notification data.
     *
     * @return Transaction|null
     */
    public function getTransaction(): ?Transaction
    {
        return $this->data["transaction"] ?? null;
    }

    /**
     * Check if the payment can be retried.
     *
     * @return bool
     */
    public function canRetry(): bool
    {
        $retryAttempts = $this->data["retry_attempts"] ?? 0;
        $maxAttempts = $this->data["max_attempts"] ?? 3;

        return $retryAttempts < $maxAttempts;
    }

    /**
     * Get retry information.
     *
     * @return array
     */
    public function getRetryInfo(): array
    {
        $retryAttempts = $this->data["retry_attempts"] ?? 0;
        $maxAttempts = $this->data["max_attempts"] ?? 3;

        return [
            "current_attempt" => $retryAttempts,
            "max_attempts" => $maxAttempts,
            "remaining_attempts" => $maxAttempts - $retryAttempts,
            "can_retry" => $retryAttempts < $maxAttempts,
        ];
    }
}
