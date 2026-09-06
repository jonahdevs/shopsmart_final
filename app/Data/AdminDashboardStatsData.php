<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The tiles across the top of the admin overview.
 *
 * Revenue counts PAID orders only. An order that was placed but never collected
 * is not money the store has, and a dashboard that says otherwise is worse than
 * no dashboard.
 *
 * Each figure carries its previous-period twin so the tile can show a delta
 * without the client doing arithmetic on money.
 */
#[TypeScript]
class AdminDashboardStatsData extends Data
{
    public function __construct(
        public int $revenueCents,
        public string $revenueFormatted,
        public ?float $revenueChangePercent,
        public int $paidOrderCount,
        public ?float $paidOrderChangePercent,
        public int $averageOrderValueCents,
        public string $averageOrderValueFormatted,
        public int $newCustomerCount,
        /** Orders placed but not yet collected. */
        public int $awaitingPaymentCount,
        /** Paid orders that have not been fulfilled yet. */
        public int $awaitingFulfilmentCount,
        /** Tracked products at or below their low-stock threshold. */
        public int $lowStockCount,
        /** The window these figures cover, e.g. "Last 30 days". */
        public string $periodLabel,
    ) {}

    /**
     * Percentage movement between two periods, or null when there is no
     * baseline to compare against — a first month of trading shows no delta
     * rather than "+100%", which would be arithmetic dressed up as insight.
     */
    public static function changePercent(int|float $current, int|float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
