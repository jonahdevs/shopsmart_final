<script setup lang="ts">
import { Globe, Maximize2, Minimize2, RotateCcw } from '@lucide/vue';
import type JsVectorMap from 'jsvectormap';
import type { JsVectorMapTooltip } from 'jsvectormap';
import 'jsvectormap/dist/jsvectormap.css';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import { useAppearance } from '@/composables/useAppearance';
import { visitorMapColors } from '@/lib/charts';

/**
 * Where the shop's visitors were, as a shaded world map beside a ranked rail.
 *
 * Not an {@see AdminChart}: this is the one panel on the dashboard whose
 * question is "where", and a place is the one thing a bar chart cannot draw.
 * Fifty countries on an axis is a list nobody reads; fifty countries on a map is
 * a shape somebody recognises in a second.
 *
 * The rail beside it is what makes the map answerable rather than merely
 * decorative — a shade tells you "more" and never "how many" — and it is also
 * the keyboard route to every country, since a region on an SVG is not a
 * focusable control.
 */
const { countries } = defineProps<{
    countries: App.Data.AdminDashboardCountrySliceData[];
}>();

/**
 * Countries wide enough that focusing them at close range overshoots — the
 * library scales about a region's centre, and half of Russia is past the
 * viewport before the animation ends.
 */
const WIDE = ['RU', 'CN', 'US', 'CA', 'BR', 'AU', 'IN', 'AR', 'KZ', 'DZ'];

const container = ref<HTMLElement | null>(null);
const map = shallowRef<JsVectorMap | null>(null);
const selected = ref<string | null>(null);
const expanded = ref(false);

const { resolvedAppearance } = useAppearance();

const hasCountries = computed(() => countries.length > 0);

const byCode = computed(
    () => new Map(countries.map((country) => [country.code, country])),
);

const placed = computed(() =>
    countries.reduce((sum, country) => sum + country.count, 0),
);

/**
 * The shading range starts at zero rather than at the quietest country, so a
 * country's colour means the same thing whatever else is on the map — otherwise
 * the least-visited country is painted the palest shade on every window and its
 * colour says only "last", never "few".
 *
 * The floor rides along as an entry under a key no region uses. The library
 * reads it when it works out the range and then skips it when it paints, and it
 * also steers the library clear of its own divide-by-zero: with every country
 * tied — one resolved country is the common case — `min === max` and it returns
 * a malformed colour built by joining decimal channels, which comes out looking
 * like no data at all.
 */
const values = computed<Record<string, number>>(() => ({
    __floor: 0,
    ...Object.fromEntries(
        countries.map((country) => [country.code, country.count]),
    ),
}));

/**
 * The flag beside a country in the rail. An external host, which is the one
 * place in the back office that reaches off-site for an image: two hundred flag
 * SVGs is not something to keep in this repository, and a two-letter code is
 * all that leaves the browser.
 */
function flagUrl(code: string): string {
    return `https://flagcdn.com/${code.toLowerCase()}.svg`;
}

function tooltipFor(code: string): string {
    const country = byCode.value.get(code);

    if (!country) {
        return `<b>${code}</b><br />No visits`;
    }

    const plural = country.count === 1 ? 'visit' : 'visits';

    return `<b>${country.label}</b><br />${country.formatted} ${plural} · ${country.share}%`;
}

function focus(code: string): void {
    selected.value = code;

    map.value?.setFocus({
        region: code,
        animate: true,
        scale: WIDE.includes(code) ? 4 : 8,
    });
}

function reset(): void {
    selected.value = null;
    map.value?.reset();
}

function destroy(): void {
    map.value?.destroy();
    map.value = null;

    if (container.value) {
        container.value.innerHTML = '';
    }
}

/**
 * The library writes straight into the DOM and reads its palette once, at
 * construction, so new data, a resize into full screen or a theme flip means
 * tearing the map down and building it again rather than patching it in place.
 */
async function render(): Promise<void> {
    if (!hasCountries.value) {
        return;
    }

    /*
      Loaded on demand: the world paths alone are a hundred kilobytes of source,
      and this panel already sits behind a deferred prop. The map file registers
      itself against the constructor rather than exporting anything, so it has
      to come after it.
    */
    const { default: VectorMap } = await import('jsvectormap');
    await import('jsvectormap/dist/maps/world-merc');

    await nextTick();

    if (!container.value) {
        return;
    }

    destroy();

    const palette = visitorMapColors();

    map.value = new VectorMap({
        selector: container.value,
        map: 'world_merc',
        backgroundColor: 'transparent',
        /*
          The library's wheel handler always calls preventDefault, so the page
          cannot be scrolled with the pointer over the map. The buttons stay on
          as the way out of that for anyone who would rather not use the wheel.
        */
        zoomButtons: true,
        zoomOnScroll: true,
        zoomMax: 12,
        zoomMin: 1,
        zoomStep: 1.4,

        visualizeData: {
            scale: palette.shade,
            values: values.value,
        },

        regionStyle: {
            initial: {
                fill: palette.unvisited,
                stroke: palette.seam,
                strokeWidth: 0.4,
            },
            hover: {
                fillOpacity: 0.8,
                cursor: 'pointer',
            },
        },

        onRegionClick: (_event: MouseEvent, code: string) => focus(code),

        onRegionTooltipShow: (
            _event: Event,
            tooltip: JsVectorMapTooltip,
            code: string,
        ) => tooltip.text(tooltipFor(code), true),
    });
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && expanded.value) {
        expanded.value = false;
    }
}

/*
  `resolvedAppearance` is what makes the repaint happen. Every colour above is
  read out of a custom property at construction, and nothing else in this
  component depends on the theme — so without it a map built in light mode keeps
  its white seams and pale fills on a dark card. The class on <html> is already
  swapped by the time a watcher runs, so the rebuild reads the new tokens.
*/
watch(
    [() => countries, resolvedAppearance, expanded],
    () => void render(),
    { deep: true },
);

onMounted(() => {
    void render();
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    destroy();
});
</script>

<template>
    <AdminCard
        class="flex flex-col"
        :class="expanded ? 'fixed inset-4 z-50' : 'h-[380px]'"
    >
        <AdminCardHeader title="Visitors by country" :icon="Globe">
            <template #actions>
                <span
                    v-if="hasCountries"
                    class="text-muted-foreground text-xs tabular-nums"
                >
                    {{ placed.toLocaleString() }} located visits ·
                    {{ countries.length }}
                    countries
                </span>

                <button
                    v-if="hasCountries"
                    type="button"
                    class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-md p-1.5 transition-colors"
                    aria-label="Reset the map view"
                    @click="reset"
                >
                    <RotateCcw class="size-4" aria-hidden="true" />
                </button>

                <button
                    v-if="hasCountries"
                    type="button"
                    class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-md p-1.5 transition-colors"
                    :aria-label="expanded ? 'Exit full screen' : 'Full screen'"
                    @click="expanded = !expanded"
                >
                    <component
                        :is="expanded ? Minimize2 : Maximize2"
                        class="size-4"
                        aria-hidden="true"
                    />
                </button>
            </template>
        </AdminCardHeader>

        <div
            v-if="hasCountries"
            class="flex min-h-0 flex-1 flex-col gap-4 p-5 sm:flex-row"
        >
            <div
                ref="container"
                class="min-h-0 min-w-0 flex-1"
                role="img"
                :aria-label="`World map shaded by visits per country, ${placed.toLocaleString()} located visits in total`"
            />

            <!--
              The rail is the accessible half of this panel: an SVG region takes
              no focus and announces nothing, so every country is also a button
              here, and clicking one flies the map to it.
            -->
            <ul
                class="flex shrink-0 flex-col gap-0.5 overflow-y-auto sm:w-56"
                data-test="visitor-map-countries"
            >
                <li v-for="country in countries" :key="country.code">
                    <button
                        type="button"
                        class="hover:bg-muted flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left transition-colors"
                        :class="selected === country.code ? 'bg-muted' : ''"
                        @click="focus(country.code)"
                    >
                        <img
                            :src="flagUrl(country.code)"
                            alt=""
                            loading="lazy"
                            class="h-3.5 w-5 shrink-0 rounded-xs object-cover"
                        />
                        <span class="min-w-0 flex-1 truncate text-xs">
                            {{ country.label }}
                        </span>
                        <span class="shrink-0 text-xs font-semibold tabular-nums">
                            {{ country.formatted }}
                        </span>
                        <span
                            class="text-muted-foreground w-10 shrink-0 text-right text-xs tabular-nums"
                        >
                            {{ country.share }}%
                        </span>
                    </button>
                </li>
            </ul>
        </div>

        <!--
          A store with no located visits gets this rather than a grey world.
          Being behind a proxy that resolves a country is the exception, not the
          rule, so the copy has to say that outright — otherwise the panel reads
          as broken on every install that is simply not configured for it.
        -->
        <AdminEmptyState
            v-else
            :icon="Globe"
            title="No countries recorded"
            description="Visits carry a country only when the shop sits behind a proxy that resolves one, and only for shoppers who accept analytics."
        />
    </AdminCard>
</template>

<!--
  The library appends its tooltip and its zoom buttons to elements outside this
  component's scope, so its own custom properties are repointed at the staff
  tokens instead. Doing it through the variables keeps them following the theme
  with no override war.
-->
<style>
:root {
    --jvm-tooltip-bg-color: var(--foreground);
    --jvm-tooltip-color: var(--background);
    --jvm-tooltip-font-size: 0.75rem;
    --jvm-tooltip-padding: 0.375rem 0.625rem;
    --jvm-tooltip-radius: 0.375rem;
    --jvm-tooltip-shadow: 0 4px 12px rgb(0 0 0 / 0.14);

    --jvm-zoom-btn-bg-color: var(--card);
    --jvm-zoom-btn-color: var(--foreground);
    --jvm-zoom-btn-size: 1.75rem;
    --jvm-zoom-btn-radius: 0.375rem;
}

.jvm-tooltip {
    z-index: 60;
    line-height: 1.4;
}

.jvm-region {
    transition: fill 0.15s ease;
}

/* Stacked in the corner at a size worth aiming at, rather than the 15px the
   library ships with. */
.jvm-zoom-btn {
    left: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    font-size: 0.9375rem;
    line-height: 1;
    user-select: none;
    transition: background-color 0.15s ease;
}

.jvm-zoom-btn:hover {
    background-color: var(--muted);
}

.jvm-zoom-btn.jvm-zoomin {
    top: 0;
}

.jvm-zoom-btn.jvm-zoomout {
    top: calc(var(--jvm-zoom-btn-size) + 0.375rem);
}
</style>
