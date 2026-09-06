---
paths:
  - 'app/Http/Controllers/Account/**'
  - 'resources/js/pages/account/**'
  - 'resources/js/layouts/account/**'
---

# Account

## Ownership failures are 404, not 403
Every account route resolves a record the signed-in customer owns. When it is
not theirs the answer is `abort_unless(..., 404)`, never 403 — confirming that
an order number or address id exists but belongs to someone else is more than a
stranger needs to know. `OrderController`, `PaymentController`,
`AddressController` and `OrderReceiptController` all do this; match them.

Scope every write to the signed-in user too. A route-model-bound `{address}` is
whatever id was in the URL until you check it.

## The account area lives in the storefront, and settings pick their chrome
Pages under `resources/js/pages/account/` resolve to
`[StorefrontLayout, AccountLayout]` through the switch in `resources/js/app.ts`.
`AccountLayout` declares a `breadcrumbs` prop and renders both the trail and the
page's H1 from it, so account pages do NOT render `StoreBreadcrumbs` themselves
— that is the opposite of the storefront rule in `pages-shop.md`, and it is safe
only because this layout declares the prop where `StoreShell` does not.

`settings/*` resolves to `SettingsShell.vue`, which branches on the shared
`auth.isStaff` prop: staff keep `[AppLayout, SettingsLayout]`, customers get the
storefront chrome. There is ONE set of profile/security pages — do not fork them
per audience. Appearance is staff-only; dark mode is not offered to customers.

## Shared props that cost a query must be closures
`HandleInertiaRequests::share()` runs on EVERY request through the middleware,
including plain JSON ones like `/search/suggest`, which fires on every keystroke.
`auth.isStaff` (a `roles` existence query) and `storefront.socialLinks` (a
settings read) are both wrapped in closures, because Inertia only resolves prop
closures when it is actually building a page response.

Anything you add to `share()` that touches the database must be a closure or
cached, and you must re-run the query-budget tests — `CatalogTest`,
`CategoryPageTest`, `ProductPageTest` and `SearchSuggestTest` each assert a cap
and exist to catch exactly this.

## Portalled overlays need re-theming per audience
Dialog and Sheet content teleports to `document.body`, outside `.storefront`.
A component used on BOTH sides of the app cannot hardcode `class="storefront"` —
that would force staff dialogs light. Use `usePortalTheme()`
(`resources/js/composables/usePortalTheme.ts`), which resolves from
`auth.isStaff`. A component that only ever renders inside the storefront should
state `class="storefront ..."` directly instead.

## Review eligibility has one home
`App\Support\ReviewEligibility` answers every question about who may review
what, and `ReviewSettings` is authoritative over all three of its toggles
(`reviews_enabled`, `require_verified_purchase`, `auto_approve`). The form, the
form request and the "awaiting review" list all call `canReview()` — a mismatch
between them either offers a form that cannot submit or hides one that could.

`verified_purchase` is computed, never taken from the request, and stays
meaningful even when the store has stopped requiring proof: the badge is the
thing a shopper weighs.

There is no unique index behind `(user_id, product_id)` — the table also holds
anonymous and imported reviews, where a null `user_id` would make one
meaningless. "One review per customer per product" is enforced in
`ReviewEligibility::hasReviewed()`, so two genuinely concurrent submits could
still both land.
