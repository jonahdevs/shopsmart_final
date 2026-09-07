<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import { TableHead } from '@/components/ui/table';
import { cn } from '@/lib/utils';

/**
 * A sortable column heading.
 *
 * A real link, not a button: a sorted table is a shareable URL and the back
 * button should undo a sort. The `aria-sort` on the cell is what a screen
 * reader announces, so it belongs on the `th` rather than on the link inside
 * it — getting that pair right once here is most of why this component exists.
 *
 * The arrow is always rendered, greyed when the column is not the active sort.
 * Showing it only on the sorted column hides the fact that the others can be
 * sorted at all, and revealing it on hover hides it from touch entirely.
 */
const { sort, align = 'start' } = defineProps<{
    label: string;
    href: string;
    sort: 'ascending' | 'descending' | 'none';
    align?: 'start' | 'end';
    class?: string;
}>();

const icon = computed(() => {
    if (sort === 'ascending') {
        return ArrowUp;
    }

    return sort === 'descending' ? ArrowDown : ChevronsUpDown;
});
</script>

<template>
    <TableHead :aria-sort="sort" :class="cn('whitespace-nowrap', $props.class)">
        <Link
            :href="href"
            preserve-scroll
            class="hover:text-foreground inline-flex items-center gap-1.5 transition-colors"
            :class="align === 'end' && 'flex-row-reverse'"
        >
            {{ label }}
            <component
                :is="icon"
                class="size-3.5 shrink-0"
                :class="
                    sort === 'none'
                        ? 'text-muted-foreground/40'
                        : 'text-primary'
                "
                aria-hidden="true"
            />
        </Link>
    </TableHead>
</template>
