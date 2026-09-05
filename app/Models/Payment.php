<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One attempt to collect the money for an {@see Order}.
 *
 * A pending row is created at placement carrying the reference we will hand to
 * the gateway; phase 5 fills in the rest when Paystack answers. `reference` is
 * unique because it is the idempotency key shared by the browser's verify call
 * and the asynchronous webhook — whichever arrives first settles the payment,
 * and the other finds it already final.
 *
 * `amount_cents` is frozen at creation and is what a verification is checked
 * against, never the live order total: that is what stops an order edit between
 * initiation and settlement from being exploitable.
 *
 * @property int $id
 * @property int $order_id
 * @property string $reference
 * @property string $gateway
 * @property PaymentStatus $status
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $channel
 * @property string|null $gateway_reference
 * @property string|null $authorization_code
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order|null $order
 */
#[Fillable([
    'order_id', 'reference', 'gateway', 'status', 'amount_cents', 'currency',
    'channel', 'gateway_reference', 'authorization_code', 'failure_reason',
    'payload', 'paid_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            // Encrypted at rest: a gateway response carries the payer's name,
            // phone and masked instrument.
            'payload' => 'encrypted:array',
            'paid_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount_cents', 'gateway', 'channel', 'paid_at'])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('payment');
    }

    // ==================================================
    // RELATIONSHIPS
    // ==================================================

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ==================================================
    // SCOPES
    // ==================================================

    /**
     * Payments that have not reached a terminal state.
     *
     * @param  Builder<Payment>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', PaymentStatus::Pending);
    }

    // ==================================================
    // HELPERS
    // ==================================================

    /** Whether this payment can still change state. */
    public function isFinal(): bool
    {
        return $this->status !== PaymentStatus::Pending;
    }
}
