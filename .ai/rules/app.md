---
paths:
  - 'app/**'
---

# App

## Money is stored as integer cents in `_cents` columns
Every monetary value is an integer number of cents, and the column name says so (`total_cents`, `unit_price_cents`, `free_shipping_threshold_cents`). Never store a float or a major-unit value.

Format for display with the `money()` helper (app/helpers.php), which delegates to App\Support\Money and respects CurrencySettings. Convert with `Money::toMinor()` / `toMajor()` — do not multiply or divide by 100 inline.

Money::format() joins the symbol with a regular space, not U+00A0. The reference project used a non-breaking space, which forced unescaped Blade echoes; this project sends money as JSON props to Vue, so a plain space keeps the value clean.
