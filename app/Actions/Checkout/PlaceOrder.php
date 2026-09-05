<?php

namespace App\Actions\Checkout;

use App\Data\CheckoutQuoteData;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\NumberSequence;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Settings\CheckoutSettings;
use App\Settings\TaxSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a priced quote into a placed, unpaid order.
 *
 * The quote handed in is the authority for every figure written: the controller
 * recomputes it immediately before calling this, so nothing here trusts a
 * number that came from the browser.
 *
 * The order comes out pending. No stock moves and no coupon is redeemed —
 * {@see Order::markPaid()} does both, once, when payment confirms. That keeps an
 * abandoned checkout from eating a limited coupon's budget or holding stock
 * nobody paid for.
 */
class PlaceOrder
{
    /** ISO code of the only currency the store trades in. */
    private const CURRENCY = 'KES';

    public function __construct(
        private CheckoutSettings $checkoutSettings,
        private TaxSettings $taxSettings,
    ) {}

    /**
     * @throws ValidationException when the quote cannot legally be ordered
     */
    public function __invoke(
        User $user,
        CheckoutQuoteData $quote,
        DeliveryMethod $delivery,
        ?Address $address = null,
        ?Coupon $coupon = null,
        ?string $customerNote = null,
        ?string $paymentMethod = null,
    ): Order {
        $this->guard($quote, $delivery, $address, $user);

        // Taken before the transaction opens: the sequence holds a row lock
        // while it hands out a number, and holding that across every line
        // insert below would serialise the whole checkout.
        $orderNumber = $this->nextOrderNumber();

        return DB::transaction(function () use (
            $user, $quote, $delivery, $address, $coupon, $customerNote, $orderNumber, $paymentMethod
        ): Order {
            $order = Order::query()->create([
                'order_number' => $orderNumber,
                'user_id' => $user->getKey(),
                // Snapshotted because the account can be deleted: these three
                // are then the only record of who bought this.
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $address?->phone,
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Pending,
                'payment_method' => $paymentMethod,
                // The store trades in one currency. CurrencySettings holds the
                // display symbol, which is not an ISO code, so the code is
                // stated here and matches the column default.
                'currency' => self::CURRENCY,
                'prices_include_tax' => $this->taxSettings->prices_include_tax,
                'subtotal_cents' => $quote->totals->subtotalCents,
                'discount_cents' => $quote->totals->discountCents,
                'shipping_cents' => $quote->totals->shippingCents,
                'tax_cents' => $quote->totals->taxCents,
                'total_cents' => $quote->totals->totalCents,
                'coupon_id' => $coupon?->getKey(),
                'coupon_code' => $coupon?->code,
                'delivery_method' => $delivery,
                'shipping_address_id' => $address?->getKey(),
                'customer_note' => $customerNote,
                'placed_at' => now(),
                ...($address?->toOrderSnapshot() ?? []),
            ]);

            $order->items()->createMany($this->itemRows($quote));

            // No `payments` row is written here. A payment record means "an
            // attempt to collect", and it is the collecting mechanism that
            // creates one — the gateway when it initialises a transaction, or a
            // staff member when they bank a transfer. What the order is owed is
            // already stated by the order itself.
            //
            // It also matters that a reference is never reused or rewritten: if
            // a shopper pays, then re-opens the gateway before the webhook
            // lands, a mutated reference would leave the first payment with
            // nothing to match against and real money unrecorded.
            return $order;
        });
    }

    /**
     * The rows for `order_items`, straight off the quote.
     *
     * Everything needed to render the line later is copied out: the catalog is
     * free to change afterwards without altering what this order says was sold.
     *
     * @return list<array<string, mixed>>
     */
    private function itemRows(CheckoutQuoteData $quote): array
    {
        return array_map(fn ($line): array => [
            'product_id' => $line->productId,
            'product_variant_id' => $line->variantId,
            'name' => $line->name,
            'sku' => $line->sku,
            'option_label' => $line->optionLabel,
            'quantity' => $line->quantity,
            'unit_price_cents' => $line->unitPriceCents,
            'subtotal_cents' => $line->subtotalCents,
            'discount_cents' => $line->discountCents,
            'tax_rate' => $line->taxRate,
            'tax_cents' => $line->taxCents,
            'total_cents' => $line->totalCents,
            'product_snapshot' => [
                'slug' => $line->slug,
                'brandName' => $line->brandName,
                'image' => $line->image?->toArray(),
            ],
        ], $quote->lines);
    }

    /**
     * Refuse anything the quote or the request says cannot be ordered.
     *
     * These are all conditions the form request has already checked; this is the
     * backstop for a caller that skipped it, not the shopper-facing path, so the
     * messages are terse.
     */
    private function guard(CheckoutQuoteData $quote, DeliveryMethod $delivery, ?Address $address, User $user): void
    {
        if ($quote->lines === []) {
            throw ValidationException::withMessages([
                'cart' => __('Your cart is empty.'),
            ]);
        }

        if ($quote->blockers !== []) {
            throw ValidationException::withMessages([
                'cart' => $quote->blockers,
            ]);
        }

        if (! $delivery->requiresAddress()) {
            return;
        }

        if ($address === null) {
            throw ValidationException::withMessages([
                'address_id' => __('Choose where this order should be delivered.'),
            ]);
        }

        if ($address->user_id !== $user->getKey()) {
            throw ValidationException::withMessages([
                'address_id' => __('That address is not yours.'),
            ]);
        }
    }

    /** `SS-000001`, from the store's configured prefix and the shared counter. */
    private function nextOrderNumber(): string
    {
        return $this->checkoutSettings->order_prefix
            .str_pad((string) NumberSequence::next('order'), 6, '0', STR_PAD_LEFT);
    }
}
