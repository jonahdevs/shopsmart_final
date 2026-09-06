<?php

use App\Models\TaxClass;
use App\Models\User;
use App\Settings\AnalyticsSettings;
use App\Settings\BusinessSettings;
use App\Settings\CheckoutSettings;
use App\Settings\LegalSettings;
use App\Settings\LocalizationSettings;
use App\Settings\PaymentApiSettings;
use App\Settings\ReviewSettings;
use App\Settings\SeoSettings;
use App\Settings\ShippingSettings;
use App\Settings\TaxSettings;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * The seven store settings screens.
 *
 * Two things are being pinned here. The first is the permission boundary:
 * `staff` opens the door and `can:settings.manage` decides who may walk through
 * it, so a Manager — who is unquestionably staff — must still be refused.
 *
 * The second is that a value typed into a form comes back out of the settings
 * repository unchanged. That is not as obvious as it sounds: money crosses a
 * cents boundary on the way in, two fields are encrypted at rest, and one is
 * deliberately never sent back to the browser at all.
 */
beforeEach(function () {
    $this->withoutVite();

    $this->seed(PermissionSeeder::class);
});

/** A staff member who may manage settings. */
function settingsManager(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole('Admin'));
}

/** Staff, but with no claim on system configuration. */
function tradingManager(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole('Manager'));
}

/** Re-read a settings group from the database rather than the container copy. */
function reloaded(string $settingsClass): object
{
    return app($settingsClass)->refresh();
}

/** The raw stored payload for one setting, straight out of the table. */
function storedPayload(string $group, string $name): string
{
    return (string) DB::table('settings')
        ->where('group', $group)
        ->where('name', $name)
        ->value('payload');
}

/** @return array<string, mixed> */
function businessPayload(): array
{
    return [
        'legal_name' => 'Acme Trading Ltd',
        'registration_number' => 'PVT-9001',
        'tax_pin' => 'A001234567X',
        'contact_email' => 'hello@acme.test',
        'contact_phone' => '+254700111222',
        'address' => '4th Floor, Rahimtulla Tower, Nairobi',
        'business_hours' => 'Mon-Fri 8am-6pm',
        'currency' => 'usd',
        'weight_unit' => 'kg',
        'dimension_unit' => 'cm',
        'timezone' => 'Africa/Nairobi',
        'symbol' => '$',
        'symbol_position' => 'after',
        'decimals' => 2,
        'thousand_separator' => ',',
        'decimal_separator' => '.',
    ];
}

test('every settings screen renders for a staff member who may manage settings', function (string $route, string $component) {
    $response = $this->actingAs(settingsManager())->get(route($route));

    $response->assertInertia(fn (AssertableInertia $page) => $page->component($component));
})->with([
    'business' => ['admin.settings.business', 'admin/settings/Business'],
    'branding' => ['admin.settings.branding', 'admin/settings/Branding'],
    'catalog' => ['admin.settings.catalog', 'admin/settings/Catalog'],
    'checkout' => ['admin.settings.checkout', 'admin/settings/Checkout'],
    'shipping' => ['admin.settings.shipping', 'admin/settings/Shipping'],
    'seo' => ['admin.settings.seo', 'admin/settings/Seo'],
    'privacy' => ['admin.settings.privacy', 'admin/settings/Privacy'],
]);

test('a staff member without settings.manage is refused every settings screen', function (string $route) {
    $this->actingAs(tradingManager())->get(route($route))->assertForbidden();
})->with([
    'admin.settings.business',
    'admin.settings.branding',
    'admin.settings.catalog',
    'admin.settings.checkout',
    'admin.settings.shipping',
    'admin.settings.seo',
    'admin.settings.privacy',
]);

test('a staff member without settings.manage cannot save settings either', function () {
    $this->actingAs(tradingManager())
        ->put(route('admin.settings.business.update'), businessPayload())
        ->assertForbidden();

    expect(reloaded(BusinessSettings::class)->legal_name)->not->toBe('Acme Trading Ltd');
});

test('a customer is refused the settings screens', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.settings.business'))
        ->assertForbidden();
});

test('a guest is sent to sign in rather than refused', function () {
    $this->get(route('admin.settings.business'))->assertRedirect(route('login'));
});

test('business settings save and reload', function () {
    $response = $this->actingAs(settingsManager())
        ->put(route('admin.settings.business.update'), businessPayload());

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.settings.business'));

    $business = reloaded(BusinessSettings::class);

    expect($business->legal_name)->toBe('Acme Trading Ltd')
        ->and($business->contact_email)->toBe('hello@acme.test')
        ->and($business->tax_pin)->toBe('A001234567X');

    // Typed lowercase, stored as the ISO code it is.
    expect(reloaded(LocalizationSettings::class)->currency)->toBe('USD');
});

test('the tax PIN is encrypted at rest', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.business.update'), businessPayload())
        ->assertSessionHasNoErrors();

    expect(storedPayload('business', 'tax_pin'))->not->toContain('A001234567X');
    expect(reloaded(BusinessSettings::class)->tax_pin)->toBe('A001234567X');
});

test('business settings reject a missing name and a bad timezone', function () {
    $payload = array_merge(businessPayload(), ['legal_name' => '', 'timezone' => 'Mars/Olympus']);

    $this->actingAs(settingsManager())
        ->put(route('admin.settings.business.update'), $payload)
        ->assertSessionHasErrors(['legal_name', 'timezone']);
});

test('the minimum order value round-trips through cents', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.checkout.update'), [
            'min_order_value' => '1500',
            'order_prefix' => 'AC-',
            'guest_checkout_enabled' => '1',
            'paystack_enabled' => '1',
            'bank_details' => 'Acme Bank 0123456789',
        ])
        ->assertSessionHasNoErrors();

    expect(reloaded(CheckoutSettings::class)->min_order_value_cents)->toBe(150000);

    $this->actingAs(settingsManager())
        ->get(route('admin.settings.checkout'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('checkout.min_order_value', 1500));
});

test('unticked checkboxes save as false rather than failing validation', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.checkout.update'), [
            'min_order_value' => '0',
            'order_prefix' => 'AC-',
        ])
        ->assertSessionHasNoErrors();

    expect(reloaded(CheckoutSettings::class)->guest_checkout_enabled)->toBeFalse();
});

test('the Paystack secret key is never sent to the browser and survives a blank field', function () {
    $api = app(PaymentApiSettings::class);
    $api->paystack_secret_key = 'sk_live_supersecret';
    $api->save();

    $this->actingAs(settingsManager())
        ->get(route('admin.settings.checkout'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('paymentApi.paystack_secret_key_set', true)
            ->missing('paymentApi.paystack_secret_key')
        )
        ->assertDontSee('sk_live_supersecret');

    $this->actingAs(settingsManager())
        ->put(route('admin.settings.checkout.update'), [
            'min_order_value' => '0',
            'order_prefix' => 'AC-',
            'paystack_public_key' => 'pk_live_public',
            'paystack_secret_key' => '',
        ])
        ->assertSessionHasNoErrors();

    expect(reloaded(PaymentApiSettings::class)->paystack_secret_key)->toBe('sk_live_supersecret');
});

test('a typed Paystack secret key replaces the stored one and stays encrypted', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.checkout.update'), [
            'min_order_value' => '0',
            'order_prefix' => 'AC-',
            'paystack_secret_key' => 'sk_live_rotated',
        ])
        ->assertSessionHasNoErrors();

    expect(reloaded(PaymentApiSettings::class)->paystack_secret_key)->toBe('sk_live_rotated');
    expect(storedPayload('payment_api', 'paystack_secret_key'))->not->toContain('sk_live_rotated');
});

test('shipping money fields round-trip through cents', function () {
    $taxClass = TaxClass::factory()->create();

    $this->actingAs(settingsManager())
        ->put(route('admin.settings.shipping.update'), [
            'flat_rate' => '450',
            'free_shipping_threshold' => '20000',
            'pickup_address' => 'Karen, Nairobi',
            'local_pickup_enabled' => '1',
            'tax_enabled' => '1',
            'default_tax_class_id' => $taxClass->id,
        ])
        ->assertSessionHasNoErrors();

    $shipping = reloaded(ShippingSettings::class);

    expect($shipping->flat_rate_cents)->toBe(45000)
        ->and($shipping->free_shipping_threshold_cents)->toBe(2000000);

    expect(reloaded(TaxSettings::class)->default_tax_class_id)->toBe($taxClass->id);
});

test('an unknown tax class is rejected', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.shipping.update'), [
            'flat_rate' => '0',
            'free_shipping_threshold' => '0',
            'default_tax_class_id' => 9999,
        ])
        ->assertSessionHasErrors('default_tax_class_id');
});

test('catalog settings save the review author format', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.catalog.update'), [
            'low_stock_threshold' => 3,
            'out_of_stock_behavior' => 'hide',
            'reviews_enabled' => '1',
            'author_display_format' => 'full_name',
        ])
        ->assertSessionHasNoErrors();

    $reviews = reloaded(ReviewSettings::class);

    expect($reviews->author_display_format)->toBe('full_name')
        ->and($reviews->auto_approve)->toBeFalse();
});

test('an unknown review author format is rejected', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.catalog.update'), [
            'low_stock_threshold' => 3,
            'out_of_stock_behavior' => 'show',
            'author_display_format' => 'initials_only',
        ])
        ->assertSessionHasErrors('author_display_format');
});

test('a title pattern without a page placeholder is rejected with an explanation', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.seo.update'), [
            'meta_title_pattern' => 'ShopSmart',
            'default_meta_description' => 'Everything, delivered.',
        ])
        ->assertSessionHasErrors([
            'meta_title_pattern' => 'The title pattern must contain the {page} placeholder.',
        ]);

    expect(reloaded(SeoSettings::class)->meta_title_pattern)->toBe('{page} | {site}');
});

test('privacy settings save consent categories, policy URLs and retention windows', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.privacy.update'), [
            'consent_categories' => ['analytics'],
            'privacy_policy_url' => '/privacy',
            'terms_url' => 'https://acme.test/terms',
            'recently_viewed_retention_days' => 30,
            'activity_log_retention_days' => 0,
            'ga4_id' => 'g-abc123',
        ])
        ->assertSessionHasNoErrors();

    $legal = reloaded(LegalSettings::class);

    expect($legal->consent_categories)->toBe(['analytics'])
        ->and($legal->privacy_policy_url)->toBe('/privacy')
        ->and($legal->terms_url)->toBe('https://acme.test/terms')
        ->and($legal->recently_viewed_retention_days)->toBe(30)
        ->and($legal->activity_log_retention_days)->toBe(0);

    expect(reloaded(AnalyticsSettings::class)->ga4_id)->toBe('G-ABC123');
});

test('unticking every consent category is allowed and switches the banner off', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.privacy.update'), [
            'recently_viewed_retention_days' => 180,
            'activity_log_retention_days' => 365,
        ])
        ->assertSessionHasNoErrors();

    expect(reloaded(LegalSettings::class)->consent_categories)->toBe([]);
});

test('a malformed measurement id is rejected with the format it expects', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.privacy.update'), [
            'recently_viewed_retention_days' => 180,
            'activity_log_retention_days' => 365,
            'ga4_id' => 'UA-12345-1',
            'meta_pixel_id' => 'pixel',
        ])
        ->assertSessionHasErrors([
            'ga4_id' => 'A GA4 measurement ID looks like G-XXXXXXXXXX.',
            'meta_pixel_id' => 'A Meta pixel ID is digits only.',
        ]);
});

test('a javascript policy URL is rejected', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.privacy.update'), [
            'recently_viewed_retention_days' => 180,
            'activity_log_retention_days' => 365,
            'privacy_policy_url' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors('privacy_policy_url');
});

test('a retention window beyond ten years is rejected', function () {
    $this->actingAs(settingsManager())
        ->put(route('admin.settings.privacy.update'), [
            'recently_viewed_retention_days' => 4000,
            'activity_log_retention_days' => 365,
        ])
        ->assertSessionHasErrors('recently_viewed_retention_days');
});
