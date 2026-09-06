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
    <Empty class="border-rule shadow-card border bg-white">
        <EmptyHeader>
            <EmptyMedia variant="icon" class="bg-tint text-electric rounded-lg">
                <PackageSearch aria-hidden="true" />
            </EmptyMedia>
            <EmptyTitle
                class="font-display text-ink text-xl font-extrabold tracking-[-0.02em]"
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
