<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Services\Paystack\PaystackPaymentService;
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
 * A row is written by whatever is doing the collecting — one per attempt, by
 * {@see PaystackPaymentService::initialize()} — never at
 * placement. What an order is owed is stated by the order itself; a payment row
 * means somebody tried to take the money.
 *
 * `reference` is ours, unique, and written once. It is the idempotency key
 * shared by the browser's verify call and the asynchronous webhook — whichever
 * arrives first settles the payment, and the other finds it already final.
 * Rewriting or reusing one would leave a settled transaction with nothing to
 * match against, and real money unrecorded.
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

    /** Set by {@see withAccessCode()}; never a column, never serialised. */
    protected ?string $accessCode = null;

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

    /**
     * Carry the gateway's access code back to the caller in memory only.
     *
     * The code is what the inline popup resumes. It is a short-lived credential
     * that grants a payment session, so it is deliberately never persisted and
     * never serialised onto a model that might be cast to an array — it travels
     * from the service to the page that opens the popup and no further.
     */
    public function withAccessCode(string $accessCode): static
    {
        $this->accessCode = $accessCode;

        return $this;
    }

    public function accessCode(): ?string
    {
        return $this->accessCode;
    }
}
