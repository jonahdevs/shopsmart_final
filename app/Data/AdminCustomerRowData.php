<?php

namespace App\Data;

use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row in the admin customers table.
 *
 * Carries only what the table renders. The two figures staff sort on —
 * `orderCount` and `lifetimeSpentCents` — arrive as query aggregates rather
 * than loaded relations, because a page of 25 customers would otherwise hydrate
 * every order any of them ever placed.
 *
 * Lifetime spend counts paid orders only. An order sitting unpaid in a basket
 * is not money the store has taken, and staff read this column as "what this
 * person is worth".
 *
 * Nothing here is a credential: the password, two-factor and passkey columns
 * never leave the model, and no admin page has any use for them.
 */
#[TypeScript]
class AdminCustomerRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        /** Null while the customer has not confirmed their email address. */
        public ?string $emailVerifiedAt,
        public int $orderCount,
        public int $lifetimeSpentCents,
        public string $lifetimeSpentFormatted,
        /** Null for a customer who has registered but never ordered. */
        public ?string $lastOrderAt,
        public string $registeredAt,
    ) {}

    public static function fromModel(User $customer): self
    {
        $spent = (int) ($customer->getAttribute('lifetime_spent_cents') ?? 0);

        // A MAX() aggregate comes back as a driver-formatted string rather than
        // a cast Carbon, and SQLite and MySQL do not agree on that format — so
        // it is parsed here rather than being handed to the client raw.
        $lastOrderAt = $customer->getAttribute('last_order_at');
        $lastOrderAt = is_string($lastOrderAt) && $lastOrderAt !== ''
            ? Carbon::parse($lastOrderAt)->toIso8601String()
            : null;

        return new self(
            id: $customer->getKey(),
            name: $customer->name,
            email: $customer->email,
            emailVerifiedAt: $customer->email_verified_at?->toIso8601String(),
            // Both set by the aggregates in CustomerController::index(); absent
            // when a caller hands over a plain model, which the table never does.
            orderCount: (int) ($customer->getAttribute('orders_count') ?? 0),
            lifetimeSpentCents: $spent,
            lifetimeSpentFormatted: money($spent),
            lastOrderAt: $lastOrderAt,
            registeredAt: $customer->created_at?->toIso8601String() ?? '',
        );
    }
}
