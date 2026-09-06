<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed } from 'vue';
import {
    clearedFilters,
    hasPriceBound,
    priceRangeLabel,
    type CatalogFilterPatch,
} from '@/components/storefront/catalogFilters';

/**
 * The receipt for what is currently narrowing the grid.
 *
 * Every chip clears exactly one thing, because a shopper who wants to widen a
 * search by one step should not have to hunt back through the sidebar for the
 * box they ticked — especially on a phone, where the sidebar is behind a sheet.
 */
const { filters, categoryFacets, brandFacets } = defineProps<{
    filters: App.Data.CatalogFilterData;
    categoryFacets: App.Data.FacetOptionData[];
    brandFacets: App.Data.FacetOptionData[];
}>();

const emit = defineEmits<{ update: [patch: CatalogFilterPatch] }>();

type Chip = {
    key: string;
    label: string;
    patch: CatalogFilterPatch;
};

const chips = computed<Chip[]>(() => {
    const list: Chip[] = [];

    if (filters.q !== '') {
        list.push({
            key: 'q',
            label: `Search: ${filters.q}`,
            patch: { q: '' },
        });
    }

    for (const slug of filters.categories) {
        const facet = categoryFacets.find((option) => option.slug === slug);

        list.push({
            key: `cat:${slug}`,
            label: facet?.name ?? slug,
            patch: {
                categories: filters.categories.filter(
                    (candidate) => candidate !== slug,
                ),
            },
        });
    }

    for (const id of filters.brands) {
        const facet = brandFacets.find((option) => option.id === id);

        list.push({
            key: `brand:${id}`,
            label: facet?.name ?? `Brand ${id}`,
            patch: {
                brands: filters.brands.filter((candidate) => candidate !== id),
            },
        });
    }

    const priceLabel = hasPriceBound(filters) ? priceRangeLabel(filters) : null;

    if (priceLabel !== null) {
        list.push({
            key: 'price',
            label: priceLabel,
            patch: { priceMin: 0, priceMax: null },
        });
    }

    if (filters.minRating > 0) {
        list.push({
            key: 'rating',
            label: `${filters.minRating} stars & up`,
            patch: { minRating: 0 },
        });
    }

    if (filters.inStockOnly) {
        list.push({
            key: 'stock',
            label: 'In stock only',
            patch: { inStockOnly: false },
        });
    }

    if (filters.tag !== '') {
        list.push({
            key: 'tag',
            label: filters.tag,
            patch: { tag: '' },
        });
    }

    if (filters.newArrivalsOnly) {
        list.push({
            key: 'arrivals',
            label: 'New arrivals',
            patch: { newArrivalsOnly: false },
        });
    }

    return list;
});
</script>

<template>
    <div
        v-if="chips.length > 0"
        class="flex flex-wrap items-center gap-2"
        role="group"
        aria-label="Active filters"
    >
        <button
            v-for="chip in chips"
            :key="chip.key"
            type="button"
            class="border-rule hover:border-electric hover:text-electric focus-visible:outline-electric text-ink inline-flex max-w-full items-center gap-1.5 rounded-full border bg-white px-3 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
            @click="emit('update', chip.patch)"
        >
            <span class="truncate">{{ chip.label }}</span>
            <X class="size-3 shrink-0" aria-hidden="true" />
            <span class="sr-only">Remove this filter</span>
        </button>

        <button
            type="button"
            class="text-electric focus-visible:outline-electric ml-1 shrink-0 rounded-sm text-xs font-bold transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-4"
            @click="emit('update', clearedFilters())"
        >
            Clear all
        </button>
    </div>
</template>
