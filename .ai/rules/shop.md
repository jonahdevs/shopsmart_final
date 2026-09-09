---
paths:
  - 'app/Http/Controllers/Shop/**'
  - app/Http/Controllers/Shop/ProductController.php
---

# Shop

## Storefront listing engine lives in FiltersCatalogProducts
The catalog and category pages share app/Http/Controllers/Shop/Concerns/FiltersCatalogProducts.php — query string parsing, filters, sorts, pagination and facets. Add a filter there, not in a controller, or the two pages drift apart.

Category membership is `primary_category_id IN (...) OR the category_product pivot`, never one alone: the seeder keeps both in step but an import may set only one.

Facet counts are one UNION-of-both-sources query (catalogCountsByCategory) rolled up in PHP, not a count query per facet. The catalog page has a 12-query budget enforced by tests/Feature/Storefront/CatalogTest.php.

Price sorts use COALESCE(sale_price, price) so a discounted product sorts where the shopper sees it, with NULL prices always last.

## Facet counts are product ids rolled up a subtree, and unset price bounds stay null
The cached read model behind every facet (StorefrontCache::CATEGORY_PRODUCT_COUNTS, built by FiltersCatalogProducts::catalogProductIdsByCategory) holds product IDs per category, not a tally. Roll it up with CategoryTree::subtreeCount, which unions the ids: a product can be filed in a parent and one of its children at once, and summing tallies made a facet promise more tiles than ticking it returns. The read on that key discards any entry that is not a list, so a value cached in the old tally shape cannot crash a deploy.

Both listing pages expand a selected `cat[]` slug through CategoryTree::subtreeIds before scoping, and pass the same resolved ids to brandFacets(), so a facet's number always equals what ticking it returns. A top-level category holds no products directly, so neither step is optional.

CatalogFilterData::$priceMax is null when the shopper set no ceiling, and applyPriceRange only binds a bound that was actually supplied — defaulting it to PRICE_CEILING hid every product dearer than the slider's top stop from the whole storefront with hasActiveFilters false. PRICE_CEILING now travels as $priceCeiling for the slider to render.

Shopper terms reach LIKE through containsPattern(), which escapes `%` and `_`. Never interpolate a raw term into a pattern.

## product.show serves two readers: the live page and a staff preview
`product.show` renders a product that is NOT public (draft, scheduled-in-future, archived, hidden) for a signed-in user holding `products.view` or `products.manage`. Everyone else still gets a 404. There is no token or signed URL — the reader is already authenticated and that permission already grants the admin table listing every draft by name.

The `preview` prop (App\Data\ProductPreviewData, null on a live page) is the ONE switch: it turns the fixed staff-preview bar on in resources/js/pages/shop/Product.vue, replaces the add-to-cart form, and forces `robots: noindex, nofollow` plus an empty `jsonLd` on the document head. A shopper never receives it, so none of that treatment can leak onto a live listing. Do not gate any of the three on anything else.

A preview does not call `recordView()` — proofreading must not feed "customers also viewed". A product in the bin is not previewable at all: route model binding drops it before `previewFor()` is asked.

`Product::isViewableOnStore()` is the single definition of "the storefront would render this rather than 404". The shop controller aborts on it and AdminProductRowData reports it to the products table; do not restate the rule at a call site.
