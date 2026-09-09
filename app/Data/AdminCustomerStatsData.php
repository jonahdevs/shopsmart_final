<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The tiles above the admin customers table.
 *
 * "Customer" is a user holding no role at all — the same boundary the table
 * draws — so staff accounts are outside every figure here, including the ones
 * measured through orders.
 *
 * Spend counts PAID orders only, matching the lifetime spend column in the
 * table below. Staff read both as "what these people are worth", and a basket
 * that was abandoned at the payment page is not worth anything.
 *
 * The share and the average arrive worked out rather than as the numerator and
 * denominator: the client formats no money and does no arithmetic on it.
 */
#[TypeScript]
class AdminCustomerStatsData extends Data
{
    public function __construct(
        /** Registered customers, all time. */
        public int $customerCount,
        /** Registrations inside the trailing window. */
        public int $newCustomerCount,
        public ?float $newCustomerChangePercent,
        /** Customers who have paid for at least one order. */
        public int $payingCustomerCount,
        /** Those customers as a percentage of all of them; null while there are none. */
        public ?float $payingCustomerSharePercent,
        /** Lifetime paid spend divided across the customers who have paid. */
        public int $averageSpendCents,
        public string $averageSpendFormatted,
        /** The window the registration figures cover, e.g. "Last 30 days". */
        public string $periodLabel,
    ) {}
}
