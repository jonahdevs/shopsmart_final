---
paths:
  - resources/js/pages/admin/Dashboard.vue
  - 'resources/js/pages/admin/**'
---

# Pages Admin

## The dashboard's panel forms are deliberate — do not add a sixth bar chart
Every panel picks its form from the question it answers, and no two panels may share a form by accident. Currently: trend = area chart with a measure switch; part-to-whole of one total (revenue by method) = a donut with the total in its hole, over a hand-built legend listing money and share; ranked top-N (products/aisles) = one horizontal bar chart with a grouping switch, never two side by side; a lifecycle or ordered scale (order status, star ratings) = a ladder of labelled meters, not a chart, so the count is printed rather than read off an axis.

Meters have no axis, so their share is server-computed on App\Data\AdminDashboardShareSliceData and never divided out in the browser. AdminChartSliceData is for things the page plots; the share slice is for things it draws as a meter.

Chart option builders live in resources/js/lib/charts.ts and take a height parameter — set the height there, not with a CSS height on the container (ApexCharts ignores it). Keep the Deferred fallback skeleton heights equal to the real panel heights or the page jumps when the charts land.

## Admin selects: `null` is the empty value, and the form post rides the primitive's hidden select
reka-ui's `SelectItem` throws on an empty-string value — `''` is reserved for "the selection has been cleared" — so nothing in the admin panel may use it as an option value. `null` is the primitive's own empty value (`isNullish`) and an item may carry it, so an "All …" / "None" row stays a real, re-selectable option.

Filter dropdowns go through `components/admin/AdminFilterSelect.vue`, which translates `useIndexTable`'s `''` (filter off, omitted from the URL) to and from `null`. Never hand-roll that mapping in a page; never bind a raw `Select` to a `useIndexTable` field.

Form selects submit through `SelectRoot`'s hidden `BubbleSelect` — a visually-hidden native `<select>` rendered only when the trigger has an ancestor `<form>` AND a `name` prop is set. So a select inside an Inertia `<Form>` must carry `name` (Inertia builds its payload with `new FormData(formEl)`), and `id` goes on `SelectTrigger`, not on `Select` (which renders a fragment). A `null` selection makes that hidden select fall back to its empty option, so the server still reads a blank field.

`multiple` selects stay `NativeSelect`: the hidden select's value cannot hold an array, so a reka Select would post nothing at all for `categories[]` / `variants[][attribute_value_ids][]`.

Portalled `SelectContent` needs no `class="storefront"` here — staff tokens live on bare `:root`.

## Colours baked into a page-level chart computed go stale on a theme flip
AdminChart re-calls chartBaseOptions() on every render, so everything the shared base resolves from a CSS token is fresh after a light/dark switch. A page's own `computed(() => someChartOptions(...))` is not: it has no dependency on the appearance, so any colour the builder read through cssValue() (a slice stroke of `--card`, a tooltip theme) keeps the value it had when the page first painted, and AdminChart rebuilds with it.

Fix it in the page, not in AdminChart: pull `resolvedAppearance` from useAppearance() and read it inside the computed. See the "Revenue by method" donut on admin/Dashboard.vue.

A hand-built legend must take its swatch colour from `chartSwatchColor(index)` in resources/js/lib/charts.ts, which shares its slot lookup with `chartPalette()`. A local list of `bg-chart-*` classes is a second copy of the ramp that can drift from the one Apex was handed.
