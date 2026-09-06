<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * Discount codes in the admin panel.
 *
 * Two invariants carry the section. Money typed as whole KES must land in the
 * database as integer cents and come back out unchanged, and nothing here may
 * write `used_count` — that counter belongs to Order::recordCouponUse(), which
 * moves it only in step with a `coupon_uses` row.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $this->marketer = User::factory()->create();
    $this->marketer->assignRole('Manager');

    // Support is the seeded role that works orders but holds no
    // `marketing.manage`, so it is the natural proof that `can:` refuses.
    $this->support = User::factory()->create();
    $this->support->assignRole('Support');
});

/**
 * A complete, valid coupon form as a staff member fills it in: whole KES, never
 * cents.
 *
 * @return array<string, mixed>
 */
function couponPayload(array $overrides = []): array
{
    return [
        'code' => 'welcome10',
        'type' => CouponType::Fixed->value,
        'amount' => '500',
        'min_subtotal' => '2500',
        'usage_limit' => 100,
        'usage_limit_per_user' => 1,
        'starts_at' => null,
        'expires_at' => null,
        'is_active' => 'on',
        'description' => 'First-order welcome code.',
        ...$overrides,
    ];
}

describe('index', function () {
    test('it lists coupons with their redemption counts', function () {
        $coupon = Coupon::factory()->create(['code' => 'SUMMER']);
        CouponUse::factory()->create(['coupon_id' => $coupon->id]);

        $this->actingAs($this->marketer)
            ->get(route('admin.coupons.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/coupons/Index')
                ->has('coupons', 1)
                ->where('coupons.0.code', 'SUMMER')
                ->where('coupons.0.redemptionCount', 1));
    });

    test('it narrows the table to codes the checkout would accept', function () {
        $live = Coupon::factory()->create();
        Coupon::factory()->expired()->create();
        Coupon::factory()->inactive()->create();

        $this->actingAs($this->marketer)
            ->get(route('admin.coupons.index', ['state' => 'live']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('coupons', 1)
                ->where('coupons.0.id', $live->id));
    });

    test('it treats a typed percent sign as a literal rather than a wildcard', function () {
        $literal = Coupon::factory()->create(['code' => 'SAVE', 'description' => 'Take 10% off']);
        Coupon::factory()->create(['code' => 'OTHER', 'description' => 'Plain words']);

        $this->actingAs($this->marketer)
            ->get(route('admin.coupons.index', ['search' => '%']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('coupons', 1)
                ->where('coupons.0.id', $literal->id));
    });

    test('it rejects a sort column that is not on the allow list', function () {
        $this->actingAs($this->marketer)
            ->get(route('admin.coupons.index', ['sort' => 'percent']))
            ->assertSessionHasErrors('sort');
    });

    test('it returns 403 for a staff member without marketing.manage', function () {
        $this->actingAs($this->support)
            ->get(route('admin.coupons.index'))
            ->assertForbidden();
    });

    test('it returns 403 for a customer', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.coupons.index'))
            ->assertForbidden();
    });

    test('it redirects a guest to sign in', function () {
        $this->get(route('admin.coupons.index'))->assertRedirect(route('login'));
    });
});

describe('store', function () {
    test('it converts whole KES into integer cents', function () {
        $this->actingAs($this->marketer)
            ->post(route('admin.coupons.store'), couponPayload([
                'amount' => '500',
                'min_subtotal' => '2500.50',
            ]))
            ->assertRedirect();

        $coupon = Coupon::query()->sole();

        expect($coupon->amount_cents)->toBe(50_000)
            ->and($coupon->min_subtotal_cents)->toBe(250_050)
            ->and($coupon->code)->toBe('WELCOME10')
            ->and($coupon->used_count)->toBe(0);
    });

    test('the stored cents round-trip back to the form as whole KES', function () {
        $this->actingAs($this->marketer)
            ->post(route('admin.coupons.store'), couponPayload(['amount' => '1234.56']))
            ->assertRedirect();

        $coupon = Coupon::query()->sole();

        $this->actingAs($this->marketer)
            ->get(route('admin.coupons.edit', $coupon))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/coupons/Edit')
                ->where('coupon.amountCents', 123_456)
                ->where('coupon.amountMajor', 1234.56)
                // A whole number of KES loses its decimal point in JSON, which
                // is exactly what a number input wants back.
                ->where('coupon.minSubtotalMajor', 2500));
    });

    test('it stores a percentage coupon without an amount', function () {
        $this->actingAs($this->marketer)
            ->post(route('admin.coupons.store'), couponPayload([
                'type' => CouponType::Percent->value,
                'amount' => null,
                'percent' => '12.5',
                'max_discount' => '3000',
            ]))
            ->assertRedirect();

        $coupon = Coupon::query()->sole();

        expect($coupon->type)->toBe(CouponType::Percent)
            ->and($coupon->amount_cents)->toBeNull()
            ->and((float) $coupon->percent)->toBe(12.5)
            ->and($coupon->max_discount_cents)->toBe(300_000);
    });

    test('it ignores a used_count smuggled into the payload', function () {
        $this->actingAs($this->marketer)
            ->post(route('admin.coupons.store'), couponPayload(['used_count' => 9999]))
            ->assertRedirect();

        expect(Coupon::query()->sole()->used_count)->toBe(0);
    });

    test('it requires an amount for a fixed coupon', function () {
        $this->actingAs($this->marketer)
            ->post(route('admin.coupons.store'), couponPayload(['amount' => null]))
            ->assertSessionHasErrors('amount');

        expect(Coupon::query()->count())->toBe(0);
    });

    test('it requires a percentage for a percentage coupon', function () {
        $this->actingAs($this->marketer)
            ->post(route('admin.coupons.store'), couponPayload([
                'type' => CouponType::Percent->value,
                'amount' => null,
                'percent' => null,
            ]))
            ->assertSessionHasErrors('percent');
    });

    test('it refuses a percentage above one hundred', function () {
        $this->actingAs($this->marketer)
            ->post(route('admin.coupons.store'), couponPayload([
                'type' => CouponType::Percent->value,
                'amount' => null,
                'percent' => '150',
            ]))
            ->assertSessionHasErrors(['percent' => 'A percentage discount cannot exceed 100%.']);
    });

    test('it refuses a code that punctuation would break in a URL', function () {
        $this->actingAs($this->marketer)
            ->post(route('admin.coupons.store'), couponPayload(['code' => 'SAVE 50%']))
            ->assertSessionHasErrors([
                'code' => 'A code may only use letters, numbers, hyphens and underscores.',
            ]);
    });

    test('it refuses a duplicate code regardless of the case it was typed in', function () {
        Coupon::factory()->create(['code' => 'WELCOME10']);

        $this->actingAs($this->marketer)
            ->post(route('admin.coupons.store'), couponPayload(['code' => 'welcome10']))
            ->assertSessionHasErrors('code');

        expect(Coupon::query()->count())->toBe(1);
    });

    test('it refuses a window that ends before it starts', function () {
        $this->actingAs($this->marketer)
            ->post(route('admin.coupons.store'), couponPayload([
                'starts_at' => '2026-10-01',
                'expires_at' => '2026-09-01',
            ]))
            ->assertSessionHasErrors([
                'expires_at' => 'The end of the window must not fall before its start.',
            ]);
    });

    test('it stores an inactive coupon when the box is unticked', function () {
        $payload = couponPayload();
        unset($payload['is_active']);

        $this->actingAs($this->marketer)
            ->post(route('admin.coupons.store'), $payload)
            ->assertRedirect();

        expect(Coupon::query()->sole()->is_active)->toBeFalse();
    });

    test('it returns 403 for a staff member without marketing.manage', function () {
        $this->actingAs($this->support)
            ->post(route('admin.coupons.store'), couponPayload())
            ->assertForbidden();

        expect(Coupon::query()->count())->toBe(0);
    });

    test('it redirects a guest to sign in', function () {
        $this->post(route('admin.coupons.store'), couponPayload())
            ->assertRedirect(route('login'));
    });
});

describe('show', function () {
    test('it lists the redemption history and what the code has given away', function () {
        $coupon = Coupon::factory()->create(['used_count' => 2]);
        $order = Order::factory()->create(['customer_name' => 'Amina Wanjiru']);

        CouponUse::factory()->create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'discount_cents' => 50_000,
        ]);
        CouponUse::factory()->create([
            'coupon_id' => $coupon->id,
            'discount_cents' => 30_000,
        ]);

        $this->actingAs($this->marketer)
            ->get(route('admin.coupons.show', $coupon))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/coupons/Show')
                ->has('detail.redemptions', 2)
                ->where('detail.discountedTotalCents', 80_000)
                ->where('detail.coupon.usedCount', 2)
                ->where('detail.coupon.redemptionCount', 2));
    });

    test('it names a redeemer from the order snapshot after the account is closed', function () {
        $coupon = Coupon::factory()->create();
        $order = Order::factory()->create([
            'user_id' => null,
            'customer_name' => 'Amina Wanjiru',
        ]);

        CouponUse::factory()->create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => null,
        ]);

        $this->actingAs($this->marketer)
            ->get(route('admin.coupons.show', $coupon))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('detail.redemptions.0.customerName', 'Amina Wanjiru')
                ->where('detail.redemptions.0.customerId', null));
    });

    test('it returns 403 for a staff member without marketing.manage', function () {
        $this->actingAs($this->support)
            ->get(route('admin.coupons.show', Coupon::factory()->create()))
            ->assertForbidden();
    });
});

describe('update', function () {
    test('it rewrites the terms in cents and leaves the redemption counter alone', function () {
        $coupon = Coupon::factory()->create(['code' => 'SUMMER', 'used_count' => 4]);

        $this->actingAs($this->marketer)
            ->put(route('admin.coupons.update', $coupon), couponPayload([
                'code' => 'SUMMER',
                'amount' => '750.25',
                'min_subtotal' => '0',
            ]))
            ->assertRedirect(route('admin.coupons.show', $coupon));

        expect($coupon->refresh()->amount_cents)->toBe(75_025)
            ->and($coupon->min_subtotal_cents)->toBe(0)
            ->and($coupon->used_count)->toBe(4);
    });

    test('it clears the percentage when a coupon is switched to a fixed amount', function () {
        $coupon = Coupon::factory()->percent(15.0, 200_000)->create(['code' => 'FIFTEEN']);

        $this->actingAs($this->marketer)
            ->put(route('admin.coupons.update', $coupon), couponPayload([
                'code' => 'FIFTEEN',
                'type' => CouponType::Fixed->value,
                'amount' => '400',
            ]))
            ->assertRedirect();

        $coupon->refresh();

        expect($coupon->percent)->toBeNull()
            ->and($coupon->max_discount_cents)->toBeNull()
            ->and($coupon->amount_cents)->toBe(40_000);
    });

    test('it lets a coupon keep its own code', function () {
        $coupon = Coupon::factory()->create(['code' => 'SUMMER']);

        $this->actingAs($this->marketer)
            ->put(route('admin.coupons.update', $coupon), couponPayload(['code' => 'SUMMER']))
            ->assertSessionHasNoErrors();
    });

    test('it returns 403 for a staff member without marketing.manage', function () {
        $coupon = Coupon::factory()->create(['code' => 'SUMMER']);

        $this->actingAs($this->support)
            ->put(route('admin.coupons.update', $coupon), couponPayload(['code' => 'CHANGED']))
            ->assertForbidden();

        expect($coupon->refresh()->code)->toBe('SUMMER');
    });
});

describe('destroy', function () {
    test('it deletes a coupon nobody has used', function () {
        $coupon = Coupon::factory()->create();

        $this->actingAs($this->marketer)
            ->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect(route('admin.coupons.index'));

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    });

    test('it deactivates rather than deletes a coupon that has been redeemed', function () {
        $coupon = Coupon::factory()->create(['used_count' => 1]);
        $use = CouponUse::factory()->create(['coupon_id' => $coupon->id]);

        $this->actingAs($this->marketer)
            ->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect(route('admin.coupons.show', $coupon));

        expect($coupon->refresh()->is_active)->toBeFalse()
            ->and($coupon->used_count)->toBe(1);

        $this->assertDatabaseHas('coupon_uses', ['id' => $use->id]);
    });

    test('it returns 403 for a staff member without marketing.manage', function () {
        $coupon = Coupon::factory()->create();

        $this->actingAs($this->support)
            ->delete(route('admin.coupons.destroy', $coupon))
            ->assertForbidden();

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    });
});

describe('the redemption counter', function () {
    /**
     * The invariant the admin panel must never break: `used_count` moves only
     * with a `coupon_uses` row, and only once per order however many times the
     * confirmation is replayed.
     */
    test('it stays in step with the coupon_uses rows across an admin edit and a replayed payment', function () {
        $coupon = Coupon::factory()->create(['code' => 'SUMMER']);
        $order = Order::factory()->create([
            'coupon_id' => $coupon->id,
            'coupon_code' => 'SUMMER',
            'discount_cents' => 50_000,
        ]);

        expect($order->recordCouponUse())->toBeTrue();
        // The webhook arriving after the browser already confirmed.
        expect($order->recordCouponUse())->toBeFalse();

        $this->actingAs($this->marketer)
            ->put(route('admin.coupons.update', $coupon), couponPayload(['code' => 'SUMMER']))
            ->assertRedirect();

        $coupon->refresh();

        expect($coupon->used_count)->toBe(1)
            ->and(CouponUse::query()->where('coupon_id', $coupon->id)->count())->toBe(1);
    });
});
