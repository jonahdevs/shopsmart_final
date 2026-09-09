---
paths:
  - 'resources/js/pages/admin/products/**'
---

# Products

## The product editor is foldable cards plus one tabbed "Product data" card
Layout follows the reference build (new-ecommerce products form): main column = Basics, Product data, Description, Search listing; aside = Publication, Filing. Everything about how the product trades (pricing, inventory, shipping, variants, linked products, advanced) lives in ONE card behind a vertical tab rail, with the `type` select in that card's header because type decides which tabs are offered.

Two hard constraints, both because every field is an uncontrolled `name`d input the page's single `<Form>` reads out of the DOM at submit time:

- Tabs pass `:unmount-on-hide="false"` and folds use `v-show`, never `v-if`. A panel that drops its fields posts nothing for them, which turns "which tab was open" into an edit. Same reason a conditional field must be hidden, never removed.
- A hidden `required` field makes the browser abort the submit with only a console warning, so `ProductSectionCard` opens on captured `invalid` and `ProductDataSection` switches to the offending panel (`invalid` does not bubble — capture phase). Server errors route the same way via `alerted` / the first-tab-in-error watcher.

Media and the delete button post on their own routes, so they cannot sit in the aside (forms do not nest) and live in a second grid below the form, edit-only. Variants and links DO save with create — `store()` syncs relations in the same transaction — so do not copy the reference's "save the product first" gate for them.
