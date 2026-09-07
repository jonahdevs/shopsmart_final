<?php

namespace Database\Seeders;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Settings\ShippingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Trading history: staff logins, customers, and four months of orders.
 *
 * Without this the catalog seeds beautifully and every screen that reports on
 * SELLING is empty — the dashboard, orders, payments and customers all render
 * their empty states, which is a poor way to show a store that works.
 *
 * Two things this seeder takes seriously:
 *
 * Orders are back-dated across a window rather than all created "now", because
 * every trend on the dashboard is a comparison between one period and the one
 * before it. A hundred orders sharing a timestamp produce a flat line and a
 * null delta on every tile.
 *
 * Totals are computed from the lines, not invented. `subtotal` is the sum of
 * what sold, shipping follows the store's own free-shipping threshold, and tax
 * is extracted from the total rather than added to it, because
 * `prices_include_tax` is true for this store. An order whose figures do not
 * add up is worse than no order — it makes the admin look broken.
 */
class DemoCommerceSeeder extends Seeder
{
    /** How far back the trading history runs. */
    private const WINDOW_DAYS = 120;

    private const CUSTOMERS = 45;

    private const ORDERS = 190;

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
          Unguarded because this seeder's whole point is back-dating. `created_at`
          and `updated_at` appear in no `#[Fillable]` list — correctly, nothing in
          the application should mass-assign them — so `create()` would silently
          drop them and stack four months of trading onto today.
        */
        Model::unguarded(function (): void {
            $this->seedStore();
        });
    }

    private function seedStore(): void
    {
        $this->createStaff();

        $customers = $this->createCustomers();
        $products = $this->sellableProducts();

        if ($products->isEmpty()) {
            $this->command->warn('No sellable products found — run ProductSeeder first. Skipping orders.');

            return;
        }

        $coupons = Coupon::query()->get();

        for ($i = 0; $i < self::ORDERS; $i++) {
            $this->createOrder($customers, $products, $coupons);
        }

        $this->command->info(sprintf(
            'Seeded %d customers and %d orders across the last %d days.',
            self::CUSTOMERS,
            self::ORDERS,
            self::WINDOW_DAYS,
        ));
    }

    /**
     * One login per seeded role, so the permission-filtered sidebar can
     * actually be demonstrated rather than described.
     */
    private function createStaff(): void
    {
        $accounts = [
            ['Super Admin', 'owner@shopsmart.test', 'Amara Otieno'],
            ['Admin', 'admin@shopsmart.test', 'Brian Kimani'],
            ['Manager', 'manager@shopsmart.test', 'Cynthia Wanjiru'],
            ['Support', 'support@shopsmart.test', 'Dennis Mutiso'],
        ];

        foreach ($accounts as [$role, $email, $name]) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    // Demo credentials for a seeded store, never a deployed one.
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role]);
        }
    }

    /**
     * Customers, registered at points across the window.
     *
     * Spread rather than bulk-created so "new customers" has a shape to plot —
     * and so the previous-period comparison has something on both sides of it.
     *
     * @return Collection<int, User>
     */
    private function createCustomers(): Collection
    {
        return Collection::times(self::CUSTOMERS, function (): User {
            $registeredAt = $this->momentInWindow();

            $customer = User::factory()->create([
                'created_at' => $registeredAt,
                'updated_at' => $registeredAt,
                'email_verified_at' => $registeredAt,
            ]);

            // Most shoppers save one address; a few save a second.
            Address::factory()->isDefault()->for($customer)->create();

            if (fake()->boolean(25)) {
                Address::factory()->for($customer)->create();
            }

            return $customer;
        });
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
     * A point in the trading window, weighted toward the recent end.
     *
     * Squaring a uniform random pulls the distribution toward "now", which is
     * what a growing store's order history looks like — and it means the
     * current period genuinely outperforms the previous one, so the trend
     * arrows on the dashboard point somewhere.
     */
    private function momentInWindow(): CarbonImmutable
    {
        $skewed = fake()->randomFloat(4, 0, 1) ** 2;

        return now()
            ->subDays((int) round($skewed * self::WINDOW_DAYS))
            ->setTime(fake()->numberBetween(7, 21), fake()->numberBetween(0, 59));
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
