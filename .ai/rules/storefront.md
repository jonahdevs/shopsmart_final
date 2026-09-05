---
paths:
  - 'resources/js/components/storefront/**'
---

# Storefront

## Storefront design system: Kenyan marketplace
A mainstream retail language, not a signage one. The page is white, content sits
on bordered cards that lift very slightly off it, and one confident blue does
all the pointing.

Tokens live in `resources/css/app.css`. `.storefront` (applied by StoreShell)
remaps the shadcn semantic variables onto the brand palette, so every `ui/`
primitive inherits the identity — never fork a `ui/` component to restyle it.
The storefront is always light; dark mode is staff-only.

**The blue is the system.** `--electric` (#0a57eb) marks every eyebrow, link,
icon tile, badge and primary button, so a shopper learns in one screen that blue
means "go here". Do not introduce a second accent to carry emphasis — reach for
weight, size or a `--tint` band instead.

**Two colours are reserved and must stay that way.** `--sale` (#ef1f6b) appears
on discounts and nowhere else; `--star` (#ffb800) belongs to ratings alone.
Spending either as a general accent destroys the two signals a shopper actually
scans a listing for. Errors use the mapped `--destructive`, never `--sale`.

**Chrome is three related darks**, lightest at the top of the page:
`--utility` (the trust strip), `--header` (the navy band), `--footer`, plus
`--panel` for dark cards sitting on a light section. `--tint` / `--tint-strong`
are the pale blue bands that separate a section from the white page.

**Depth, not rules.** Cards carry a 1px `--rule` border and `shadow-card`,
rising to `shadow-card-hover` on interaction. Both are tokens, so the whole
storefront changes depth in one edit.

**Radius is soft, with one deliberate contrast.** `--radius` (0.625rem) rounds
cards, inputs and buttons. Anything that should read as a pill — eyebrows,
badges, the search field, icon-only buttons — rounds fully instead. That is the
contrast; do not add a third step.

**Section rhythm.** Every section opens with a blue letter-spaced uppercase
eyebrow, then a heavy display heading, then a muted one-line subtitle, with an
optional blue "View all →" pushed to the right. `SectionHeading.vue` owns this —
compose it, do not hand-roll another.

Plus Jakarta Sans is display only (headings, the wordmark, prices). Body copy is
Instrument Sans. Money arrives preformatted from the server via `money()`; the
client never formats currency. `Price.vue` splits the currency unit off the
amount on the single space that `Money::format()` emits, sets the amount in the
display face with tabular figures, and is the only place a price is rendered.
