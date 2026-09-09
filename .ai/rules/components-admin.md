---
paths:
  - 'resources/js/components/admin/**'
---

# Components Admin

## The visitors map reads hex tokens and rebuilds on a theme flip
AdminVisitorsMap wraps jsvectormap, which writes straight into the DOM and reads its palette once at construction. So it is destroyed and rebuilt on new data, on full-screen and on a `resolvedAppearance` change — the same useAppearance() dependency AdminChart and the revenue donut use, and the only thing that stops light-mode fills surviving on a dark card.

Its colours come from `visitorMapColors()` in lib/charts.ts, not from tokens read in the component: the ramp has one home. They are returned RESOLVED, not as `var(...)`, because the library interpolates every country's fill between the two ends — and only from `#rgb`/`#rrggbb`. A token that stops being hex paints the whole map `#NaNNaNNaN`, which is why there are validated fallbacks and why the quiet end is `--chart-1` mixed over `--card` rather than a token (`--accent` is too close to `--muted`, the "nobody came from there" grey, in both themes).

Two jsvectormap quirks the code depends on: `visualizeData.values` carries a `__floor: 0` entry under a key no region matches, so shading runs from zero (a colour means the same thing on every window) and `min === max` never happens — the library's own divide-by-zero branch returns a malformed colour. And `setFocus` scales about a region's centre, so wide countries (RU, CN, US, CA, BR, AU, IN, AR, KZ, DZ) get a lower scale or they overshoot the viewport.
