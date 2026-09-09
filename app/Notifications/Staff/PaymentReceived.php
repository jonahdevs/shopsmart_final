<?php

namespace App\Notifications\Staff;

use App\Data\AdminActivityRowData;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Admin\PaymentController;
use App\Models\Payment;
use App\Notifications\OrderPaid;
use App\Services\Paystack\PaystackPaymentService;
use Illuminate\Notifications\Notification;

/**
 * "Money landed." The staff counterpart to {@see OrderPaid}.
 *
 * Written from the one place a payment reaches
 * {@see PaymentStatus::Success} — the settle branch of
 * {@see PaystackPaymentService} — so a webhook replaying
 * a confirmation the browser already made cannot ring the bell twice.
 *
 * Governed by `payments.view`, not `orders.view`, because it is a payment it
 * describes and the payment record is where it points. That is the same rule
 * {@see AdminActivityRowData::SUBJECT_PERMISSIONS} applies to the
 * audit trail: the permission that guards the subject guards what is said
 * about it.
 */
class PaymentReceived extends Notification
{
    public function __construct(public Payment $payment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The amount, the gateway and the order it settles — the three things the
     * payments screen leads with, and nothing from the encrypted gateway
     * payload, which carries the payer's name, phone and masked instrument.
     * {@see PaymentController} refuses to send that
     * to the client; a notification row is not the way around it.
     *
     * @return array<string, string|int>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->payment->loadMissing('order:id,order_number');

        return [
            // {@see \App\Data\AdminNotificationData::TYPES}
            'type' => 'payment_received',
            'title' => __('Payment received :amount', ['amount' => money($this->payment->amount_cents)]),
            'body' => __('Order :number · :gateway', [
                'number' => $this->payment->order?->order_number ?? '—',
                'gateway' => ucfirst($this->payment->gateway),
            ]),
            'payment_id' => $this->payment->getKey(),
        ];
    }
}
