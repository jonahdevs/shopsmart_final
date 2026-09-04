---
paths:
  - 'app/Data/**'
---

# Data

## Data objects are the Inertia/TypeScript contract
Every class in app/Data needs `#[TypeScript]` — the provider registers AttributedClassTransformer, so a Data class without the attribute silently never reaches resources/js/types/generated.d.ts. Run `php artisan typescript:transform` after changing one.

Money crosses the wire twice: integer cents for comparisons and a preformatted string from `money()`. Clients never format currency. A null price is price-on-application and stays null in both forms.

Collections must be handed out as `array_values(...->all())`. `->values()->all()` is a list at runtime but PHPStan level 7 types it `array<int, T>`, which fails against the `list<T>` docblocks.

ImageData resolves every media conversion up front (card/webp/zoom/thumb plus an inlined base64 lqip) so no Vue component knows a conversion name.
