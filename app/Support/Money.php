<?php

namespace App\Support;

use App\Settings\CurrencySettings;

/**
 * Single source of truth for rendering money amounts on the storefront and
 * admin. Every monetary column is stored as integer cents; this honours the
 * store-wide {@see CurrencySettings} (symbol, placement, decimals and
 * separators).
 *
 * Most columns carry a `_cents` suffix, but the catalog tables predate that
 * convention: `products.price` / `sale_price` / `cost_price` and their
 * `product_variants` counterparts are cents too, despite reading like major
 * units.
 */
class Money
{
    /**
     * A plain space separates the symbol from the amount.
     *
     * The Blade-era implementation used a non-breaking space (U+00A0), which
     * forced every call site into an unescaped echo. This app is Inertia/Vue:
     * formatted strings travel as JSON props and Vue escapes them on render, so
     * a regular space keeps the payload clean and diffable.
     */
    private const SPACE = ' ';

    /** Minor units per major unit — cents to the currency's base unit. */
    private const MINOR_UNITS = 100;

    public function __construct(private CurrencySettings $settings) {}

    /**
     * Format an integer cents amount into a display string, e.g. "KES 24,000".
     */
    public function format(int $cents): string
    {
        $amount = number_format(
            $cents / self::MINOR_UNITS,
            $this->settings->decimals,
            $this->settings->decimal_separator,
            $this->settings->thousand_separator,
        );

        return $this->settings->symbol_position === 'after'
            ? $amount.self::SPACE.$this->settings->symbol
            : $this->settings->symbol.self::SPACE.$amount;
    }

    /**
     * Convert a major-unit amount (what a human types into a form) into the
     * integer cents used for storage. e.g. toMinor('24000.50') => 2400050.
     */
    public function toMinor(string|float $major): int
    {
        return (int) round(((float) $major) * self::MINOR_UNITS);
    }

    /**
     * Convert stored integer cents back into a major-unit float, for form
     * inputs and API payloads that expect a decimal. e.g. toMajor(2400050)
     * => 24000.5.
     */
    public function toMajor(int $cents): float
    {
        return $cents / self::MINOR_UNITS;
    }
}
