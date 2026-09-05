<?php

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Seeder;

/**
 * Two demo discount codes, one of each type, so a fresh install has something
 * to type into the checkout.
 *
 * Every column is written out in full. Seeders run with model events muted, so
 * nothing here may rely on a hook or an observer to fill a value in, and the
 * defaults a factory would supply are not applied either.
 */
class CouponSeeder extends Seeder
{
    /** Flat amount off, no strings attached beyond a floor on the order. */
    public const WELCOME_CODE = 'WELCOME500';

    /** A percentage, capped so a large order cannot run away with it. */
    public const BULK_CODE = 'BULK10';

    public function run(): void
    {
        /** @var list<array<string, mixed>> $coupons */
        $coupons = [
            [
                'code' => self::WELCOME_CODE,
                'type' => CouponType::Fixed,
                'amount_cents' => 50_000,
                'percent' => null,
                'min_subtotal_cents' => 300_000,
                'max_discount_cents' => null,
                'usage_limit' => null,
                'usage_limit_per_user' => 1,
                'description' => 'KES 500 off your first order over KES 3,000.',
            ],
            [
                'code' => self::BULK_CODE,
                'type' => CouponType::Percent,
                'amount_cents' => null,
                'percent' => '10',
                'min_subtotal_cents' => 2_000_000,
                'max_discount_cents' => 500_000,
                'usage_limit' => 500,
                'usage_limit_per_user' => null,
                'description' => '10% off orders over KES 20,000, up to KES 5,000.',
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::updateOrCreate(
                ['code' => $coupon['code']],
                [
                    // `used_count` is deliberately not written: the column
                    // defaults to zero on a new row, and re-running the seeder
                    // must not erase redemptions already recorded against it.
                    ...$coupon,
                    'starts_at' => null,
                    'expires_at' => null,
                    'is_active' => true,
                ],
            );
        }

        $this->command->info('Seeded '.count($coupons).' demo discount codes.');
    }
}
