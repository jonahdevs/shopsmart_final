<?php

use App\Enums\ConsentCategory;
use App\Http\Middleware\TrackVisitor;
use App\Models\Product;
use App\Models\User;
use App\Models\Visitor;
use App\Settings\LegalSettings;
use App\Support\Consent;
use App\Support\StorefrontCache;
use Database\Seeders\PermissionSeeder;

/**
 * The visitor counter and the consent gate on it.
 *
 * "Not tracked" here means no row AND no cookie. A tracking cookie minted for
 * somebody who declined analytics is the whole of the intrusion — the store has
 * marked their browser — even if no row is ever written against it, so both
 * halves are asserted every time.
 *
 * The second point is that a visit is a session, not a page view. A shopper who
 * reads three pages is one line on the dashboard, and the first test walks
 * three pages precisely to prove it.
 */
beforeEach(function () {
    $this->withoutVite();
});

/** The cookie the consent endpoint writes, as the browser sends it back. */
function visitorConsent(string ...$granted): string
{
    return (string) json_encode([
        'granted' => array_values($granted),
        'offered' => ConsentCategory::optionalValues(),
    ]);
}

/**
 * Written directly rather than through the admin screen, so the cached read
 * model has to be dropped here — the same thing PrivacySettingsController does
 * after a save.
 *
 * @param  array<int, string>  $categories
 */
function offerVisitorCategories(array $categories): void
{
    $legal = app(LegalSettings::class);
    $legal->consent_categories = $categories;
    $legal->save();

    StorefrontCache::forgetPrivacy();
}

/** A desktop Chrome on Windows: the ordinary case, spelled out once. */
const DESKTOP_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

test('a consenting visitor is recorded once for the whole session', function () {
    offerVisitorCategories(ConsentCategory::optionalValues());

    $product = Product::factory()->published()->create();

    $this->withCookie(Consent::COOKIE, visitorConsent('analytics'))
        ->withHeader('User-Agent', DESKTOP_AGENT);

    $this->get(route('home'))->assertOk()->assertCookie(TrackVisitor::COOKIE);
    $this->get(route('catalog'))->assertOk();
    $this->get(route('product.show', $product->slug))->assertOk();

    // One line for the visit, not three for the three pages.
    expect(Visitor::query()->count())->toBe(1);
});

test('the first session is new and a browser that comes back is returning', function () {
    offerVisitorCategories(ConsentCategory::optionalValues());

    $this->withCookie(Consent::COOKIE, visitorConsent('analytics'))
        ->withHeader('User-Agent', DESKTOP_AGENT);

    $this->get(route('home'))->assertOk();

    $first = Visitor::query()->sole();
    expect($first->is_new)->toBeTrue();

    // The browser closed and reopened: the session is gone, the tracking cookie
    // survived it. That pair is exactly what "returning" is.
    $this->flushSession();
    $this->withCookie(TrackVisitor::COOKIE, $first->tracking_id);

    $this->get(route('home'))->assertOk();

    $rows = Visitor::query()->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[1]->is_new)->toBeFalse()
        ->and($rows[1]->tracking_id)->toBe($first->tracking_id);
});

test('a visitor who has not answered the banner is not tracked and gets no cookie', function () {
    offerVisitorCategories(ConsentCategory::optionalValues());

    $this->withHeader('User-Agent', DESKTOP_AGENT)
        ->get(route('home'))
        ->assertOk()
        ->assertCookieMissing(TrackVisitor::COOKIE);

    expect(Visitor::query()->count())->toBe(0);
});

test('a visitor who granted only marketing is not tracked and gets no cookie', function () {
    offerVisitorCategories(ConsentCategory::optionalValues());

    $this->withCookie(Consent::COOKIE, visitorConsent('marketing'))
        ->withHeader('User-Agent', DESKTOP_AGENT)
        ->get(route('home'))
        ->assertOk()
        ->assertCookieMissing(TrackVisitor::COOKIE);

    expect(Visitor::query()->count())->toBe(0);
});

test('nobody is tracked when the store does not offer the analytics category', function () {
    // The same gate the measurement tags answer to: a category that is not
    // offered can never be granted, so an answer claiming it is worthless.
    offerVisitorCategories(['marketing']);

    $this->withCookie(Consent::COOKIE, visitorConsent('analytics'))
        ->withHeader('User-Agent', DESKTOP_AGENT)
        ->get(route('home'))
        ->assertOk()
        ->assertCookieMissing(TrackVisitor::COOKIE);

    expect(Visitor::query()->count())->toBe(0);
});

test('a robot is not counted as a visitor', function () {
    offerVisitorCategories(ConsentCategory::optionalValues());

    $this->withCookie(Consent::COOKIE, visitorConsent('analytics'))
        ->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
        ->get(route('home'))
        ->assertOk()
        ->assertCookieMissing(TrackVisitor::COOKIE);

    expect(Visitor::query()->count())->toBe(0);
});

test('staff working in the admin panel are not counted as shop traffic', function () {
    offerVisitorCategories(ConsentCategory::optionalValues());
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->withCookie(Consent::COOKIE, visitorConsent('analytics'))
        ->withHeader('User-Agent', DESKTOP_AGENT)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertCookieMissing(TrackVisitor::COOKIE);

    expect(Visitor::query()->count())->toBe(0);
});

test('the user agent is read into a browser and a platform', function () {
    offerVisitorCategories(ConsentCategory::optionalValues());

    $this->withCookie(Consent::COOKIE, visitorConsent('analytics'))
        ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1')
        ->get(route('home'))
        ->assertOk();

    $visitor = Visitor::query()->sole();

    expect($visitor->platform)->toBe('iPhone')
        ->and($visitor->browser)->toBe('Safari');
});

test('country stays null when nothing trustworthy said otherwise', function () {
    offerVisitorCategories(ConsentCategory::optionalValues());

    // The header is client-supplied until a proxy overwrites it, and no proxy
    // is trusted here — so a visitor cannot type their own country into the
    // store's analytics.
    $this->withCookie(Consent::COOKIE, visitorConsent('analytics'))
        ->withHeader('User-Agent', DESKTOP_AGENT)
        ->withHeader('CF-IPCountry', 'FR')
        ->get(route('home'))
        ->assertOk();

    expect(Visitor::query()->sole()->country)->toBeNull();
});

test('visitor sessions older than the retention window are pruned', function () {
    $legal = app(LegalSettings::class);
    $legal->visitor_retention_days = 30;
    $legal->save();

    $stale = Visitor::factory()->create(['created_at' => now()->subDays(31)]);
    $fresh = Visitor::factory()->create(['created_at' => now()->subDays(29)]);

    $this->artisan('privacy:prune')->assertSuccessful();

    $this->assertDatabaseMissing('visitors', ['id' => $stale->id]);
    $this->assertModelExists($fresh);
});
