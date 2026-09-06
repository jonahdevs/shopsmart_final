<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { home } from '@/routes';
import { index as categoriesIndex } from '@/routes/categories';
import { show } from '@/routes/category';

/**
 * The storefront trail.
 *
 * The server sends `Home / Categories / …ancestors / this one`, with a null
 * slug on the two roots — so the rung's slug decides whether it is a category
 * and its position decides which root it is.
 */
defineProps<{ items: App.Data.BreadcrumbData[] }>();

function hrefFor(
    item: App.Data.BreadcrumbData,
    position: number,
): NonNullable<InertiaLinkProps['href']> {
    if (item.slug !== null) {
        return show(item.slug);
    }

    if (position === 0) {
        return home();
    }

    return categoriesIndex();
}
</script>

<template>
    <nav v-if="items.length > 0" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
            <li
                v-for="(item, position) in items"
                :key="`${position}-${item.slug ?? item.name}`"
                class="flex items-center gap-2"
            >
                <span
                    v-if="position > 0"
                    class="text-muted-foreground/60"
                    aria-hidden="true"
                >
                    /
                </span>

                <span
                    v-if="position === items.length - 1"
                    class="text-foreground font-medium"
                    aria-current="page"
                >
                    {{ item.name }}
                </span>
                <Link
                    v-else
                    :href="hrefFor(item, position)"
                    class="text-muted-foreground hover:text-electric focus-visible:outline-electric rounded-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                >
                    {{ item.name }}
                </Link>
            </li>
        </ol>
    </nav>
</template>
