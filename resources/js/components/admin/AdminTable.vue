<script setup lang="ts">
import { Table } from '@/components/ui/table';
import { cn } from '@/lib/utils';

/**
 * The admin table, with the back office's own spacing.
 *
 * A wrapper rather than an edit to `ui/table`, because the storefront's
 * `CompareTable` uses the same primitive and wants none of this — it pins its
 * first column and sets its own rhythm.
 *
 * Two things are decided here, both of them alignment problems the shadcn
 * defaults cannot know about:
 *
 * The first and last cells are inset to `px-5`, which is the gutter every other
 * strip in an `AdminCard` uses — the card header, the filter bar and the
 * pagination row. The primitive ships `p-2`, so a table sat its content 12px
 * further out than the search box directly above it, and the eye reads that as
 * two panels rather than one. Applied through child selectors so a page keeps
 * writing plain `TableHead` and `TableCell`.
 *
 * The header takes a `--muted` band. On a screen that is one white card on a
 * grey page, a header row with nothing but a bottom border does not read as a
 * header at all once the table scrolls under it.
 *
 * Rows are `py-3` rather than the primitive's 8px. Fifty rows of catalog at 8px
 * is a wall; this is the density the reference build settles on.
 */
const { class: klass } = defineProps<{ class?: string }>();
</script>

<template>
    <Table
        :class="
            cn(
                '[&_td]:py-3 [&_th]:h-11',
                '[&_td:first-child]:pl-5 [&_th:first-child]:pl-5',
                '[&_td:last-child]:pr-5 [&_th:last-child]:pr-5',
                '[&_thead]:bg-muted/40',
                klass,
            )
        "
    >
        <slot />
    </Table>
</template>
