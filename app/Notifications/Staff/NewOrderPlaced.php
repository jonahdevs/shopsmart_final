<?php

namespace App\Notifications\Staff;

use App\Data\AdminNotificationData;
use App\Models\Order;
use App\Notifications\OrderPlaced;
use Illuminate\Notifications\Notification;

/**
 * "An order just came in." The staff counterpart to {@see OrderPlaced}.
 *
 * A separate class rather than a second channel on the customer's notification,
 * because the two are different messages to different audiences: the shopper is
 * being told their commitment was received, the shop is being told there is
 * work to do. Bending one notification to both would also put a customer's row
 * in the same table the bell reads, and the bell would then have to prove per
 * row that its owner is staff instead of simply owning every row it can see.
 *
 * `database` only. Staff are not mailed on every order — that is a digest
 * decision, not a bell decision, and nobody asked for one.
 *
 * @see AdminNotificationData::TYPES — `orders.view` governs this kind, which is
 *      also the permission on the screen it links to.
 */
class NewOrderPlaced extends Notification
{
    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * What the bell will show, frozen at write time.
     *
     * Only the customer's name, the total and the order number — every one of
     * which is on the order screen the recipient is being sent to, so the row
     * carries nothing `orders.view` does not already buy. No email address, no
     * phone, no address and no lines: a notification is a pointer to a record,
     * not a copy of it.
     *
     * `order_number` rather than the primary key because it is the order's
     * route key, and it is what a staff member reads the notification for.
     *
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            // The kind, written into the row so the payload says what it is
            // without anyone having to resolve a class name.
            // {@see \App\Data\AdminNotificationData::TYPES}
            'type' => 'new_order',
            'title' => __('New order :number', ['number' => $this->order->order_number]),
            'body' => $this->order->customer_name.' · '.money($this->order->total_cents),
            'order_number' => $this->order->order_number,
        ];
    }
}
