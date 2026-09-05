---
paths:
  - app/Support/StorefrontSession.php
---

# Support

## The session is the live cart; the database is a mirror
StorefrontSession is the only public API for the cart, wishlist and compare tray. Callers never branch on auth state — the session holds the live copy for guests and signed-in customers alike, and every mutation mirrors it into carts/cart_items/saved_products for a signed-in user only.

That is what makes the shared header counts free: HandleInertiaRequests reads shopperState() straight out of the session, so the props shared on every request cost zero queries. Never make those counts query the database.

SyncCartOnLogin is the one place the two copies are reconciled. Overlapping cart lines take the LARGER quantity, not the sum, and saved lists merge as a union — that is what makes logging in twice a no-op. The Login event carries the user because the session guard fires it before setting the resolved user, so every persistence path takes a ?User explicitly rather than trusting auth().

cart_items.unit_price_cents is the price captured when a line was opened, kept even when the line is topped up. It exists so a catalog edit cannot rewrite a cart under the shopper; it is not an authority for money taken — checkout re-prices.
