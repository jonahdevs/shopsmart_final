<script setup lang="ts">
import { ImageOff } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import type { ComponentPublicInstance } from 'vue';

/**
 * The product page's image column.
 *
 * Every rendition is already resolved server-side on ImageData, so nothing here
 * knows a conversion name: the big frame takes the zoom rendition (falling back
 * to the card one), the strip takes the thumb rendition, and the inlined lqip
 * paints as the frame's background so the frame is never empty and never
 * shifts once the real file lands.
 *
 * The server sends the gallery cover-first; that order is rendered as-is.
 */
const { images, activeUrl = null } = defineProps<{
    images: App.Data.ImageData[];
    /** Url of the image the currently selected variant carries, if it has one. */
    activeUrl?: string | null;
}>();

const activeIndex = ref<number>(0);
const thumbs = ref<HTMLButtonElement[]>([]);

const active = computed<App.Data.ImageData | null>(
    () => images[activeIndex.value] ?? images[0] ?? null,
);

/**
 * A variant's own photograph is appended to the gallery by the page, so
 * selecting a variant is just a jump to the slide that already exists.
 */
watch(
    [() => images, () => activeUrl],
    ([gallery, url]) => {
        const index =
            url === null ? -1 : gallery.findIndex((image) => image.url === url);

        if (index !== -1) {
            activeIndex.value = index;

            return;
        }

        if (activeIndex.value >= gallery.length) {
            activeIndex.value = 0;
        }
    },
    { immediate: true },
);

function setThumb(
    el: Element | ComponentPublicInstance | null,
    index: number,
): void {
    if (el instanceof HTMLButtonElement) {
        thumbs.value[index] = el;
    }
}

function selectThumb(index: number): void {
    activeIndex.value = index;

    void nextTick(() => {
        thumbs.value[index]?.focus();
    });
}

/**
 * Arrow keys walk the strip and move the selection with the focus, which is
 * what a shopper expects from a row of thumbnails. Every thumbnail stays in the
 * tab order too, so the gallery is operable without knowing that.
 */
function onThumbKeydown(event: KeyboardEvent, index: number): void {
    const last = images.length - 1;

    let next: number | null = null;

    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        next = index === last ? 0 : index + 1;
    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        next = index === 0 ? last : index - 1;
    } else if (event.key === 'Home') {
        next = 0;
    } else if (event.key === 'End') {
        next = last;
    }

    if (next === null) {
        return;
    }

    event.preventDefault();
    selectThumb(next);
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            class="relative aspect-square w-full overflow-hidden rounded-xs bg-white"
            :style="
                active?.placeholder
                    ? {
                          backgroundImage: `url(${active.placeholder})`,
                          backgroundSize: 'cover',
                      }
                    : undefined
            "
        >
            <picture v-if="active">
                <source
                    v-if="active.zoomWebpUrl ?? active.webpUrl"
                    :srcset="active.zoomWebpUrl ?? active.webpUrl ?? undefined"
                    type="image/webp"
                />
                <!-- The product photograph is this page's LCP, so it is never lazy. -->
                <img
                    :src="active.zoomUrl ?? active.url"
                    :alt="active.alt"
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                    class="size-full object-contain"
                />
            </picture>

            <div
                v-else
                class="text-muted-foreground flex size-full flex-col items-center justify-center gap-2"
            >
                <ImageOff class="size-8" aria-hidden="true" />
                <p class="text-xs">No photograph yet</p>
            </div>
        </div>

        <!--
          The strip scrolls inside itself rather than wrapping, so a long
          gallery never widens the document on a narrow screen.
        -->
        <ul
            v-if="images.length > 1"
            class="flex gap-3 overflow-x-auto pb-1"
            :aria-label="`${images.length} product images`"
        >
            <li v-for="(image, index) in images" :key="image.url">
                <button
                    :ref="(el) => setThumb(el, index)"
                    type="button"
                    :aria-pressed="index === activeIndex"
                    class="focus-visible:outline-electric block size-16 shrink-0 overflow-hidden rounded-xs bg-white transition-[box-shadow] ring-inset focus-visible:outline-2 focus-visible:outline-offset-2 sm:size-20"
                    :class="
                        index === activeIndex
                            ? 'ring-electric ring-2'
                            : 'ring-rule hover:ring-ink ring-1'
                    "
                    @click="selectThumb(index)"
                    @keydown="onThumbKeydown($event, index)"
                >
                    <img
                        :src="image.thumbUrl ?? image.url"
                        :alt="`View image ${index + 1}: ${image.alt}`"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-contain"
                    />
                </button>
            </li>
        </ul>
    </div>
</template>
