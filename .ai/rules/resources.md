---
paths:
  - 'resources/**'
---

# Resources

## Button cursor is one base rule, not a utility per button
Tailwind v4 preflight resets `button` to `cursor: default`. `resources/css/app.css` restores it once in `@layer base` for `button`, `[role=button]` and `summary`, excluding `:disabled`, `[aria-disabled=true]` and `[data-disabled]`.

Do not add `cursor-pointer` to individual buttons — it is already covered, including everything reka-ui and the `ui/` primitives render. Anchors get the pointer from the browser.

Explicit `cursor-*` utilities still win, because utilities outrank the base layer. That is deliberate: shadcn's `cursor-default` on menu, select and calendar items keeps them reading as native menus, and `disabled:cursor-not-allowed` keeps its arrow.

## The thin scrollbar is global, not opt-in
`resources/css/app.css` styles every scrollbar in `@layer base`: a 10px gutter carrying a 4px thumb, the extra width spent on a transparent border with `background-clip: padding-box` so the thumb floats clear of the content. Borrowed from the wryterscript build.

Do not add a per-panel scrollbar class. A panel that starts scrolling later inherits the treatment for free.

`scrollbar-width` is behind `@supports not selector(::-webkit-scrollbar)` on purpose. Chromium 121+ understands the property, and setting it makes Chromium ignore every `::-webkit-scrollbar` rule — the padded thumb is simply not drawn. Firefox is the only engine that needs the standard properties and the only one without the pseudo-element, so the guard hands each engine the mechanism it honours. Do not lift it.

The thumb colour is the `--scrollbar-thumb` token, set in `:root`, re-asserted light in `.storefront` (the shop is always light) and darkened in `.dark`. It is a token rather than a `dark:` utility because the project's dark variant cannot be expressed on a `::-webkit-scrollbar-thumb` selector — `@apply ... dark:bg-*` there compiles away silently.

`CategoryStripe.vue` keeps its own scoped `.scrollbar-none` for the horizontal category rail, where the bar is hidden entirely rather than thinned.
