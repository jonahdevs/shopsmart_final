<?php

namespace App\Http\Controllers\Dev;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\OrderPaid;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Renders the transactional emails in a browser, for local development only.
 *
 * Registered behind an environment check in routes/web.php, so it does not
 * exist on a deployed site — a page that will render any of the store's emails
 * to anyone who asks is not something to leave switched on.
 *
 * Nothing here touches the database. The sample order is an unsaved model with
 * an unsaved line collection attached, which means the preview works on an
 * empty database and cannot be confused for a real order.
 */
class MailPreviewController extends Controller
{
    public function __invoke(Request $request): string
    {
        $key = (string) $request->query('mail', '');
        $recipient = $this->sampleUser();

        $message = match ($key) {
            'order-placed' => (new OrderPlaced($this->sampleOrder()))->toMail($recipient),
            'order-paid' => (new OrderPaid($this->sampleOrder(paid: true)))->toMail($recipient),
            'order-status-changed' => (new OrderStatusChanged(
                $this->sampleOrder(paid: true, status: OrderStatus::OutForDelivery),
                OrderStatus::Processing,
            ))->toMail($recipient),
            default => null,
        };

        return $message === null
            ? $this->index()
            : (string) $message->render();
    }

    /** The list of previews, so the route is useful without knowing the keys. */
    private function index(): string
    {
        $links = collect([
            'order-placed' => 'OrderPlaced',
            'order-paid' => 'OrderPaid',
            'order-status-changed' => 'OrderStatusChanged',
        ])->map(fn (string $label, string $key): string => sprintf(
            '<li><a href="%s">%s</a></li>',
            e(route('dev.mail-preview', ['mail' => $key])),
            e($label),
        ))->implode('');

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            .'<title>Mail preview</title></head><body style="font:14px system-ui;padding:2rem">'
            .'<h1>Mail preview</h1><ul>'.$links.'</ul></body></html>';
    }

    /**
     * An order that looks real enough to lay out, and exists nowhere.
     *
     * `setRelation` rather than a saved relation: the notification views only
     * ever read `$order->items`, and `loadMissing()` leaves an already-set
     * relation alone, so an unsaved order never reaches the database.
     */
    private function sampleOrder(bool $paid = false, ?OrderStatus $status = null): Order
    {
        $order = new Order([
            'order_number' => 'SS-000123',
            'customer_name' => 'Amina Wanjiru',
            'customer_email' => 'amina@example.com',
            'customer_phone' => '0712345678',
            'status' => $status ?? ($paid ? OrderStatus::Processing : OrderStatus::Pending),
            'payment_status' => $paid ? PaymentStatus::Success : PaymentStatus::Pending,
            'payment_method' => $paid ? 'paystack' : null,
            'currency' => 'KES',
            'prices_include_tax' => true,
            'subtotal_cents' => 4_500_000,
            'discount_cents' => 250_000,
            'shipping_cents' => 30_000,
            'tax_cents' => 585_862,
            'total_cents' => 4_280_000,
            'coupon_code' => 'WELCOME10',
            'delivery_method' => DeliveryMethod::Delivery,
            'shipping_first_name' => 'Amina',
            'shipping_last_name' => 'Wanjiru',
            'shipping_phone' => '0712345678',
            'shipping_line1' => 'Nyati Road 14',
            'shipping_line2' => 'Apartment 3B',
            'shipping_city' => 'Nairobi',
            'shipping_county' => 'Nairobi',
            'shipping_country_code' => 'KE',
            'placed_at' => now()->subHour(),
            'paid_at' => $paid ? now() : null,
        ]);

        /** @var Collection<int, OrderItem> $items */
        $items = new Collection([
            new OrderItem([
                'name' => 'Ridgeline 18V Combi Drill',
                'option_label' => '2 batteries',
                'quantity' => 1,
                'unit_price_cents' => 3_000_000,
                'total_cents' => 3_000_000,
            ]),
            new OrderItem([
                'name' => 'Ridgeline 42-piece Bit Set',
                'option_label' => null,
                'quantity' => 2,
                'unit_price_cents' => 750_000,
                'total_cents' => 1_500_000,
            ]),
        ]);

        $order->setRelation('items', $items);

        return $order;
    }

    private function sampleUser(): User
    {
        return new User([
            'name' => 'Amina Wanjiru',
            'email' => 'amina@example.com',
        ]);
    }
}
