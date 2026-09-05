---
paths:
  - 'resources/js/pages/shop/**'
---

# Pages Shop

## Storefront pages render breadcrumbs in-page, not via layout props
The `defineOptions({ layout: { breadcrumbs: [...] } })` pattern only works on the STAFF side, where AppLayout declares a `breadcrumbs` prop. StorefrontLayout.vue -> layouts/storefront/StoreShell.vue declares no props, so layout props fall through to $attrs and stamp `breadcrumbs="[object Object]"` on the shell's root div.

Storefront pages therefore render `components/storefront/StoreBreadcrumbs.vue` in-page, from the `breadcrumbs: BreadcrumbData[]` prop (a null slug plus position decides Home vs Categories). It is the single breadcrumb component for the storefront — compose it, do not fork it, do not add a second one.

Three separate agents independently hit this and converged here. If you want the layout route instead, add the prop to StoreShell first; do not change one page in isolation.

## Checkout runs two sibling forms joined by the HTML5 form attribute
shop/Checkout.vue posts to two endpoints from one screen and HTML forbids nested forms. The left column IS `<Form id="checkout-form">` (delivery, address picker); the new-address form and the coupon form are SIBLINGS of it, and the place-order button plus the order-note textarea rejoin it with `form="checkout-form"`. Inertia's Form reads `new FormData(formElement, submitter)`, which includes form-associated controls that are not descendants, so this posts correctly — but only because the `<Form>` renders before those controls in DOM order. Do not "tidy" a sibling form back inside, and do not reorder the column so an associated control mounts before the form it names.

The place-order button is outside the form, so it cannot see the `processing` slot prop: Checkout.vue tracks it with a local ref driven by `@start` / `@finish` on the Form.
