<?php

namespace Database\Seeders;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Settings\ShippingSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\SeedsDemoHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Four months of trading: orders, their lines, and the payment attempts behind
 * them.
 *
 * Runs last, because it sells the catalog to the customer list and discounts
 * with the coupons — every one of which has to exist first.
 *
 * Two things this seeder takes seriously.
 *
 * Orders are back-dated across the window rather than all created "now",
 * because every trend on the admin dashboard is a comparison between one period
 * and the one before it. A hundred orders sharing a timestamp produce a flat
 * line and a null delta on every tile.
 *
 * Totals are computed from the lines, not invented. `subtotal` is the sum of
 * what sold, shipping follows the store's own free-shipping threshold, and tax
 * is extracted from the total rather than added to it, because
 * `prices_include_tax` is true for this store. An order whose figures do not
 * add up is worse than no order — it makes the admin look broken, and every
 * screen reports the discrepancy faithfully.
 */
class OrderSeeder extends Seeder
{
    use SeedsDemoHistory;

    /** Orders spread across the whole customer list. */
    private const ORDERS = 190;

    /** Orders guaranteed to the named demo shopper, so their account is furnished. */
    private const DEMO_SHOPPER_ORDERS = 9;

    /** VAT is extracted from tax-inclusive prices at this rate. */
    private const TAX_DIVISOR = 1.16;

    /**
     * The status mix, weighted the way a real store's ledger looks: mostly
     * completed, a working tail of in-flight orders, and a realistic minority
     * that never became money.
     *
     * @var array<string, int>
     */
    private const STATUS_WEIGHTS = [
        'completed' => 44,
        'processing' => 13,
        'out_for_delivery' => 8,
        'pending' => 15,
        'cancelled' => 12,
        'refunded' => 8,
    ];

    /** @var array<string, int> */
    private const METHOD_WEIGHTS = [
        'paystack' => 72,
        'bank_transfer' => 18,
        'cash_on_delivery' => 10,
    ];

    public function run(): void
    {
        /*
          Unguarded because back-dating is the point. `created_at` and
          `updated_at` appear in no `#[Fillable]` list — correctly, nothing in
          the application should mass-assign them — so `create()` would silently
          drop them and stack four months of trading onto today.
        */
        Model::unguarded(function (): void {
            $this->seedOrders();
        });
    }

    private function seedOrders(): void
    {
        $products = $this->sellableProducts();

        if ($products->isEmpty()) {
            $this->command->warn('No sellable products found — run ProductSeeder first. Skipping orders.');

            return;
        }

        /*
          Everyone except the untouched shopper, whose whole reason for existing
          is to show what the account area looks like before you have bought
          anything.
        */
        $customers = User::query()
            ->whereDoesntHave('roles')
            ->where('email', '!=', UserSeeder::NEW_SHOPPER_EMAIL)
            ->get();

        if ($customers->isEmpty()) {
            $this->command->warn('No customers found — run UserSeeder first. Skipping orders.');

            return;
        }

        $coupons = Coupon::query()->get();

        for ($i = 0; $i < self::ORDERS; $i++) {
            $this->createOrder($customers, $products, $coupons);
        }

        /*
          Random assignment across forty-six shoppers leaves the named one with
          a handful of orders on a good run and none on a bad one — and an empty
          account is exactly what that login exists to avoid. So it gets its own
          guaranteed run afterwards.
        */
        $shopper = $customers->firstWhere('email', UserSeeder::DEMO_SHOPPER_EMAIL);

        if ($shopper !== null) {
            for ($i = 0; $i < self::DEMO_SHOPPER_ORDERS; $i++) {
                $this->createOrder(new Collection([$shopper]), $products, $coupons);
            }
        }

        $this->command->info(sprintf(
            'Seeded %d orders across the last %d days.',
            self::ORDERS + ($shopper === null ? 0 : self::DEMO_SHOPPER_ORDERS),
            self::WINDOW_DAYS,
        ));
    }

    /**
     * Products that can actually be sold: published, in stock, and priced.
     *
     * A line item priced at zero would drag every average on the dashboard
     * down, so price-on-application products are excluded rather than
     * defaulted to nothing.
     *
     * @return Collection<int, Product>
     */
    private function sellableProducts(): Collection
    {
        return Product::query()
            ->published()
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->inRandomOrder()
            ->limit(120)
            ->get();
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Coupon>  $coupons
     */
    private function createOrder(
        Collection $customers,
        Collection $products,
        Collection $coupons,
    ): void {
        $customer = $customers->random();
        $placedAt = $this->momentInWindow();

        // An order cannot predate the account that placed it. Rebuilt through
        // CarbonImmutable because `created_at` is typed as the mutable Carbon,
        // and letting that leak here would widen every date this method passes on.
        if ($placedAt->lt($customer->created_at)) {
            $placedAt = CarbonImmutable::instance($customer->created_at)
                ->addHours(fake()->numberBetween(1, 72));
        }

        $status = OrderStatus::from($this->weighted(self::STATUS_WEIGHTS));
        $isPickup = fake()->boolean(18);
        $lines = $products->random(fake()->numberBetween(1, 4));

        $address = $customer->addresses()->where('is_default', true)->first();

        $order = Order::query()->create([
            'order_number' => 'SS-'.Str::upper(Str::random(8)),
            'user_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $address->phone ?? '07'.fake()->numerify('########'),
            'status' => $status,
            'payment_status' => PaymentStatus::Pending,
            'currency' => 'KES',
            'prices_include_tax' => true,
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 0,
            'delivery_method' => $isPickup ? DeliveryMethod::Pickup : DeliveryMethod::Delivery,
            'shipping_address_id' => $isPickup ? null : $address?->getKey(),
            'shipping_first_name' => $isPickup ? null : ($address->first_name ?? $customer->name),
            'shipping_last_name' => $isPickup ? null : $address?->last_name,
            'shipping_phone' => $isPickup ? null : $address?->phone,
            'shipping_line1' => $isPickup ? null : ($address->line1 ?? fake()->streetAddress()),
            'shipping_city' => $isPickup ? null : ($address->city ?? 'Nairobi'),
            'shipping_county' => $isPickup ? null : ($address->county ?? 'Nairobi'),
            'shipping_postal_code' => $isPickup ? null : $address?->postal_code,
            'shipping_country_code' => $isPickup ? null : 'KE',
            'customer_note' => fake()->boolean(12) ? fake()->sentence() : null,
            'staff_note' => fake()->boolean(8) ? 'Called customer to confirm the delivery window.' : null,
            'placed_at' => $placedAt,
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
        ]);

        $subtotal = 0;

        foreach ($lines as $product) {
            $quantity = fake()->numberBetween(1, 3);
            $unitPrice = (int) $product->effectivePriceCents();
            $lineTotal = $unitPrice * $quantity;
            $subtotal += $lineTotal;

            OrderItem::query()->create([
                'order_id' => $order->getKey(),
                'product_id' => $product->getKey(),
                'product_variant_id' => null,
                'name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $quantity,
                'unit_price_cents' => $unitPrice,
                'subtotal_cents' => $lineTotal,
                'discount_cents' => 0,
                'tax_rate' => 16,
                'tax_cents' => $this->taxWithin($lineTotal),
                'total_cents' => $lineTotal,
                'product_snapshot' => ['slug' => $product->slug],
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);
        }

        [$discount, $couponId, $couponCode] = $this->applyCoupon($coupons, $subtotal);

        $shipping = $this->shippingFor($isPickup, $subtotal - $discount);
        $total = $subtotal - $discount + $shipping;

        $order->forceFill([
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'shipping_cents' => $shipping,
            'tax_cents' => $this->taxWithin($subtotal - $discount),
            'total_cents' => $total,
            'coupon_id' => $couponId,
            'coupon_code' => $couponCode,
        ]);

        $this->settle($order, $status, $placedAt, $total);

        $order->save();
    }

    /**
     * Move the order into a payment state consistent with its status, and write
     * the payment attempt that produced it.
     *
     * The rule that matters: an order is only paid if its status says money
     * changed hands. A cancelled order that carries `paid_at` would inflate
     * revenue on every screen that reports it.
     */
    private function settle(Order $order, OrderStatus $status, CarbonImmutable $placedAt, int $total): void
    {
        $paidStatuses = [
            OrderStatus::Processing,
            OrderStatus::OutForDelivery,
            OrderStatus::Completed,
            OrderStatus::Refunded,
        ];

        if (! in_array($status, $paidStatuses, true)) {
            $order->forceFill([
                'payment_status' => $status === OrderStatus::Cancelled
                    ? PaymentStatus::Cancelled
                    : PaymentStatus::Pending,
                'cancelled_at' => $status === OrderStatus::Cancelled
                    ? $placedAt->copy()->addHours(fake()->numberBetween(2, 48))
                    : null,
            ]);

            // A cancelled order often has a failed attempt behind it — that is
            // usually WHY it was cancelled, and the payments screen should
            // show the attempt rather than nothing.
            if ($status === OrderStatus::Cancelled && fake()->boolean(60)) {
                $this->payment($order, $total, PaymentStatus::Failed, $placedAt);
            }

            return;
        }

        $method = $this->weighted(self::METHOD_WEIGHTS);
        $paidAt = $placedAt->copy()->addMinutes(fake()->numberBetween(3, 2_880));

        $order->forceFill([
            'payment_status' => $status === OrderStatus::Refunded
                ? PaymentStatus::Refunded
                : PaymentStatus::Success,
            'payment_method' => $method,
            'paid_at' => $paidAt,
            'stock_deducted_at' => $paidAt,
        ]);

        $this->payment(
            $order,
            $total,
            $status === OrderStatus::Refunded ? PaymentStatus::Refunded : PaymentStatus::Success,
            $paidAt,
            $method,
        );
    }

    private function payment(
        Order $order,
        int $total,
        PaymentStatus $status,
        CarbonImmutable $at,
        string $method = 'paystack',
    ): void {
        Payment::query()->create([
            'order_id' => $order->getKey(),
            'reference' => 'SS-'.Str::upper(Str::random(10)),
            'gateway' => $method === 'paystack' ? 'paystack' : $method,
            'status' => $status,
            'amount_cents' => $total,
            'currency' => 'KES',
            'channel' => $method === 'paystack'
                ? fake()->randomElement(['card', 'mobile_money'])
                : null,
            'gateway_reference' => $status === PaymentStatus::Pending
                ? null
                : (string) fake()->numberBetween(1_000_000, 9_999_999),
            'failure_reason' => $status === PaymentStatus::Failed
                ? 'Declined by issuer.'
                : null,
            'paid_at' => in_array($status, [PaymentStatus::Success, PaymentStatus::Refunded], true)
                ? $at
                : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /**
     * Occasionally attach a coupon, discounting by its own rule.
     *
     * @param  Collection<int, Coupon>  $coupons
     * @return array{0: int, 1: int|null, 2: string|null}
     */
    private function applyCoupon(Collection $coupons, int $subtotal): array
    {
        if ($coupons->isEmpty() || ! fake()->boolean(18)) {
            return [0, null, null];
        }

        $coupon = $coupons->random();
        $discount = $coupon->discountFor($subtotal);

        if ($discount <= 0) {
            return [0, null, null];
        }

        return [$discount, $coupon->getKey(), $coupon->code];
    }

    /**
     * Pickup is always free; delivery is the flat rate until the order reaches
     * the store's single free-shipping threshold. Read from settings rather
     * than hardcoded, because that threshold living in more than one place is
     * a bug this project has already fixed once.
     */
    private function shippingFor(bool $isPickup, int $payable): int
    {
        if ($isPickup) {
            return 0;
        }

        $settings = app(ShippingSettings::class);

        return $payable >= $settings->free_shipping_threshold_cents
            ? 0
            : $settings->flat_rate_cents;
    }

    /** VAT already inside a tax-inclusive amount. */
    private function taxWithin(int $cents): int
    {
        return (int) round($cents - ($cents / self::TAX_DIVISOR));
    }

    /**
     * Pick a key from a weight map.
     *
     * @param  array<string, int>  $weights
     */
    private function weighted(array $weights): string
    {
        $roll = fake()->numberBetween(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $key;
            }
        }

        return (string) array_key_first($weights);
    }
}
