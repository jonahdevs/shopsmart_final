<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue';
import FacetCheckbox from '@/components/storefront/FacetCheckbox.vue';
import FacetSection from '@/components/storefront/FacetSection.vue';
import {
    RATING_STEPS,
    toggleValue,
    type CatalogFilterPatch,
} from '@/components/storefront/catalogFilters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * The listing's filter sidebar.
 *
 * It renders entirely from the server's echo of the query string, never from
 * local state, so the panel and the grid can never disagree — the one exception
 * is the price range, which is a typed draft until it is applied.
 */
const { filters, categoryFacets, brandFacets } = defineProps<{
    filters: App.Data.CatalogFilterData;
    categoryFacets: App.Data.FacetOptionData[];
    brandFacets: App.Data.FacetOptionData[];
    /** "Category" on the catalog, "Sub-category" inside a category listing. */
    categoryLabel?: string;
}>();

const emit = defineEmits<{ update: [patch: CatalogFilterPatch] }>();

const ratingGroupName = useId();
const priceMinId = useId();
const priceMaxId = useId();
const priceErrorId = useId();

function toggleCategory(slug: string, checked: boolean): void {
    const next = checked
        ? [...filters.categories, slug]
        : filters.categories.filter((candidate) => candidate !== slug);

    emit('update', { categories: next });
}

function toggleBrand(id: number): void {
    emit('update', { brands: toggleValue(filters.brands, id) });
}

/*
  The price range is the one control that is not applied on change: a number
  input fires on every keystroke, and "1" on the way to "1000" is a filter
  nobody asked for. The draft is seeded from the server's reading and resynced
  whenever a response changes it.
*/
const minDraft = ref('');
const maxDraft = ref('');

watch(
    () => [filters.priceMin, filters.priceMax] as const,
    ([min, max]) => {
        minDraft.value = min > 0 ? String(min) : '';
        maxDraft.value = max === null ? '' : String(max);
    },
    { immediate: true },
);

/** An empty box is an open end, not a zero. */
function parseBound(value: string): number | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number(trimmed);

    return Number.isFinite(parsed) ? Math.trunc(parsed) : Number.NaN;
}

const draftMin = computed(() => parseBound(minDraft.value));
const draftMax = computed(() => parseBound(maxDraft.value));

/**
 * The server rejects an inverted or out-of-range pair with a validation error
 * rather than a listing, so the same rules are checked here and the apply
 * button simply stays inert until the pair makes sense.
 */
const priceError = computed<string | null>(() => {
    const min = draftMin.value;
    const max = draftMax.value;

    if (Number.isNaN(min) || Number.isNaN(max)) {
        return 'Enter whole numbers only.';
    }

    if ((min !== null && min < 0) || (max !== null && max < 0)) {
        return 'Prices cannot be negative.';
    }

    if (min !== null && max !== null && min > max) {
        return 'The lowest price must not be above the highest.';
    }

    return null;
});

const priceChanged = computed(
    () =>
        (draftMin.value ?? 0) !== filters.priceMin ||
        (draftMax.value ?? null) !== filters.priceMax,
);

function applyPrice(): void {
    if (priceError.value !== null || !priceChanged.value) {
        return;
    }

    const max = draftMax.value;

    emit('update', {
        priceMin: draftMin.value ?? 0,
        // At or above the ceiling there is nothing left to exclude, so the
        // bound is dropped rather than pinned — that is what keeps `pmax` out
        // of the URL and the chip out of the active-filter summary.
        priceMax: max === null || max >= filters.priceCeiling ? null : max,
    });
}

function clearPrice(): void {
    emit('update', { priceMin: 0, priceMax: null });
}

function selectRating(rating: number): void {
    emit('update', { minRating: rating });
}
</script>

<template>
    <div class="flex flex-col">
        <FacetSection
            v-if="categoryFacets.length > 0"
            :title="categoryLabel ?? 'Category'"
        >
            <ul class="flex max-h-72 flex-col gap-2.5 overflow-y-auto pr-1">
                <li v-for="facet in categoryFacets" :key="facet.slug">
                    <FacetCheckbox
                        :label="facet.name"
                        :count="facet.count"
                        :checked="filters.categories.includes(facet.slug)"
                        @change="
                            (checked) => toggleCategory(facet.slug, checked)
                        "
                    />
                </li>
            </ul>
        </FacetSection>

        <FacetSection v-if="brandFacets.length > 0" title="Brand">
            <ul class="flex max-h-72 flex-col gap-2.5 overflow-y-auto pr-1">
                <li v-for="facet in brandFacets" :key="facet.id">
                    <FacetCheckbox
                        :label="facet.name"
                        :count="facet.count"
                        :checked="filters.brands.includes(facet.id)"
                        @change="() => toggleBrand(facet.id)"
                    />
                </li>
            </ul>
        </FacetSection>

        <FacetSection title="Price">
            <!--
              A plain pair of numbers rather than a two-thumb slider: the
              ceiling runs to seven figures, so a linear track cannot resolve
              the range most of the catalogue actually sits in, and the value
              a shopper means is one they can type exactly.
            -->
            <div class="flex items-end gap-2">
                <div class="min-w-0 flex-1">
                    <label
                        :for="priceMinId"
                        class="text-muted-foreground block text-xs"
                    >
                        Lowest
                    </label>
                    <Input
                        :id="priceMinId"
                        v-model="minDraft"
                        type="number"
                        inputmode="numeric"
                        min="0"
                        :max="filters.priceCeiling"
                        step="1"
                        placeholder="Any"
                        class="mt-1 rounded-lg"
                        :aria-invalid="priceError !== null"
                        :aria-describedby="
                            priceError === null ? undefined : priceErrorId
                        "
                        @keydown.enter.prevent="applyPrice"
                    />
                </div>

                <span
                    class="text-muted-foreground pb-2 text-sm"
                    aria-hidden="true"
                >
                    &ndash;
                </span>

                <div class="min-w-0 flex-1">
                    <label
                        :for="priceMaxId"
                        class="text-muted-foreground block text-xs"
                    >
                        Highest
                    </label>
                    <Input
                        :id="priceMaxId"
                        v-model="maxDraft"
                        type="number"
                        inputmode="numeric"
                        min="0"
                        :max="filters.priceCeiling"
                        step="1"
                        placeholder="Any"
                        class="mt-1 rounded-lg"
                        :aria-invalid="priceError !== null"
                        :aria-describedby="
                            priceError === null ? undefined : priceErrorId
                        "
                        @keydown.enter.prevent="applyPrice"
                    />
                </div>
            </div>

            <p
                v-if="priceError"
                :id="priceErrorId"
                class="text-destructive mt-2 text-xs"
            >
                {{ priceError }}
            </p>

            <div class="mt-3 flex items-center gap-2">
                <Button
                    type="button"
                    size="sm"
                    class="rounded-lg font-semibold"
                    :disabled="priceError !== null || !priceChanged"
                    @click="applyPrice"
                >
                    Apply price
                </Button>
                <Button
                    v-if="filters.priceMin > 0 || filters.priceMax !== null"
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="text-muted-foreground hover:text-ink rounded-lg"
                    @click="clearPrice"
                >
                    Reset
                </Button>
            </div>
        </FacetSection>

        <FacetSection title="Rating">
            <!--
              Native radios: the group is a single-choice control, and the
              browser's own roving focus and arrow-key handling are exactly the
              behaviour a hand-rolled listbox would have to reimplement.
            -->
            <div class="flex flex-col gap-2.5">
                <label
                    v-for="step in RATING_STEPS"
                    :key="step"
                    class="flex cursor-pointer items-center gap-2.5 text-sm"
                >
                    <input
                        type="radio"
                        :name="ratingGroupName"
                        :value="step"
                        :checked="filters.minRating === step"
                        class="accent-electric focus-visible:outline-electric size-4 focus-visible:outline-2 focus-visible:outline-offset-2"
                        @change="selectRating(step)"
                    />
                    <span class="text-foreground"
                        >{{ step }} stars &amp; up</span
                    >
                </label>

                <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                    <input
                        type="radio"
                        :name="ratingGroupName"
                        value="0"
                        :checked="filters.minRating === 0"
                        class="accent-electric focus-visible:outline-electric size-4 focus-visible:outline-2 focus-visible:outline-offset-2"
                        @change="selectRating(0)"
                    />
                    <span class="text-muted-foreground">Any rating</span>
                </label>
            </div>
        </FacetSection>

        <FacetSection title="Availability">
            <FacetCheckbox
                label="In stock only"
                :checked="filters.inStockOnly"
                @change="(checked) => emit('update', { inStockOnly: checked })"
            />
        </FacetSection>
    </div>
</template>
