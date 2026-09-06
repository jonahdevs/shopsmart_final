<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * The two files a crawler asks for before it reads anything else.
 *
 * Both are routes rather than static files in `public/`, because both answer to
 * settings a staff member can change: turning indexing off has to take effect
 * immediately, and a `robots.txt` written to disk at deploy time would still be
 * inviting crawlers hours later.
 */
class SeoController extends Controller
{
    /**
     * Categories, brands and products are all bounded sets in this store, but
     * a sitemap is capped by the protocol at 50,000 URLs regardless.
     */
    private const MAX_URLS = 50000;

    public function __construct(private Seo $seo) {}

    /**
     * `robots.txt`, resolved against the store's own setting.
     *
     * With indexing off this disallows everything — the same answer the
     * `noindex` on every page gives, said in the one place a crawler reads
     * first. With indexing on it still keeps bots out of the areas that are
     * either private or infinite: the admin panel, the account area, checkout,
     * and the faceted catalog, whose filter combinations are unbounded and
     * would be crawled forever.
     */
    public function robots(): Response
    {
        if (! $this->seo->indexSite()) {
            return $this->text("User-agent: *\nDisallow: /\n");
        }

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /account',
            'Disallow: /settings',
            'Disallow: /checkout',
            'Disallow: /cart',
            'Disallow: /pay',
            // The suggest endpoint answers keystrokes, not pages.
            'Disallow: /search/suggest',
            // Faceted URLs multiply without limit; the clean listings above
            // reach every product anyway.
            'Disallow: /*?*brands=',
            'Disallow: /*?*categories=',
            'Disallow: /*?*sort=',
            '',
        ];

        if ($this->seo->publishesSitemap()) {
            $lines[] = 'Sitemap: '.route('sitemap');
            $lines[] = '';
        }

        return $this->text(implode("\n", $lines));
    }

    /**
     * The sitemap.
     *
     * A 404 rather than an empty document when the setting is off: an empty
     * sitemap is a claim that the store has no pages, which is worse than not
     * publishing one at all.
     *
     * Only what the storefront would actually serve is listed. A draft product
     * or a hidden category in here would send crawlers to a 404 and spend the
     * store's crawl budget on nothing.
     */
    public function sitemap(): Response
    {
        abort_unless($this->seo->publishesSitemap(), 404);

        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => route('catalog'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => route('categories.index'), 'priority' => '0.7', 'changefreq' => 'weekly'],
        ];

        foreach ($this->categories() as $category) {
            $urls[] = [
                'loc' => route('category.show', $category->slug),
                'lastmod' => $category->updated_at?->toAtomString(),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ];
        }

        foreach ($this->products() as $product) {
            $urls[] = [
                'loc' => route('product.show', $product->slug),
                'lastmod' => $product->updated_at?->toAtomString(),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ];
        }

        return response()
            ->view('sitemap', ['urls' => array_slice($urls, 0, self::MAX_URLS)])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories()
    {
        return Category::query()
            ->active()
            ->orderBy('name')
            ->get(['slug', 'updated_at']);
    }

    /**
     * Products a shopper could actually reach. `visibleInCatalog()` rather than
     * `visibleInSearch()`: this lists pages, and a search-only product has no
     * listing of its own to point at.
     *
     * @return Collection<int, Product>
     */
    private function products()
    {
        return Product::query()
            ->published()
            ->visibleInCatalog()
            ->orderBy('id')
            ->take(self::MAX_URLS)
            ->get(['slug', 'updated_at']);
    }

    private function text(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
