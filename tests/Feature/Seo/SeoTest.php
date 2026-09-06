<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Settings\BrandingSettings;
use App\Settings\SeoSettings;
use App\Support\StorefrontCache;

/**
 * What the store tells a search engine.
 *
 * These assertions read the RENDERED HTML rather than page props, on purpose.
 * Inertia's `<Head>` only reaches the initial document when SSR is running, and
 * it is off here — so a test that asserted a prop would pass while a crawler
 * fetching the same URL saw an empty head. The point of the whole design is
 * that these tags are printed by Blade, and only the HTML proves it.
 */
beforeEach(function () {
    $this->seo = app(SeoSettings::class);
    $this->seo->index_site = true;
    $this->seo->generate_sitemap = true;
    $this->seo->meta_title_pattern = '{page} | {site}';
    $this->seo->default_meta_description = 'Everything for the modern home.';
    $this->seo->save();
    StorefrontCache::forgetSeo();

    $branding = app(BrandingSettings::class);
    $branding->store_name = 'ShopSmart';
    $branding->save();

    // Written behind the controllers, so nothing has cleared the cached read
    // model — see StorefrontCache::SEO. Without this every assertion below
    // would be reading whatever the previous test happened to leave there.
    StorefrontCache::forgetSeo();
});

test('a page with no title of its own falls back to the store name alone', function () {
    // Not "| ShopSmart" with a dangling separator.
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>ShopSmart</title>', escape: false);
});

test('a product runs its own title through the store pattern', function () {
    $product = Product::factory()->published()->create(['name' => 'Cast Iron Pan']);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('<title>Cast Iron Pan | ShopSmart</title>', escape: false);
});

test('a page with no description of its own gets the store fallback', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Everything for the modern home.', escape: false);
});

test('every page carries a canonical url and a robots directive', function () {
    $response = $this->get(route('home'))->assertOk();

    $response->assertSee('rel="canonical"', escape: false)
        ->assertSee('name="robots" content="index, follow"', escape: false);
});

test('turning indexing off makes every page noindex', function () {
    $this->seo->index_site = false;
    $this->seo->save();
    StorefrontCache::forgetSeo();

    // The setting is a single switch over the whole site, so a shop being set
    // up cannot be indexed half-built.
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('content="noindex, nofollow"', escape: false)
        ->assertDontSee('content="index, follow"', escape: false);
});

test('every page identifies the shop with organization structured data', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('application/ld+json', escape: false)
        ->assertSee('"@type":"OnlineStore"', escape: false);
});

test('a product page publishes its price in major units, never in cents', function () {
    $product = Product::factory()->published()->create([
        'name' => 'Cast Iron Pan',
        // 4,999.00 KES stored as cents. Quoting 499900 to a search engine would
        // overstate the price a hundredfold in every result snippet.
        'price' => 499900,
        'sale_price' => null,
    ]);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('"price":"4999.00"', escape: false)
        ->assertSee('"priceCurrency":"KES"', escape: false)
        ->assertDontSee('"price":"499900', escape: false);
});

test('a product on sale advertises what the shopper actually pays', function () {
    $product = Product::factory()->published()->create([
        'price' => 500000,
        'sale_price' => 250000,
    ]);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('"price":"2500.00"', escape: false);
});

test('a price on application product advertises no offer at all', function () {
    $product = Product::factory()->published()->create([
        'price' => null,
        'sale_price' => null,
    ]);

    // An offer of zero would be a lie; the absence of one is the truth.
    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertDontSee('"@type":"Offer"', escape: false);
});

test('a product with no reviews publishes no rating', function () {
    $product = Product::factory()->published()->create();

    // schema.org treats an AggregateRating of zero reviews as invalid and
    // Google reports it as an error rather than ignoring it.
    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertDontSee('AggregateRating', escape: false);
});

test('a product name containing markup cannot break out of the ld+json script', function () {
    $product = Product::factory()->published()->create([
        'name' => 'Pan </script><script>alert(1)</script>',
    ]);

    $response = $this->get(route('product.show', $product->slug))->assertOk();

    // The closing tag must be escaped inside the JSON, or the product name
    // ends the element and the rest becomes executable markup.
    $response->assertDontSee('</script><script>alert(1)', escape: false);
});

test('the product canonical prefers the product own canonical column', function () {
    $product = Product::factory()->published()->create([
        'canonical_url' => 'https://example.test/canonical-home',
    ]);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('href="https://example.test/canonical-home"', escape: false);
});

test('robots.txt invites crawlers and points at the sitemap', function () {
    $response = $this->get('/robots.txt')->assertOk();

    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($response->getContent())
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /account')
        ->toContain('Disallow: /checkout')
        ->toContain('Sitemap: '.route('sitemap'));
});

test('robots.txt closes the whole site when indexing is off', function () {
    $this->seo->index_site = false;
    $this->seo->save();
    StorefrontCache::forgetSeo();

    expect($this->get('/robots.txt')->assertOk()->getContent())
        ->toContain('Disallow: /')
        ->not->toContain('Sitemap:');
});

test('the sitemap lists the pages a shopper can actually reach', function () {
    $product = Product::factory()->published()->create();
    $category = Category::factory()->create();

    $response = $this->get('/sitemap.xml')->assertOk();

    expect($response->getContent())
        ->toContain(route('home'))
        ->toContain(route('product.show', $product->slug))
        ->toContain(route('category.show', $category->slug));
});

test('the sitemap never points a crawler at a product that would 404', function () {
    $draft = Product::factory()->draft()->create();

    expect($this->get('/sitemap.xml')->assertOk()->getContent())
        ->not->toContain(route('product.show', $draft->slug));
});

test('the sitemap is withheld rather than published empty when it is turned off', function () {
    $this->seo->generate_sitemap = false;
    $this->seo->save();
    StorefrontCache::forgetSeo();

    // An empty sitemap is a claim the store has no pages, which is worse than
    // publishing none.
    $this->get('/sitemap.xml')->assertNotFound();
});

test('a brand page is reachable from the catalog the sitemap advertises', function () {
    $brand = Brand::factory()->create();
    Product::factory()->published()->create(['brand_id' => $brand->id]);

    expect($this->get('/sitemap.xml')->assertOk()->getContent())
        ->toContain(route('catalog'));
});
