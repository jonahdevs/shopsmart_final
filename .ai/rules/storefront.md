---
paths:
  - 'resources/js/components/storefront/**'
---

# Storefront

## Storefront design system: matatu signage
The storefront identity is drawn from Nairobi matatu livery, read structurally not literally: an ink header band, a continuous pinstripe that slides to the active category, and heavy condensed signage lettering.

Tokens live in `resources/css/app.css`. `.storefront` (applied by StoreShell) remaps the shadcn semantic variables onto the brand palette, so every `ui/` primitive inherits the identity — never fork a `ui/` component to restyle it. The storefront is always light; dark mode is staff-only.

`--sale` (#ff1f6b) is reserved for discounts and appears nowhere else. Do not reach for it as a general accent or for errors — errors use the mapped `--destructive`.

Boldness is spent in one place: the price block (`Price.vue`). Currency is demoted to a small tracked unit, the amount is set in Archivo 800 with tabular figures so prices align down a grid. Cards, chrome and surrounding UI stay deliberately quiet — no card borders, no shadows, product photography straight on white.

Money arrives preformatted from the server via `money()`; the client never formats currency. `Price.vue` splits the unit off the amount on the single space that `Money::format()` emits.

Archivo is display only (signage headers, prices). Body copy is Instrument Sans.
