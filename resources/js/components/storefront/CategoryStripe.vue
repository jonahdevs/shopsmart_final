<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { show } from '@/routes/category';

export type NavCategory = {
    name: string;
    slug: string;
};

const { categories } = defineProps<{ categories: NavCategory[] }>();

const { currentUrl } = useCurrentUrl();

const links = ref<HTMLElement[]>([]);
const hovered = ref<number | null>(null);

/**
 * `<Link>` is a component, so its template ref hands back an instance rather
 * than the anchor. Unwrap to the real element the marker has to measure.
 */
function setLink(
    el: Element | ComponentPublicInstance | null,
    index: number,
): void {
    const node = el && '$el' in el ? el.$el : el;

    if (node instanceof HTMLElement) {
        links.value[index] = node;
    }
}

const activeIndex = computed(() =>
    categories.findIndex((c) =>
        currentUrl.value.startsWith(toUrl(show(c.slug)) ?? ''),
    ),
);

/**
 * A single pinstripe slides between categories rather than each link owning its
 * own underline — the livery rule is continuous, and the movement is the point.
 * Falls back to a static rule under the active item when motion is reduced.
 */
const marker = ref({ left: 0, width: 0, visible: false });

const trackedIndex = computed(() =>
    hovered.value !== null ? hovered.value : activeIndex.value,
);

function syncMarker(): void {
    const el = trackedIndex.value >= 0 ? links.value[trackedIndex.value] : null;

    marker.value = el
        ? { left: el.offsetLeft, width: el.offsetWidth, visible: true }
        : { ...marker.value, visible: false };
}

/**
 * `links` is filled in place by the `:ref` callbacks, which never retriggers a
 * watcher, and on first render those callbacks have not run yet — so the
 * initial measurement is taken once the DOM exists rather than by watching the
 * array. `post` flush keeps later measurements after the links have re-rendered.
 */
watch(trackedIndex, syncMarker, { flush: 'post' });

onMounted(() => void nextTick(syncMarker));
</script>

<template>
    <nav
        class="border-t border-white/10"
        aria-label="Product categories"
        @mouseleave="hovered = null"
    >
        <!--
          The marker lives inside the scroller, not the nav: `offsetLeft` is
          measured against this box and ignores `scrollLeft`, so anchoring it
          outside left the stripe stranded once the strip was swiped sideways.
        -->
        <div
            class="relative flex scrollbar-none gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8"
        >
            <Link
                v-for="(category, index) in categories"
                :key="category.slug"
                :ref="(el) => setLink(el, index)"
                :href="show(category.slug)"
                class="font-display shrink-0 py-3 text-[0.8125rem] font-semibold tracking-[-0.005em] whitespace-nowrap text-white/70 transition-colors hover:text-white focus-visible:text-white focus-visible:outline-none"
                :class="{ 'text-white': index === activeIndex }"
                @mouseenter="hovered = index"
                @focus="hovered = index"
            >
                {{ category.name }}
            </Link>

            <span
                aria-hidden="true"
                class="bg-electric pointer-events-none absolute bottom-0 h-0.5 transition-[left,width,opacity] duration-300 ease-out motion-reduce:transition-none"
                :style="{
                    left: `${marker.left}px`,
                    width: `${marker.width}px`,
                    opacity: marker.visible ? 1 : 0,
                }"
            />
        </div>
    </nav>
</template>

<style scoped>
.scrollbar-none {
    scrollbar-width: none;
}
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
</style>
