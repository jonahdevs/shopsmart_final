<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The receipt: money has been taken and the order is now being prepared.
 *
 * Sent from {@see Order::markPaid()}, on the single branch whose conditional
 * UPDATE actually moved the row. A payment can confirm twice — once from the
 * browser's verify call and once from the gateway's webhook — and the loser of
 * that race must not send a second receipt.
 */
class OrderPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // See OrderPlaced: the worker rendering this may never have touched the
        // order, and lazy loading is prevented outside production.
        $this->order->loadMissing('items');

        return (new MailMessage)
            ->subject(__('Payment received for order :number', ['number' => $this->order->order_number]))
            ->markdown('mail.orders.paid', [
                'order' => $this->order,
                'url' => route('orders.show', $this->order),
                'receiptUrl' => route('orders.receipt', $this->order),
            ]);
    }
}
