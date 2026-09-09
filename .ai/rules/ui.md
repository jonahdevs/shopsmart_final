---
paths:
  - 'resources/js/components/ui/**'
---

# Ui

## Form fields are padding-sized, not height-sized
Input, Textarea, NativeSelect and SelectTrigger have had stock shadcn's fixed `h-9` removed. Height comes from `py-3` (`py-3.25` on Textarea) plus an explicit `leading-normal` / `leading-relaxed`, with gutters widened to `px-3.5`. Ported from the wryterscript build so fields read roomier and wrap predictably.

Label is `text-xs font-semibold`, not `text-sm font-medium`, so it reads as a field name rather than body copy.

Keep `text-base md:text-sm` on Input and Textarea. Dropping it makes mobile Safari zoom on focus, which matters on a mobile-first storefront.

Button still sizes by height (`h-9` default). Only `AdminFilterBar.vue` puts a button inline with a field, and it is a ghost `size="sm"`, so the mismatch is not visible. Revisit if a default button is ever placed beside an input.
