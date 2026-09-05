<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Checkout\PlaceOrder;
use App\Data\AddressData;
use App\Data\BreadcrumbData;
use App\Enums\DeliveryMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\PlaceOrderRequest;
use App\Models\Address;
use App\Models\Coupon;
use App\Services\PaymentCredentials;
use App\Settings\ShippingSettings;
use App\Support\CheckoutPricer;
use App\Support\StorefrontSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The last page of the storefront: confirm where it goes, then commit.
 *
 * Nothing the page sends is trusted as money. The quote is recomputed on every
 * request from the session cart and the live catalog, and again inside the
 * request's validation before the order is written — the browser's only say in
 * the total is confirming the one it displayed.
 */
class CheckoutController extends Controller
{
    public function index(Request $request, StorefrontSession $storefront, CheckoutPricer $pricer): Response|RedirectResponse
    {
        $lines = $storefront->cartLines();

        if ($lines === []) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Your cart is empty, so there is nothing to check out.'),
            ]);

            return to_route('cart.index');
        }

        $delivery = $this->deliveryMethod($request);
        $coupon = $this->coupon($storefront);
        $shipping = app(ShippingSettings::class);

        return Inertia::render('shop/Checkout', [
            'quote' => $pricer->quote($lines, $coupon, $delivery),
            'addresses' => $this->addresses($request),
            'deliveryMethod' => $delivery,
            'deliveryMethods' => $this->deliveryMethods($shipping),
            'pickupAddress' => $shipping->pickup_address,
            'paymentMethods' => $this->paymentMethods(),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    public function store(
        PlaceOrderRequest $request,
        StorefrontSession $storefront,
        CheckoutPricer $pricer,
        PlaceOrder $placeOrder,
    ): RedirectResponse {
        $delivery = $request->deliveryMethod();
        $coupon = $this->coupon($storefront);

        // Priced once more, here, so the row that gets written comes from the
        // same computation the request just validated rather than from anything
        // carried over the wire.
        $quote = $pricer->quote($storefront->cartLines(), $coupon, $delivery);

        $order = $placeOrder(
            user: $request->user(),
            quote: $quote,
            delivery: $delivery,
            address: $delivery->requiresAddress() ? $request->address() : null,
            coupon: $coupon,
            customerNote: $request->customerNote(),
            paymentMethod: $request->paymentMethod(),
        );

        // Cleared at placement, not at payment: the order is now the shopper's
        // record of what they are buying, and clearing here means no webhook
        // ever has to reach into a session it does not have.
        $storefront->clearCart();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Order :number placed.', ['number' => $order->order_number]),
        ]);

        // An online payment goes straight to the gateway page; the offline
        // methods have nothing to collect right now, so they land on the order
        // itself, which carries the instructions for what happens next.
        return $request->paymentMethod() === 'paystack'
            ? to_route('payment.show', $order)
            : to_route('orders.show', $order);
    }

    /**
     * The delivery choice for this render.
     *
     * Read from the query string so switching between delivery and collection
     * is a plain link the shopper can go back through, rather than session
     * state that outlives the page.
     */
    private function deliveryMethod(Request $request): DeliveryMethod
    {
        $method = DeliveryMethod::tryFrom((string) $request->query('delivery', ''))
            ?? DeliveryMethod::Delivery;

        return $method === DeliveryMethod::Pickup && ! app(ShippingSettings::class)->local_pickup_enabled
            ? DeliveryMethod::Delivery
            : $method;
    }

    /** The coupon held in session, or null once it no longer resolves. */
    private function coupon(StorefrontSession $storefront): ?Coupon
    {
        $code = $storefront->couponCode();

        return $code === null
            ? null
            : Coupon::query()->where('code', $code)->first();
    }

    /**
     * @return list<AddressData>
     */
    private function addresses(Request $request): array
    {
        return array_values(Address::query()
            ->where('user_id', $request->user()?->getKey())
            ->inPickOrder()
            ->get()
            ->map(fn (Address $address): AddressData => AddressData::fromModel($address))
            ->all());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function deliveryMethods(ShippingSettings $shipping): array
    {
        return array_values(array_filter(
            DeliveryMethod::options(),
            fn (array $option): bool => $option['value'] !== DeliveryMethod::Pickup->value
                || $shipping->local_pickup_enabled,
        ));
    }

    /**
     * The ways this store will take money, in the order they are offered.
     *
     * Paystack sends the shopper on to the gateway page; the offline methods
     * leave the order pending with instructions on it. The same source decides
     * what is offered here and what PlaceOrderRequest will accept, so a method
     * that is switched off cannot be submitted anyway.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    private function paymentMethods(): array
    {
        $settings = app(PaymentCredentials::class);
        $methods = [];

        if ($settings->paystackEnabled()) {
            $methods[] = [
                'value' => 'paystack',
                'label' => __('Card or mobile money'),
                'description' => __('Pay securely with a card, M-Pesa or Airtel Money.'),
            ];
        }

        if ($settings->bankTransferEnabled()) {
            $methods[] = [
                'value' => 'bank_transfer',
                'label' => __('Bank transfer'),
                'description' => __('We will send you our account details to complete the payment.'),
            ];
        }

        if ($settings->cashOnDeliveryEnabled()) {
            $methods[] = [
                'value' => 'cash_on_delivery',
                'label' => __('Pay on delivery'),
                'description' => __('Pay in cash when your order arrives.'),
            ];
        }

        return $methods;
    }

    /**
     * Home / Checkout, and no deeper.
     *
     * StoreBreadcrumbs treats a null slug on any rung past the first as the
     * Categories root, so an intermediate "Cart" rung would link to the wrong
     * place. Two rungs say everything this page needs to; the way back to the
     * cart is a link in the page itself.
     *
     * @return list<BreadcrumbData>
     */
    private function breadcrumbs(): array
    {
        return [
            new BreadcrumbData(name: __('Home'), slug: null),
            new BreadcrumbData(name: __('Checkout'), slug: null),
        ];
    }
}
