<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Search, Tag, Layers } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';
import Price from '@/components/storefront/Price.vue';
import { catalog, search } from '@/routes';
import { show as categoryShow } from '@/routes/category';
import { show as productShow } from '@/routes/product';
import { suggest } from '@/routes/search';

/**
 * The header's search field and its autocomplete.
 *
 * Built as an ARIA combobox rather than a menu: the text box stays focused
 * throughout and the highlight moves through the list on
 * `aria-activedescendant`, which is what lets a keyboard shopper read the
 * suggestions without losing the caret in the term they are still typing.
 *
 * Submitting always goes to the results page, whatever is highlighted, so the
 * dropdown is a shortcut and never a gate: a term nothing matches still gets a
 * page saying so rather than a form that appears to do nothing.
 */

/** Nothing shorter reaches the endpoint — it matches the server's own floor. */
const MINIMUM_TERM_LENGTH = 2;

/** How long the shopper has to stop typing before a request goes out. */
const DEBOUNCE_MS = 180;

type Suggestion =
    | { kind: 'product'; key: string; label: string; product: App.Data.ProductCardData }
    | { kind: 'category'; key: string; label: string; slug: string }
    | { kind: 'brand'; key: string; label: string; id: number };

const page = usePage();

const query = ref('');
const open = ref(false);
const activeIndex = ref(-1);
const products = ref<App.Data.ProductCardData[]>([]);
const categories = ref<App.Data.FacetOptionData[]>([]);
const brands = ref<App.Data.FacetOptionData[]>([]);

const listboxId = useId();
const statusId = useId();
const optionIdPrefix = useId();
const root = ref<HTMLElement | null>(null);
const field = ref<HTMLInputElement | null>(null);

let debounce: ReturnType<typeof setTimeout> | null = null;
let inFlight: AbortController | null = null;

/**
 * The header lives in the persistent layout, so it is constructed once and
 * never again — seeding the box from the URL at setup would leave the last
 * search sitting in it for the rest of the session. Track Inertia's url
 * instead, so navigating away from a search result clears the field.
 */
watch(
    () => page.url,
    (url) => {
        query.value =
            new URL(url, window.location.origin).searchParams.get('q') ?? '';
        close();
    },
    { immediate: true },
);

/** One flat list, because arrow keys walk the groups as a single sequence. */
const suggestions = computed<Suggestion[]>(() => [
    ...products.value.map(
        (product): Suggestion => ({
            kind: 'product',
            key: `product-${product.id}`,
            label: product.name,
            product,
        }),
    ),
    ...categories.value.map(
        (category): Suggestion => ({
            kind: 'category',
            key: `category-${category.id}`,
            label: category.name,
            slug: category.slug,
        }),
    ),
    ...brands.value.map(
        (brand): Suggestion => ({
            kind: 'brand',
            key: `brand-${brand.id}`,
            label: brand.name,
            id: brand.id,
        }),
    ),
]);

const hasSuggestions = computed(() => suggestions.value.length > 0);
const isExpanded = computed(() => open.value && hasSuggestions.value);

const activeOptionId = computed(() =>
    isExpanded.value && activeIndex.value >= 0
        ? `${optionIdPrefix}-${activeIndex.value}`
        : undefined,
);

/**
 * Announced politely rather than rendered: the suggestions themselves are
 * already reachable with the arrow keys, so a screen reader needs to be told
 * that they arrived, not read the whole list unbidden.
 */
const status = computed(() => {
    if (!isExpanded.value) {
        return '';
    }

    const count = suggestions.value.length;

    return `${count} ${count === 1 ? 'suggestion' : 'suggestions'} available. Use the up and down arrow keys to review them.`;
});

function close(): void {
    open.value = false;
    activeIndex.value = -1;
}

function clearResults(): void {
    products.value = [];
    categories.value = [];
    brands.value = [];
}

function onInput(): void {
    activeIndex.value = -1;

    if (debounce !== null) {
        clearTimeout(debounce);
    }

    const term = query.value.trim();

    if (term.length < MINIMUM_TERM_LENGTH) {
        inFlight?.abort();
        inFlight = null;
        clearResults();
        close();

        return;
    }

    debounce = setTimeout(() => void fetchSuggestions(term), DEBOUNCE_MS);
}

/**
 * A plain request, not an Inertia visit: the endpoint answers JSON and must not
 * touch the page's history, its scroll position or its props. Every keystroke
 * cancels the one before it, so a slow response can never overwrite a newer one.
 */
async function fetchSuggestions(term: string): Promise<void> {
    inFlight?.abort();

    const controller = new AbortController();
    inFlight = controller;

    try {
        const response = await fetch(suggest.url({ query: { q: term } }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            clearResults();
            close();

            return;
        }

        const payload = (await response.json()) as {
            products: App.Data.ProductCardData[];
            categories: App.Data.FacetOptionData[];
            brands: App.Data.FacetOptionData[];
        };

        products.value = payload.products;
        categories.value = payload.categories;
        brands.value = payload.brands;
        activeIndex.value = -1;
        open.value = true;
    } catch {
        // An aborted request is the normal case here — the shopper simply kept
        // typing — and a failed one is not worth interrupting them over. Either
        // way the field still submits to the results page.
    } finally {
        if (inFlight === controller) {
            inFlight = null;
        }
    }
}

function move(step: number): void {
    if (!hasSuggestions.value) {
        return;
    }

    open.value = true;

    const total = suggestions.value.length;
    const next = activeIndex.value + step;

    activeIndex.value = ((next % total) + total) % total;
}

function onEnter(event: KeyboardEvent): void {
    const active = isExpanded.value ? suggestions.value[activeIndex.value] : undefined;

    if (active !== undefined) {
        event.preventDefault();
        choose(active);

        return;
    }

    // Nothing highlighted: the form's own submit takes over.
}

/** Escape gives the term back before it gives the page back. */
function onEscape(): void {
    if (open.value) {
        close();

        return;
    }

    query.value = '';
    clearResults();
}

function choose(suggestion: Suggestion): void {
    close();
    field.value?.blur();

    if (suggestion.kind === 'product') {
        router.visit(productShow(suggestion.product.slug));

        return;
    }

    if (suggestion.kind === 'category') {
        router.visit(categoryShow(suggestion.slug));

        return;
    }

    router.visit(catalog.url({ query: { brand: [suggestion.id] } }));
}

function submit(): void {
    close();

    const term = query.value.trim();

    router.get(
        search.url(term === '' ? {} : { query: { q: term } }),
        {},
        { preserveState: false },
    );
}

/**
 * `focusout` rather than a document click listener: it fires for a keyboard Tab
 * as well as a click elsewhere, and the relatedTarget check keeps the panel
 * open while focus moves between the field and its own options.
 */
function onFocusOut(event: FocusEvent): void {
    const next = event.relatedTarget;

    if (next instanceof Node && root.value?.contains(next)) {
        return;
    }

    close();
}

onBeforeUnmount(() => {
    if (debounce !== null) {
        clearTimeout(debounce);
    }

    inFlight?.abort();
});
</script>

<template>
    <!--
      `min-w-0` because a flex item will not shrink past its content's
      intrinsic width by default, and an `<input>` carries one — without it the
      header is wider than a 360px viewport and the whole document picks up a
      horizontal scrollbar.
    -->
    <div
        ref="root"
        class="relative order-3 w-full min-w-0 md:order-2 md:mx-auto md:w-auto md:max-w-2xl md:flex-1"
        @focusout="onFocusOut"
    >
        <form
            class="focus-within:outline-electric flex w-full min-w-0 items-center overflow-hidden rounded-full bg-white focus-within:outline-2 focus-within:outline-offset-2"
            role="search"
            @submit.prevent="submit"
        >
            <label for="store-search" class="sr-only">
                Search the catalogue
            </label>
            <input
                id="store-search"
                ref="field"
                v-model="query"
                type="search"
                name="q"
                placeholder="Search for products, brands and more..."
                autocomplete="off"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="isExpanded"
                :aria-controls="listboxId"
                :aria-activedescendant="activeOptionId"
                :aria-describedby="statusId"
                class="text-ink placeholder:text-muted-foreground min-w-0 flex-1 bg-transparent py-2.5 pr-2 pl-5 text-sm focus:outline-none"
                @input="onInput"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter="onEnter"
                @keydown.escape.prevent="onEscape"
            />
            <button
                type="submit"
                class="bg-electric flex w-14 shrink-0 items-center justify-center self-stretch text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-white"
            >
                <Search class="size-4.5" aria-hidden="true" />
                <span class="sr-only">Search</span>
            </button>
        </form>

        <p :id="statusId" class="sr-only" role="status" aria-live="polite">
            {{ status }}
        </p>

        <!--
          Always in the DOM so `aria-controls` points at something real, and
          rendered inside the header's own stacking context rather than
          portalled — it is anchored to the field and never needs a focus trap,
          which is the only thing a portal would buy it.
        -->
        <div
            v-show="isExpanded"
            :id="listboxId"
            role="listbox"
            aria-label="Search suggestions"
            class="border-rule shadow-card absolute inset-x-0 top-full z-50 mt-2 max-h-[70vh] overflow-y-auto rounded-lg border bg-white py-2"
        >
            <template v-if="products.length > 0">
                <p
                    class="text-muted-foreground px-3 pt-1 pb-1.5 text-[0.625rem] font-bold tracking-[0.14em] uppercase"
                >
                    Products
                </p>
                <div
                    v-for="(product, index) in products"
                    :id="`${optionIdPrefix}-${index}`"
                    :key="`product-${product.id}`"
                    role="option"
                    :aria-selected="activeIndex === index"
                    class="flex cursor-pointer items-center gap-3 px-3 py-2"
                    :class="activeIndex === index ? 'bg-tint' : ''"
                    @mousedown.prevent="choose(suggestions[index]!)"
                    @mousemove="activeIndex = index"
                >
                    <!--
                      Decorative: the option's own text already names the
                      product, so an alt here would be read twice.
                    -->
                    <img
                        v-if="product.image"
                        :src="product.image.thumbUrl ?? product.image.url"
                        alt=""
                        class="border-rule size-10 shrink-0 rounded-md border object-contain"
                        loading="lazy"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="text-ink block truncate text-sm">
                            {{ product.name }}
                        </span>
                        <span
                            v-if="product.brandName"
                            class="text-muted-foreground block truncate text-xs"
                        >
                            {{ product.brandName }}
                        </span>
                    </span>
                    <Price
                        v-if="product.effectivePriceFormatted"
                        :formatted="product.effectivePriceFormatted"
                        size="sm"
                        class="shrink-0"
                    />
                </div>
            </template>

            <template v-if="categories.length > 0">
                <p
                    class="text-muted-foreground border-rule mt-1 border-t px-3 pt-2.5 pb-1.5 text-[0.625rem] font-bold tracking-[0.14em] uppercase"
                >
                    Categories
                </p>
                <div
                    v-for="(category, index) in categories"
                    :id="`${optionIdPrefix}-${products.length + index}`"
                    :key="`category-${category.id}`"
                    role="option"
                    :aria-selected="activeIndex === products.length + index"
                    class="flex cursor-pointer items-center gap-2.5 px-3 py-2 text-sm"
                    :class="
                        activeIndex === products.length + index ? 'bg-tint' : ''
                    "
                    @mousedown.prevent="
                        choose(suggestions[products.length + index]!)
                    "
                    @mousemove="activeIndex = products.length + index"
                >
                    <Layers
                        class="text-electric size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="text-ink truncate">{{ category.name }}</span>
                </div>
            </template>

            <template v-if="brands.length > 0">
                <p
                    class="text-muted-foreground border-rule mt-1 border-t px-3 pt-2.5 pb-1.5 text-[0.625rem] font-bold tracking-[0.14em] uppercase"
                >
                    Brands
                </p>
                <div
                    v-for="(brand, index) in brands"
                    :id="`${optionIdPrefix}-${products.length + categories.length + index}`"
                    :key="`brand-${brand.id}`"
                    role="option"
                    :aria-selected="
                        activeIndex ===
                        products.length + categories.length + index
                    "
                    class="flex cursor-pointer items-center gap-2.5 px-3 py-2 text-sm"
                    :class="
                        activeIndex ===
                        products.length + categories.length + index
                            ? 'bg-tint'
                            : ''
                    "
                    @mousedown.prevent="
                        choose(
                            suggestions[
                                products.length + categories.length + index
                            ]!,
                        )
                    "
                    @mousemove="
                        activeIndex =
                            products.length + categories.length + index
                    "
                >
                    <Tag
                        class="text-electric size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="text-ink truncate">{{ brand.name }}</span>
                </div>
            </template>
        </div>
    </div>
</template>
