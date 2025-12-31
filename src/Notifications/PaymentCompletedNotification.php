<?php

namespace Lisosoft\PaymentGateway\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Support\Facades\Config;

class PaymentCompletedNotification extends Notification implements ShouldQueue
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
        $channels = ['database'];

        // Add email if enabled
        if (Config::get('payment-gateway.notifications.email.enabled', true)) {
            $channels[] = 'mail';
        }

        // Add Slack if enabled
        if (Config::get('payment-gateway.notifications.slack.enabled', false)) {
            $channels[] = 'slack';
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
        $transaction = $this->data['transaction'] ?? null;
        $subject = $this->data['subject'] ?? 'Payment Completed';

        $mailMessage = (new MailMessage)
            ->subject($subject)
            ->greeting('Payment Completed Successfully!');

        if ($transaction) {
            $mailMessage->line("A payment has been completed successfully.")
                ->line("**Transaction Details:**")
                ->line("- Reference: {$transaction->reference}")
                ->line("- Amount: " . number_format($transaction->amount, 2) . " {$transaction->currency}")
                ->line("- Gateway: " . ucfirst($transaction->gateway))
                ->line("- Customer: {$transaction->customer_name} ({$transaction->customer_email})")
                ->line("- Date: " . ($transaction->completed_at ? $transaction->completed_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s')))
                ->line("**Description:** {$transaction->description}")
                ->action('View Transaction', $this->getTransactionUrl($transaction))
                ->line('Thank you for using our payment service!');
        } else {
            $mailMessage->line("A payment has been completed successfully.")
                ->line("**Details:**")
                ->line("- Type: {$this->data['type']}")
                ->line("- Timestamp: {$this->data['timestamp']}")
                ->line('Thank you for using our payment service!');
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
        $transaction = $this->data['transaction'] ?? null;

        return (new SlackMessage)
            ->success()
            ->content('💰 Payment Completed Successfully!')
            ->attachment(function (SlackAttachment $attachment) use ($transaction) {
                if ($transaction) {
                    $attachment->title('Transaction Details', $this->getTransactionUrl($transaction))
                        ->fields([
                            'Reference' => $transaction->reference,
                            'Amount' => number_format($transaction->amount, 2) . ' ' . $transaction->currency,
                            'Gateway' => ucfirst($transaction->gateway),
                            'Customer' => $transaction->customer_email,
                            'Status' => 'Completed',
                        ]);
                } else {
                    $attachment->title('Payment Completed')
                        ->fields([
                            'Type' => $this->data['type'] ?? 'payment_completed',
                            'Timestamp' => $this->data['timestamp'] ?? now()->toISOString(),
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
        $transaction = $this->data['transaction'] ?? null;

        $arrayData = [
            'type' => 'payment_completed',
            'timestamp' => $this->data['timestamp'] ?? now()->toISOString(),
            'data' => $this->data,
        ];

        if ($transaction) {
            $arrayData['transaction'] = [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'gateway' => $transaction->gateway,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'customer_email' => $transaction->customer_email,
                'customer_name' => $transaction->customer_name,
                'completed_at' => $transaction->completed_at?->toISOString(),
            ];
        }

        return $arrayData;
    }

    /**
     * Get the database representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toDatabase($notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get transaction URL for admin dashboard.
     *
     * @param mixed $transaction
     * @return string
     */
    protected function getTransactionUrl($transaction): string
    {
        if (isset($transaction->id)) {
            return url('/admin/payments/transactions/' . $transaction->id);
        }

        if (isset($transaction->reference)) {
            return url('/admin/payments/transactions?reference=' . $transaction->reference);
        }

        return url('/admin/payments/dashboard');
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
     * Set the notification data.
     *
     * @param array $data
     * @return void
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
