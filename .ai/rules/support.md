---
paths:
  - app/Support/StorefrontSession.php
  - app/Support/Consent.php
---

# Support

## The session is the live cart; the database is a mirror
StorefrontSession is the only public API for the cart, wishlist and compare tray. Callers never branch on auth state — the session holds the live copy for guests and signed-in customers alike, and every mutation mirrors it into carts/cart_items/saved_products for a signed-in user only.

That is what makes the shared header counts free: HandleInertiaRequests reads shopperState() straight out of the session, so the props shared on every request cost zero queries. Never make those counts query the database.

SyncCartOnLogin is the one place the two copies are reconciled. Overlapping cart lines take the LARGER quantity, not the sum, and saved lists merge as a union — that is what makes logging in twice a no-op. The Login event carries the user because the session guard fires it before setting the resolved user, so every persistence path takes a ?User explicitly rather than trusting auth().

cart_items.unit_price_cents is the price captured when a line was opened, kept even when the line is topped up. It exists so a catalog edit cannot rewrite a cart under the shopper; it is not an authority for money taken — checkout re-prices.

## Measurement tags are gated server-side, never in the browser
App\Support\Consent is the only thing that decides whether an optional category may load. AnalyticsTags::forRequest() asks it, and the x-privacy-scripts Blade component renders only what comes back — so an ungranted vendor's `<script>` is never written into the document at all. Never move that check into Vue: a tag that reaches the browser has already been fetched from Google or Meta and has already set its cookies.

legal.consent_categories IS the gate, not a cosmetic banner switch. A category that is not offered can never be granted, so an id in AnalyticsSettings whose category is missing is dead. An empty list removes the banner AND every optional tag.

The visitor's answer is the `consent` cookie, written server-side by Shop\ConsentController (so it round-trips through EncryptCookies) rather than by JavaScript — bootstrap/app.php's `encryptCookies(except:)` list is deliberately not extended for it. The cookie stores the offered set alongside the granted one, so adding a category re-asks everyone. Because the tags live in the document head, a new answer only takes effect on the next full document: ConsentBanner.vue reloads the page when the granted set actually changed.
