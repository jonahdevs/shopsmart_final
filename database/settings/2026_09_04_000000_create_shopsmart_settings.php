<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // ==================================================
        // BUSINESS INFO
        // ==================================================
        $this->migrator->add('business.legal_name', config('app.name', 'ShopSmart'));
        $this->migrator->add('business.registration_number', '');
        $this->migrator->addEncrypted('business.tax_pin', '');
        $this->migrator->add('business.contact_email', '');
        $this->migrator->add('business.contact_phone', '');
        $this->migrator->add('business.address', '');
        $this->migrator->add('business.business_hours', '');

        // ==================================================
        // BRANDING
        // ==================================================
        $this->migrator->add('branding.store_name', config('app.name', 'ShopSmart'));
        $this->migrator->add('branding.tagline', '');
        $this->migrator->add('branding.logo_path', null);
        $this->migrator->add('branding.favicon_path', null);

        // ==================================================
        // LOCALIZATION
        // ==================================================
        $this->migrator->add('localization.currency', 'KES');
        $this->migrator->add('localization.weight_unit', 'g');
        $this->migrator->add('localization.dimension_unit', 'mm');
        $this->migrator->add('localization.timezone', 'Africa/Nairobi');

        // ==================================================
        // CURRENCY & PRICING
        // ==================================================
        // KES is quoted in whole shillings, so no decimal places are rendered.
        $this->migrator->add('currency.symbol', 'KES');
        $this->migrator->add('currency.symbol_position', 'before');
        $this->migrator->add('currency.decimals', 0);
        $this->migrator->add('currency.thousand_separator', ',');
        $this->migrator->add('currency.decimal_separator', '.');

        // ==================================================
        // CHECKOUT
        // ==================================================
        $this->migrator->add('checkout.min_order_value', 0);
        $this->migrator->add('checkout.order_prefix', 'SS-');
        $this->migrator->add('checkout.guest_checkout_enabled', true);
        $this->migrator->add('checkout.terms_url', '/terms');

        // ==================================================
        // TAX
        // ==================================================
        $this->migrator->add('tax.tax_enabled', true);
        // The fallback tax class for products without one of their own; the tax
        // class seeder points this at the standard-rated class once it exists.
        $this->migrator->add('tax.default_tax_class_id', null);
        // Kenyan retail prices are quoted VAT-inclusive.
        $this->migrator->add('tax.prices_include_tax', true);

        // ==================================================
        // SHIPPING
        // ==================================================
        $this->migrator->add('shipping.local_pickup_enabled', true);
        $this->migrator->add('shipping.pickup_address', '');
        // Single source of truth for the free-delivery threshold: KES 50,000.
        $this->migrator->add('shipping.free_shipping_threshold_cents', 5_000_000);

        // ==================================================
        // PAYMENTS
        // ==================================================
        // Paystack is the only online gateway available in Kenya; it fronts
        // cards, M-Pesa, Airtel Money and bank transfers in one integration.
        $this->migrator->add('payments.paystack_enabled', true);
        $this->migrator->add('payments.bank_transfer_enabled', false);
        $this->migrator->addEncrypted('payments.bank_details', '');
        $this->migrator->add('payments.cash_on_delivery_enabled', false);

        // ==================================================
        // PAYMENT API CREDENTIALS
        // ==================================================
        $this->migrator->add('payment_api.paystack_public_key', null);
        $this->migrator->addEncrypted('payment_api.paystack_secret_key', null);

        // ==================================================
        // INVENTORY
        // ==================================================
        $this->migrator->add('inventory.track_stock_by_default', true);
        $this->migrator->add('inventory.low_stock_threshold', 5);
        $this->migrator->add('inventory.out_of_stock_behavior', 'show');
        $this->migrator->add('inventory.allow_backorders_by_default', false);

        // ==================================================
        // REVIEWS
        // ==================================================
        $this->migrator->add('reviews.reviews_enabled', true);
        $this->migrator->add('reviews.require_verified_purchase', true);
        $this->migrator->add('reviews.auto_approve', false);

        // ==================================================
        // SEO
        // ==================================================
        $this->migrator->add('seo.meta_title_pattern', '{page} | {site}');
        $this->migrator->add('seo.default_meta_description', 'ShopSmart is your smart online shop for everyday essentials: beauty, fashion, electronics, home and groceries, delivered across Kenya.');
        $this->migrator->add('seo.index_site', true);
        $this->migrator->add('seo.generate_sitemap', true);

        // ==================================================
        // SOCIAL & SHARING
        // ==================================================
        $this->migrator->add('social.og_image_path', null);
        $this->migrator->add('social.twitter_handle', 'shopsmart');
        $this->migrator->add('social.facebook_url', 'https://www.facebook.com/shopsmart');
        $this->migrator->add('social.instagram_url', 'https://www.instagram.com/shopsmart/');
        $this->migrator->add('social.x_url', 'https://x.com/shopsmart');
        $this->migrator->add('social.linkedin_url', 'https://www.linkedin.com/company/shopsmart/');
        $this->migrator->add('social.youtube_url', 'https://www.youtube.com/@shopsmart');
        $this->migrator->add('social.whatsapp_number', '+254700000000');
        $this->migrator->add('social.whatsapp_order_enabled', false);

        // ==================================================
        // LEGAL
        // ==================================================
        // Policy copy lives in CMS pages; this is only the cookie banner
        // behaviour. On by default because analytics and marketing scripts load
        // ungated when it is off.
        $this->migrator->add('legal.cookie_consent_enabled', true);

        // ==================================================
        // ANALYTICS
        // ==================================================
        $this->migrator->add('analytics.ga4_id', '');
        $this->migrator->add('analytics.gtm_id', '');
        $this->migrator->add('analytics.meta_pixel_id', '');
    }
};
