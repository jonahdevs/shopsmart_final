<?php

use App\Enums\ConsentCategory;
use App\Enums\ReviewAuthorFormat;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Turns the legal group from a dead boolean into the gate the analytics tags
 * are actually rendered through, and gives the store the two privacy controls
 * it was missing.
 *
 * `legal.cookie_consent_enabled` governed nothing — no tag consulted it and no
 * banner existed — so a store that switched it off and then filled in a GA4 id
 * would have loaded Google on every page with a "consent" toggle that did
 * nothing. It is replaced by the list of categories the banner offers, which
 * IS the gate: a tag whose category is not offered can never be granted and is
 * never rendered.
 *
 * `checkout.terms_url` moves rather than being duplicated. It was the only
 * policy URL in the application and nothing pointed at it; it belongs next to
 * the privacy policy, which is where the footer and the consent banner show
 * both.
 *
 * `reviews.author_display_format` defaults to the abbreviated form on purpose.
 * The store never asked a reviewer whether their surname could stay published,
 * and `reviews.author_name` outlives the account it came from, so the safer
 * rendering is the one to arrive at by default — the same reasoning that makes
 * every optional consent category start denied.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // ==================================================
        // LEGAL & CONSENT
        // ==================================================
        $this->migrator->delete('legal.cookie_consent_enabled');
        $this->migrator->add('legal.consent_categories', ConsentCategory::optionalValues());
        $this->migrator->add('legal.privacy_policy_url', '');
        $this->migrator->rename('checkout.terms_url', 'legal.terms_url');

        // Retention windows, in days. Zero keeps a trail indefinitely.
        // Six months of browsing history is enough for "recently viewed" to be
        // useful; a year of activity log covers a full audit cycle.
        $this->migrator->add('legal.recently_viewed_retention_days', 180);
        $this->migrator->add('legal.activity_log_retention_days', 365);

        // ==================================================
        // REVIEWS
        // ==================================================
        $this->migrator->add('reviews.author_display_format', ReviewAuthorFormat::FirstNameAndInitial->value);
    }

    public function down(): void
    {
        $this->migrator->delete('reviews.author_display_format');

        $this->migrator->delete('legal.activity_log_retention_days');
        $this->migrator->delete('legal.recently_viewed_retention_days');
        $this->migrator->rename('legal.terms_url', 'checkout.terms_url');
        $this->migrator->delete('legal.privacy_policy_url');
        $this->migrator->delete('legal.consent_categories');
        $this->migrator->add('legal.cookie_consent_enabled', true);
    }
};
