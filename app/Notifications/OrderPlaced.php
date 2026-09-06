<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "We have your order." Sent once, the moment the order row is written.
 *
 * Deliberately not "thanks for your payment": the order is created unpaid and
 * the shopper may still be on their way to the gateway, so this message
 * confirms the commitment and nothing more. {@see OrderPaid} confirms the money.
 *
 * Queued, because a checkout must not wait on an SMTP handshake, and dispatched
 * after the placement transaction commits so the queue worker cannot pick it up
 * before the order exists.
 */
class OrderPlaced extends Notification implements ShouldQueue
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
        // The queue may hand this to a worker that has never touched the order,
        // and lazy loading is prevented outside production, so the lines the
        // view iterates are loaded explicitly here.
        $this->order->loadMissing('items');

        return (new MailMessage)
            ->subject(__('Order :number received', ['number' => $this->order->order_number]))
            ->markdown('mail.orders.placed', [
                'order' => $this->order,
                'url' => route('orders.show', $this->order),
            ]);
    }
}
