<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PackageSearch } from '@lucide/vue';
import { clearedFilters } from '@/components/storefront/catalogFilters';
import type { CatalogFilterPatch } from '@/components/storefront/catalogFilters';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { index } from '@/routes/categories';

/**
 * The dead end a filter combination can produce. It always offers the way back
 * out, because the shopper cannot see which of several ticked boxes emptied the
 * grid — clearing everything is the one move guaranteed to return products.
 */
defineProps<{ hasActiveFilters: boolean }>();

const emit = defineEmits<{ update: [patch: CatalogFilterPatch] }>();
</script>

<template>
    <Empty class="border-rule border">
        <EmptyHeader>
            <EmptyMedia variant="icon">
                <PackageSearch aria-hidden="true" />
            </EmptyMedia>
            <EmptyTitle
                class="font-display text-lg font-black tracking-[-0.02em] uppercase"
            >
                Nothing here yet
            </EmptyTitle>
            <EmptyDescription>
                <template v-if="hasActiveFilters">
                    No products match every filter you have on. Try loosening
                    one, or start again.
                </template>
                <template v-else>
                    There are no products in this part of the shop right now.
                </template>
            </EmptyDescription>
        </EmptyHeader>

        <div class="flex flex-wrap items-center justify-center gap-3">
            <button
                v-if="hasActiveFilters"
                type="button"
                class="bg-electric font-display focus-visible:outline-electric rounded-xs px-4 py-2 text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
                @click="emit('update', clearedFilters())"
            >
                Clear all filters
            </button>

            <Link
                :href="index()"
                class="font-display text-electric hover:border-electric focus-visible:outline-electric border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                Browse categories
            </Link>
        </div>
    </Empty>
</template>
