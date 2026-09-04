---
paths:
  - 'app/**'
---

# App

## Money is stored as integer cents — but the catalog columns are not suffixed
Every monetary value is an integer number of cents. Never store a float or a major-unit value.

The column name usually says so (`total_cents`, `unit_price_cents`, `free_shipping_threshold_cents`), but the catalog tables predate that convention and their money columns are **unsuffixed**: `products.price`, `products.sale_price`, `products.cost_price` and the same three on `product_variants` are all integer cents despite reading like major units. `Product::effectivePriceCents()` is the accessor that says so out loud — prefer it over touching the columns directly. New tables take the `_cents` suffix.

Format for display with the `money()` helper (app/helpers.php), which delegates to App\Support\Money and respects CurrencySettings. Convert with `Money::toMinor()` / `toMajor()` — do not multiply or divide by 100 inline. The catalog price filter is the reference call site: it reads whole KES off the query string and hands them to `toMinor()`.

Money::format() joins the symbol with a regular space, not U+00A0. The reference project used a non-breaking space, which forced unescaped Blade echoes; this project sends money as JSON props to Vue, so a plain space keeps the value clean.
