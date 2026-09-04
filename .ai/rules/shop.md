---
paths:
  - 'app/Http/Controllers/Shop/**'
---

# Shop

## Storefront listing engine lives in FiltersCatalogProducts
The catalog and category pages share app/Http/Controllers/Shop/Concerns/FiltersCatalogProducts.php — query string parsing, filters, sorts, pagination and facets. Add a filter there, not in a controller, or the two pages drift apart.

Category membership is `primary_category_id IN (...) OR the category_product pivot`, never one alone: the seeder keeps both in step but an import may set only one.

Facet counts are one UNION-of-both-sources query (catalogCountsByCategory) rolled up in PHP, not a count query per facet. The catalog page has a 12-query budget enforced by tests/Feature/Storefront/CatalogTest.php.

Price sorts use COALESCE(sale_price, price) so a discounted product sorts where the shopper sees it, with NULL prices always last.
