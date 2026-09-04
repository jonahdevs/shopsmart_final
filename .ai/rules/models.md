---
paths:
  - 'app/Models/**'
---

# Models

## Catalog model conventions: scopes, generics, and the variant price convention
Models use `#[Fillable([...])]` attributes, a `casts()` method, and a full `@property` docblock. PHPStan level 7 with Larastan is in CI, so every relation needs a generic docblock (`@return HasMany<Review, $this>`) and every `#[Scope]` method needs `@param Builder<Model> $query`. Without them the build fails.

Storefront listing queries compose these Product scopes: `published()` (published, or scheduled with an elapsed date), `visibleInCatalog()` / `visibleInSearch()`, `honorStockVisibility()` (reads InventorySettings), and `withReviewStats()`.

`withReviewStats()` aggregates through the `approvedReviews` relation, not a closure, so `Review::approved()` stays the single definition of "approved". That relation is deliberately unordered — it is used inside count/avg subqueries. Order reviews at the call site.

ProductVariant uses `price` + `sale_price` with the same meaning as Product: `sale_price` is what the customer pays. The reference app inverted this on variants only (`compare_at_price` held the effective price) and it was a recurring source of bugs.
