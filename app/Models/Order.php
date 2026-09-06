<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockStatus;
use App\Notifications\OrderPaid;
use App\Notifications\OrderStatusChanged;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A placed order: what was bought, at what price, going where.
 *
 * Every field a customer or an auditor might later need is copied onto the row
 * at placement — the customer's name and email, the destination, the coupon
 * code, whether prices included tax. Products get edited, addresses get deleted
 * and accounts get closed; none of that may alter what this order says was sold.
 *
 * The order is created unpaid. {@see markPaid()} is the single transition that
 * turns it into a sale, and it is deliberately the only place that moves stock
 * or redeems a coupon, so a payment confirmation arriving twice — once from the
 * browser and once from a webhook — settles the order exactly once.
 *
 * @property int $id
 * @property string $order_number
 * @property int|null $user_id Null once the customer deletes their account.
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property string|null $payment_method
 * @property string $currency
 * @property bool $prices_include_tax
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $shipping_cents
 * @property int $tax_cents
 * @property int $total_cents
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property DeliveryMethod $delivery_method
 * @property int|null $shipping_address_id
 * @property string|null $shipping_first_name
 * @property string|null $shipping_last_name
 * @property string|null $shipping_phone
 * @property string|null $shipping_line1
 * @property string|null $shipping_line2
 * @property string|null $shipping_city
 * @property string|null $shipping_county
 * @property string|null $shipping_postal_code
 * @property string|null $shipping_country_code
 * @property string|null $customer_note
 * @property string|null $staff_note
 * @property Carbon $placed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $stock_deducted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Coupon|null $coupon
 * @property-read Address|null $shippingAddress
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, Payment> $payments
 */
#[Fillable([
    'order_number', 'user_id', 'customer_name', 'customer_email', 'customer_phone',
    'status', 'payment_status', 'payment_method', 'currency', 'prices_include_tax',
    'subtotal_cents', 'discount_cents', 'shipping_cents', 'tax_cents', 'total_cents',
    'coupon_id', 'coupon_code', 'delivery_method', 'shipping_address_id',
    'shipping_first_name', 'shipping_last_name', 'shipping_phone', 'shipping_line1',
    'shipping_line2', 'shipping_city', 'shipping_county', 'shipping_postal_code',
    'shipping_country_code', 'customer_note', 'staff_note', 'placed_at', 'paid_at',
    'cancelled_at', 'stock_deducted_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, LogsActivity;

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'prices_include_tax' => 'boolean',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'shipping_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'stock_deducted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_status', 'payment_method', 'total_cents', 'staff_note'])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('order');
    }

    // ==================================================
    // RELATIONSHIPS
    // ==================================================

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<Address, $this> */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ==================================================
    // SCOPES
    // ==================================================

    /**
     * One customer's orders, newest first.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function forCustomer(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId)->orderByDesc('placed_at');
    }

    // ==================================================
    // HELPERS
    // ==================================================

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Success;
    }

    /** Whether the shopper can still be sent to a gateway for this order. */
    public function awaitsPayment(): bool
    {
        return $this->payment_status === PaymentStatus::Pending
            && $this->status !== OrderStatus::Cancelled;
    }

    /**
     * Settle the order: mark it paid, take the stock and redeem the coupon.
     *
     * The guard is a conditional UPDATE rather than a read-then-write, so two
     * confirmations racing each other — the browser's verify call and the
     * gateway's webhook — resolve without a lock: exactly one of them sees a row
     * affected and does the work, the other returns false and does nothing.
     *
     * Returns false when the order was already settled or cancelled.
     */
    public function markPaid(?string $paymentMethod = null): bool
    {
        $settled = static::query()
            ->whereKey($this->getKey())
            ->where('payment_status', PaymentStatus::Pending)
            ->where('status', OrderStatus::Pending)
            ->update([
                'payment_status' => PaymentStatus::Success,
                'status' => OrderStatus::Processing,
                'payment_method' => $paymentMethod ?? $this->payment_method,
                'paid_at' => now(),
                'updated_at' => now(),
            ]);

        if ($settled === 0) {
            return false;
        }

        $this->forceFill([
            'payment_status' => PaymentStatus::Success,
            'status' => OrderStatus::Processing,
            'payment_method' => $paymentMethod ?? $this->payment_method,
            'paid_at' => now(),
        ])->syncOriginal();

        $this->deductStock();
        $this->recordCouponUse();

        // Inside the winning branch on purpose. The loser has already returned
        // false above, so a webhook replaying a confirmation the browser
        // already made cannot send a second receipt.
        $this->notifyCustomer(new OrderPaid($this));

        return true;
    }

    /**
     * Move the order's fulfilment status, telling the customer once.
     *
     * Guarded the same way {@see markPaid()} is, and for the same reason: two
     * staff members marking an order delivered at the same moment must produce
     * one transition and one email, not two.
     *
     * Returns false when the order was already in that status, or when another
     * request moved it out from under this one.
     */
    public function changeStatus(OrderStatus $status): bool
    {
        $previous = $this->status;

        if ($previous === $status) {
            return false;
        }

        $moved = static::query()
            ->whereKey($this->getKey())
            ->where('status', $previous)
            ->update([
                'status' => $status,
                'updated_at' => now(),
                ...($status === OrderStatus::Cancelled ? ['cancelled_at' => now()] : []),
            ]);

        if ($moved === 0) {
            return false;
        }

        $this->forceFill([
            'status' => $status,
            ...($status === OrderStatus::Cancelled ? ['cancelled_at' => now()] : []),
        ])->syncOriginal();

        $this->notifyCustomer(new OrderStatusChanged($this, $previous));

        return true;
    }

    /**
     * Mail the person who placed this order.
     *
     * Silent once the account has been deleted: the order keeps the snapshotted
     * name and email for the record, but a closed account is not a mailbox this
     * store should still be writing to.
     */
    private function notifyCustomer(Notification $notification): void
    {
        $this->loadMissing('user');

        $this->user?->notify($notification);
    }

    /**
     * Take this order's lines out of stock, once.
     *
     * Guarded by `stock_deducted_at` so a replay cannot double-deduct, and each
     * line moves through a conditional UPDATE that refuses to go negative rather
     * than clamping at zero — an oversell should surface, not be swallowed.
     *
     * Lines are handled in product order so concurrent orders take their row
     * locks in the same sequence and cannot deadlock against each other.
     *
     * Products with a null `stock_quantity` are untracked and are skipped.
     */
    public function deductStock(): bool
    {
        $claimed = static::query()
            ->whereKey($this->getKey())
            ->whereNull('stock_deducted_at')
            ->update(['stock_deducted_at' => now(), 'updated_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        $this->loadMissing('items');

        $lines = $this->items->sortBy(fn (OrderItem $item): int => $item->product_id ?? 0);

        foreach ($lines as $item) {
            $this->deductLine($item);
        }

        return true;
    }

    /**
     * Record the coupon redemption, once.
     *
     * `firstOrCreate` leans on the (coupon_id, order_id) unique index rather
     * than a check-then-insert, and the counter is only bumped when a row was
     * actually created, so the two stay in step under a replay.
     */
    public function recordCouponUse(): bool
    {
        if ($this->coupon_id === null || $this->discount_cents <= 0) {
            return false;
        }

        $use = CouponUse::query()->firstOrCreate(
            ['coupon_id' => $this->coupon_id, 'order_id' => $this->getKey()],
            ['user_id' => $this->user_id, 'discount_cents' => $this->discount_cents],
        );

        if (! $use->wasRecentlyCreated) {
            return false;
        }

        Coupon::query()->whereKey($this->coupon_id)->increment('used_count');

        return true;
    }

    /**
     * Take one line's quantity off the variant it sold, or off the product.
     *
     * `stock_status` is written in the same statement as `stock_quantity`
     * because {@see Product::isInStock()} reads only the status — leaving it on
     * "in stock" at a quantity of zero would keep a sold-out product on sale.
     *
     * Money has already changed hands by the time this runs, so a line that
     * oversold cannot be refused; the column is unsigned, so it floors at zero
     * and the shortfall is logged for staff to reconcile. That is the cost of
     * not reserving stock at placement, and it is deliberately noisy.
     */
    private function deductLine(OrderItem $item): void
    {
        $table = $item->product_variant_id !== null ? 'product_variants' : 'products';
        $id = $item->product_variant_id ?? $item->product_id;

        if ($id === null) {
            return;
        }

        $row = DB::table($table)->where('id', $id)->first(['stock_quantity', 'allow_backorder']);

        // Untracked stock, or a row that has since been hard-deleted.
        if ($row === null || $row->stock_quantity === null) {
            return;
        }

        $remaining = (int) $row->stock_quantity - $item->quantity;
        $allowsBackorder = (bool) $row->allow_backorder;

        if ($remaining < 0) {
            Log::warning('Order settled for more stock than was on hand.', [
                'order_number' => $this->order_number,
                'order_item_id' => $item->getKey(),
                'table' => $table,
                'id' => $id,
                'short_by' => abs($remaining),
            ]);
        }

        DB::table($table)->where('id', $id)->update([
            'stock_quantity' => max(0, $remaining),
            'stock_status' => $remaining <= 0 && ! $allowsBackorder
                ? StockStatus::OutOfStock->value
                : StockStatus::InStock->value,
            'updated_at' => now(),
        ]);
    }
}
