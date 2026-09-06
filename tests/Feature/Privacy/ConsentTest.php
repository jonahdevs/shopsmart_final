<?php

use App\Enums\ConsentCategory;
use App\Settings\AnalyticsSettings;
use App\Settings\LegalSettings;
use App\Support\Consent;
use App\Support\StorefrontCache;

/**
 * The consent gate on the measurement tags.
 *
 * The point of these tests is that "not tracked" means the vendor's script is
 * never written into the document at all — not that it loads and then behaves.
 * A tag that reaches the browser has already been fetched from Google or Meta
 * and has already set whatever it sets, so asserting on the HTML the server
 * produced is asserting on the only moment the decision can still be made.
 *
 * The ids below are deliberately distinctive strings: `assertDontSee` on them
 * catches the tag leaking through any route, including the JSON island the
 * banner configures itself from.
 */
const GA4_ID = 'G-SHOPSMART1';
const GTM_ID = 'GTM-SHOPSMART';
const PIXEL_ID = '1234509876543210';

beforeEach(function () {
    $this->withoutVite();

    $analytics = app(AnalyticsSettings::class);
    $analytics->ga4_id = GA4_ID;
    $analytics->gtm_id = GTM_ID;
    $analytics->meta_pixel_id = PIXEL_ID;
    $analytics->save();

    StorefrontCache::forgetPrivacy();
});

/**
 * These tests write the settings directly rather than through the admin screen,
 * so they have to drop the cached read model themselves — the same thing
 * PrivacySettingsController does after a save.
 *
 * @param  array<int, string>  $categories
 */
function offerCategories(array $categories): void
{
    $legal = app(LegalSettings::class);
    $legal->consent_categories = $categories;
    $legal->save();

    StorefrontCache::forgetPrivacy();
}

/**
 * The cookie the consent endpoint writes, as the browser would send it back.
 *
 * @param  array<int, string>  $granted
 * @param  array<int, string>|null  $offered
 */
function consentCookie(array $granted, ?array $offered = null): string
{
    return (string) json_encode([
        'granted' => $granted,
        'offered' => $offered ?? ConsentCategory::optionalValues(),
    ]);
}

test('no measurement tag is emitted to a visitor who has not answered', function () {
    offerCategories(ConsentCategory::optionalValues());

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertDontSee(GA4_ID)
        ->assertDontSee(GTM_ID)
        ->assertDontSee(PIXEL_ID)
        ->assertDontSee('googletagmanager.com', false)
        ->assertDontSee('connect.facebook.net', false);
});

test('granting analytics emits the Google tags and still withholds the marketing pixel', function () {
    offerCategories(ConsentCategory::optionalValues());

    $response = $this
        ->withCookie(Consent::COOKIE, consentCookie(['analytics']))
        ->get(route('home'));

    $response->assertOk()
        ->assertSee(GA4_ID)
        ->assertSee(GTM_ID)
        ->assertSee('googletagmanager.com/gtag/js', false)
        ->assertDontSee(PIXEL_ID)
        ->assertDontSee('connect.facebook.net', false);
});

test('granting marketing emits the pixel and leaves advertising storage denied for Google', function () {
    offerCategories(ConsentCategory::optionalValues());

    $response = $this
        ->withCookie(Consent::COOKIE, consentCookie(['marketing']))
        ->get(route('home'));

    $response->assertOk()
        ->assertSee(PIXEL_ID)
        ->assertSee('connect.facebook.net', false)
        ->assertDontSee(GA4_ID);
});

test('a granted category the store no longer offers does not resurrect its tag', function () {
    offerCategories(['marketing']);

    $response = $this
        ->withCookie(Consent::COOKIE, consentCookie(['analytics', 'marketing'], ['analytics', 'marketing']))
        ->get(route('home'));

    // Marketing is still offered and still granted, so the pixel loads; the
    // Google tags do not, because analytics is no longer something a visitor
    // is able to consent to.
    $response->assertOk()
        ->assertSee(PIXEL_ID)
        ->assertDontSee(GA4_ID)
        ->assertDontSee(GTM_ID);
});

test('switching every category off silences the banner and every tag with it', function () {
    offerCategories([]);

    $response = $this
        ->withCookie(Consent::COOKIE, consentCookie(['analytics', 'marketing']))
        ->get(route('home'));

    $response->assertOk()
        ->assertDontSee(GA4_ID)
        ->assertDontSee(PIXEL_ID)
        ->assertSee('"needsAnswer":false', false);
});

test('the banner is asked for again when a new category is added after an answer', function () {
    offerCategories(ConsentCategory::optionalValues());

    $answered = $this
        ->withCookie(Consent::COOKIE, consentCookie(['analytics']))
        ->get(route('home'));

    $answered->assertSee('"needsAnswer":false', false);

    // The store starts asking about marketing after this visitor answered a
    // question that did not include it.
    $stale = $this
        ->withCookie(Consent::COOKIE, consentCookie(['analytics'], ['analytics']))
        ->get(route('home'));

    $stale->assertSee('"needsAnswer":true', false);
});

test('accepting everything stores the granted categories in a cookie', function () {
    offerCategories(ConsentCategory::optionalValues());

    $response = $this->post(route('consent.store'), ['accept' => 'all']);

    $response->assertRedirect()
        ->assertCookie(Consent::COOKIE, consentCookie(['analytics', 'marketing']));
});

test('declining stores an answer that grants nothing', function () {
    offerCategories(ConsentCategory::optionalValues());

    $this->post(route('consent.store'), ['accept' => 'none'])
        ->assertCookie(Consent::COOKIE, consentCookie([]));
});

test('a selected answer keeps only the categories that were ticked', function () {
    offerCategories(ConsentCategory::optionalValues());

    $this->post(route('consent.store'), [
        'accept' => 'selected',
        'categories' => ['marketing'],
    ])->assertCookie(Consent::COOKIE, consentCookie(['marketing']));
});

test('a category the store does not offer cannot be granted through the endpoint', function () {
    offerCategories(['analytics']);

    $this->post(route('consent.store'), [
        'accept' => 'selected',
        'categories' => ['marketing'],
    ])->assertSessionHasErrors('categories.0');
});

test('necessary storage is never offered as a choice', function () {
    offerCategories(ConsentCategory::optionalValues());

    $this->get(route('home'))->assertDontSee('"value":"necessary"', false);

    $this->post(route('consent.store'), [
        'accept' => 'selected',
        'categories' => ['necessary'],
    ])->assertSessionHasErrors('categories.0');
});

test('an answer with no accept intent is rejected', function () {
    offerCategories(ConsentCategory::optionalValues());

    $this->post(route('consent.store'), [])->assertSessionHasErrors('accept');
});

test('an unreadable consent cookie denies everything rather than failing open', function () {
    offerCategories(ConsentCategory::optionalValues());

    $response = $this
        ->withCookie(Consent::COOKIE, 'not-json-at-all')
        ->get(route('home'));

    $response->assertOk()
        ->assertDontSee(GA4_ID)
        ->assertSee('"needsAnswer":true', false);
});
