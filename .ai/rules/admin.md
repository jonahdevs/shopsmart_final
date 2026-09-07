---
paths:
  - 'resources/js/pages/admin/**, resources/js/components/admin/**, resources/js/layouts/admin/**'
---

# Admin

## The admin design system: tokens at :root, strip-stacked cards, one index-table composable
The back office is the storefront's identity arranged for density, not a second palette. Never write a raw Tailwind palette class (`bg-emerald-50`, `text-red-600`) in an admin page — the closed tone vocabulary lives in `resources/js/components/admin/tones.ts` and components pick a tone by name.

Staff tokens are the bare `:root` / `.dark` blocks in app.css, NOT a scoped `.admin` class. That is deliberate: Dialog/Sheet/Popover teleport to `document.body`, so anything scoped to a layout wrapper is dropped by the first confirm dialog. This is why `usePortalTheme()` returns `undefined` for staff.

`AdminLayout` owns the page gutter (`p-4 sm:p-6 lg:p-8`). Pages open with `<div class="flex flex-col gap-6">` and must NOT re-state a page-level `p-*`.

An INDEX screen is one `AdminCard` holding a stack of bordered strips — `AdminFilterBar`, the table, `AdminPagination` — not three cards with gaps. The card carries no padding; strips own theirs and separate with `border-b`/`border-t`.

A FORM or DETAIL screen is the opposite: several `AdminCard`s in a `grid gap-6 lg:grid-cols-3`, with a `space-y-6 lg:col-span-2` main column and a one-column aside for status/publish/meta. Nine form sections stacked as strips inside one card read worse than nine cards, because there is no single table for the strips to belong to. Cancel and Save go in `AdminPageHeader`'''s `#actions` slot, and the header sits INSIDE the `<Form>` so Save is a real submit with `processing` in scope.

Index pages must use `useIndexTable()` (resources/js/composables/useIndexTable.ts) rather than hand-rolling `activeQuery`/`sortHref`/`ariaSort`/`hrefForPage` plus a debounced watcher. Eleven pages each grew their own copy; the composable also holds the `replace` + `preserveState` + `preserveScroll` visit options, and dropping `preserveState` makes the search box lose focus mid-word.

Sidebar nav items may declare `children` for a disclosure. Settings uses it — `/admin/settings` only redirects, so a single link left six screens reachable only by typing the URL.
