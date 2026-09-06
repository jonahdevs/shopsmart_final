<?php

namespace App\Support;

use App\Data\BreadcrumbData;
use App\Data\SeoData;
use App\Models\Product;
use App\Settings\BrandingSettings;
use App\Settings\BusinessSettings;
use App\Settings\SeoSettings;
use App\Settings\SocialSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Builds what the document head tells a search engine.
 *
 * The four SeoSettings fields were written and read by the admin page and
 * applied nowhere; this is what applies them. `index_site` is the one with
 * teeth — turned off, every page of the store answers `noindex, nofollow`,
 * which is what makes it safe to put the site up before it is ready to trade.
 */
class Seo
{
    /**
     * The ISO code prices are quoted in. Matches the constant PlaceOrder freezes
     * onto an order; structured data must not disagree with the receipt.
     */
    private const CURRENCY = 'KES';

    /**
     * The settings-derived read model, resolved once per request.
     *
     * Deliberately NOT constructor-injected settings objects: this class is
     * injected into controllers, and typed settings parameters would resolve
     * four settings groups on construction whether or not the action used
     * them. The head is built on every page response, so those reads have to
     * be cached — see {@see StorefrontCache::SEO} — and the property keeps the
     * cache lookup itself down to one per request.
     *
     * @var array{titlePattern: string, defaultDescription: string, indexSite: bool, generateSitemap: bool, siteName: string}|null
     */
    private ?array $config = null;

    /**
     * @return array{titlePattern: string, defaultDescription: string, indexSite: bool, generateSitemap: bool, siteName: string}
     */
    private function config(): array
    {
        return $this->config ??= Cache::remember(
            StorefrontCache::SEO,
            now()->addHour(),
            function (): array {
                $seo = app(SeoSettings::class);
                $branding = app(BrandingSettings::class);
                $name = trim($branding->store_name) !== ''
                    ? trim($branding->store_name)
                    : (string) config('app.name');

                return [
                    'titlePattern' => $seo->meta_title_pattern,
                    'defaultDescription' => $seo->default_meta_description,
                    'indexSite' => $seo->index_site,
                    'generateSitemap' => $seo->generate_sitemap,
                    'siteName' => $name,
                ];
            },
        );
    }

    /**
     * Assemble the head for one page.
     *
     * @param  list<array<string, mixed>>  $jsonLd
     */
    public function page(
        ?string $title = null,
        ?string $description = null,
        ?string $canonicalUrl = null,
        array $jsonLd = [],
    ): SeoData {
        return new SeoData(
            title: $this->title($title),
            description: $this->description($description),
            canonicalUrl: $canonicalUrl ?? url()->current(),
            robots: $this->robots(),
            jsonLd: $jsonLd,
        );
    }

    /**
     * The page's own title run through the store's pattern.
     *
     * `{page}` and `{site}` are the placeholders the settings screen documents.
     * A page with no title of its own collapses to the site name rather than
     * rendering a dangling separator.
     */
    public function title(?string $pageTitle): string
    {
        $site = $this->siteName();

        if ($pageTitle === null || trim($pageTitle) === '') {
            return $site;
        }

        $pattern = trim($this->config()['titlePattern']);

        if ($pattern === '') {
            return $pageTitle.' - '.$site;
        }

        return str_replace(['{page}', '{site}'], [$pageTitle, $site], $pattern);
    }

    /** The page's own description, or the store's fallback, or nothing. */
    public function description(?string $description): ?string
    {
        $resolved = trim((string) ($description ?? $this->config()['defaultDescription']));

        return $resolved === '' ? null : $resolved;
    }

    /**
     * Whether this store currently invites indexing.
     *
     * A single switch over the whole site, so a shop being set up cannot be
     * indexed half-built by a crawler that happened to arrive early.
     */
    public function robots(): string
    {
        return $this->config()['indexSite'] ? 'index, follow' : 'noindex, nofollow';
    }

    public function indexSite(): bool
    {
        return $this->config()['indexSite'];
    }

    public function publishesSitemap(): bool
    {
        return $this->config()['generateSitemap'];
    }

    public function siteName(): string
    {
        return $this->config()['siteName'];
    }

    /**
     * Who the shop is. Built from BusinessSettings because that is where the
     * data controller's own identity already lives, so the structured data and
     * the privacy policy cannot name two different companies.
     *
     * The HOME PAGE ONLY asks for this. It describes the business, not the
     * page, so repeating it on every product is duplication a crawler has to
     * reconcile rather than extra signal — and it costs two more settings
     * groups on a response that already has a query budget. Cached separately
     * from {@see config()} for exactly that reason: the pages that never ask
     * must not pay for it.
     *
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return Cache::remember(
            StorefrontCache::SEO_ORGANIZATION,
            now()->addHour(),
            fn (): array => $this->buildOrganization($this->siteName()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrganization(string $siteName): array
    {
        $business = app(BusinessSettings::class);
        $social = app(SocialSettings::class);

        $profiles = array_values(array_filter([
            $social->facebook_url,
            $social->instagram_url,
            $social->x_url,
            $social->linkedin_url,
            $social->youtube_url,
        ], static fn (string $url): bool => trim($url) !== ''));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'OnlineStore',
            'name' => $siteName,
            'legalName' => trim($business->legal_name) ?: null,
            'url' => url('/'),
            'email' => trim($business->contact_email) ?: null,
            'telephone' => trim($business->contact_phone) ?: null,
            'address' => trim($business->address) ?: null,
            // Null rather than an empty list, so the one filter below covers it:
            // schema.org would accept `"sameAs": []` but it says nothing.
            'sameAs' => $profiles === [] ? null : $profiles,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * One product, as a search engine reads it.
     *
     * The price is the EFFECTIVE price — what the shopper actually pays — and
     * it leaves integer cents exactly once, here. A price quoted in cents would
     * be a hundredfold overstatement in every result snippet.
     *
     * A product with no price is price-on-application and carries no `offers`
     * block at all, rather than an offer of zero.
     *
     * @return array<string, mixed>
     */
    public function product(Product $product): array
    {
        $cents = $product->effectivePriceCents();

        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->meta_description ?? $product->short_description,
            'sku' => $product->sku,
            'mpn' => $product->model_number,
            'brand' => $product->brand === null ? null : [
                '@type' => 'Brand',
                'name' => $product->brand->name,
            ],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($cents !== null) {
            $data['offers'] = [
                '@type' => 'Offer',
                'price' => number_format(app(Money::class)->toMajor($cents), 2, '.', ''),
                'priceCurrency' => self::CURRENCY,
                'availability' => $product->isInStock()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => route('product.show', $product->slug),
            ];
        }

        // Only when the aggregate has actually been loaded AND there is
        // something to aggregate: schema.org treats an AggregateRating with a
        // zero review count as invalid, and Google reports it as an error.
        $count = (int) ($product->getAttribute('reviews_count') ?? 0);
        $average = $product->getAttribute('reviews_avg_rating');

        if ($count > 0 && $average !== null) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $average, 2),
                'reviewCount' => $count,
            ];
        }

        return $data;
    }

    /**
     * The trail, as structured data.
     *
     * Takes the same {@see BreadcrumbData} the pages already render, so the
     * crumbs a shopper sees and the crumbs Google indexes are one list. A rung
     * with a null slug is a label rather than a destination and carries no
     * `item`.
     *
     * @param  list<BreadcrumbData>  $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $crumbs): array
    {
        $items = [];
        $position = 1;

        foreach ($crumbs as $crumb) {
            $items[] = array_filter([
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $crumb->name,
                'item' => $crumb->slug === null ? null : route('category.show', $crumb->slug),
            ], static fn (mixed $value): bool => $value !== null);
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
