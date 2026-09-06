<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PackageSearch } from '@lucide/vue';
import { computed } from 'vue';
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
const { searchTerm } = defineProps<{
    hasActiveFilters: boolean;
    /** Present only on the search page, where the dead end has a cause to name. */
    searchTerm?: string;
}>();

const emit = defineEmits<{ update: [patch: CatalogFilterPatch] }>();

/** Blank on the catalog and the category pages, which are not searches. */
const term = computed(() => searchTerm?.trim() ?? '');
</script>

<template>
    <Empty class="border-rule shadow-card border bg-white">
        <EmptyHeader>
            <EmptyMedia variant="icon" class="bg-tint text-electric rounded-lg">
                <PackageSearch aria-hidden="true" />
            </EmptyMedia>
            <EmptyTitle
                class="font-display text-ink text-xl font-extrabold tracking-[-0.02em]"
            >
                <template v-if="term !== ''">
                    No matches for “{{ term }}”
                </template>
                <template v-else>Nothing here yet</template>
            </EmptyTitle>
            <EmptyDescription>
                <template v-if="term !== ''">
                    Check the spelling, try a shorter or more general term, or
                    browse the categories instead.
                </template>
                <template v-else-if="hasActiveFilters">
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
                class="bg-electric focus-visible:outline-electric rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
                @click="emit('update', clearedFilters())"
            >
                Clear all filters
            </button>

            <Link
                :href="index()"
                class="text-electric focus-visible:outline-electric rounded-sm text-sm font-bold transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                Browse categories
            </Link>
        </div>
    </Empty>
</template>
