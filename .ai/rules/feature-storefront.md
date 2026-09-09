---
paths:
  - 'app/Http/Middleware/**, tests/Feature/Storefront/**'
---

# Feature Storefront

## Middleware on the storefront must read settings through the cache
`EnsureStoreIsOpen` wraps every storefront route. Resolving a settings group inline there puts a query on every page load, and the standing query budgets in CatalogTest, CategoryPageTest, ProductPageTest and SearchSuggestTest fail by exactly one when you do.

Read through a `StorefrontCache` key instead, and forget it in the admin controller that saves the group — nothing observes a settings save. Any test that writes the group behind the controller must forget the key too.

The budgets count the cold-cache read, so a genuinely new dependency still raises them by one. Raise them with a comment saying which dependency, the way the SEO head and maintenance mode both did. Do not raise them to make a failure go away without knowing which read is new.
