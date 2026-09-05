<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Phase 4 gives the checkout the two numbers it was missing.
 *
 * `checkout.min_order_value` was the only monetary value in the application
 * held in whole KES; every other one is integer cents. It is renamed and
 * converted so the pricing engine never has to remember the exception.
 *
 * `shipping.free_shipping_threshold_cents` shipped in phase 0 with no rate to
 * sit above — there was no delivery fee anywhere in the application — so the
 * flat rate charged below the threshold is added here.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->rename('checkout.min_order_value', 'checkout.min_order_value_cents');
        $this->migrator->update(
            'checkout.min_order_value_cents',
            fn (int $wholeCurrencyUnits): int => $wholeCurrencyUnits * 100,
        );

        // KES 300. Charged on delivery orders whose post-discount subtotal has
        // not reached `shipping.free_shipping_threshold_cents`. Pickup is free
        // and carries no setting of its own.
        $this->migrator->add('shipping.flat_rate_cents', 30_000);
    }

    public function down(): void
    {
        $this->migrator->delete('shipping.flat_rate_cents');

        $this->migrator->update(
            'checkout.min_order_value_cents',
            fn (int $cents): int => intdiv($cents, 100),
        );
        $this->migrator->rename('checkout.min_order_value_cents', 'checkout.min_order_value');
    }
};
