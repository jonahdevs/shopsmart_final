<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fulfilment moved: out for delivery, completed, cancelled, refunded.
 *
 * Sent from {@see Order::changeStatus()}, which is the one place a status is
 * allowed to move after payment, so a shopper is told exactly once per real
 * transition and never for a no-op save.
 *
 * The previous status travels with the notification because "your order is
 * complete" reads very differently after "out for delivery" than after
 * "cancelled", and the queue worker sees the row only in its new state.
 */
class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public OrderStatus $previousStatus,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Order :number is now :status', [
                'number' => $this->order->order_number,
                'status' => $this->order->status->label(),
            ]))
            ->markdown('mail.orders.status-changed', [
                'order' => $this->order,
                'previousStatus' => $this->previousStatus,
                'url' => route('orders.show', $this->order),
            ]);
    }
}
