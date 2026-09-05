<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pause, Play } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
} from '@/components/ui/carousel';
import type { CarouselApi } from '@/components/ui/carousel';
import { cn } from '@/lib/utils';

const { slides } = defineProps<{ slides: App.Data.HeroSlideData[] }>();

const ADVANCE_MS = 6500;

const api = ref<CarouselApi>();
const current = ref(0);
const hovering = ref(false);
const reduceMotion = useMediaQuery('(prefers-reduced-motion: reduce)');

/**
 * The explicit control, distinct from the transient hover/focus pause. Pointer
 * hover is unavailable to touch users, so WCAG 2.2.2 needs a real switch they
 * can operate — this one wins over the hover state in the pausing direction.
 */
const playing = ref(true);

const paused = computed(() => !playing.value || hovering.value);

let timer: ReturnType<typeof setInterval> | undefined;

function stop(): void {
    if (timer) {
        clearInterval(timer);
        timer = undefined;
    }
}

/**
 * Autoplay is driven here rather than by an embla plugin so the progress ticks
 * below stay in step with it, and so it can be switched off wholesale when the
 * viewer prefers reduced motion.
 */
watch([api, paused, reduceMotion], () => {
    stop();

    if (!api.value || paused.value || reduceMotion.value || slides.length < 2) {
        return;
    }

    timer = setInterval(() => api.value?.scrollNext(), ADVANCE_MS);
});

watch(api, (carousel) => {
    if (!carousel) {
        return;
    }

    current.value = carousel.selectedScrollSnap();
    carousel.on(
        'select',
        () => (current.value = carousel.selectedScrollSnap()),
    );
});

onBeforeUnmount(stop);

const ALIGNMENTS: Record<string, string> = {
    left: 'justify-start text-left',
    center: 'justify-center text-center',
    right: 'justify-end text-right',
};

/** Alignment and theme are free-text on the model, so both fall back safely. */
function alignmentClass(value: string): string {
    return ALIGNMENTS[value] ?? ALIGNMENTS.left;
}

function slabClass(theme: string): string {
    return theme === 'light' ? 'bg-ink/85 text-white' : 'bg-white/90 text-ink';
}

const CTA_CLASS =
    'mt-5 inline-block rounded-xs bg-electric px-5 py-2.5 font-display text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric';

/**
 * Slide targets are authored free-text, so most are app-relative paths from
 * `route(..., absolute: false)` but some point off-site. Internal ones must go
 * through `<Link>` or the click tears the SPA down and reloads the document.
 */
function isInternal(url: string): boolean {
    return url.startsWith('/');
}
</script>

<template>
    <section
        v-if="slides.length"
        @mouseenter="hovering = true"
        @mouseleave="hovering = false"
        @focusin="hovering = true"
        @focusout="hovering = false"
    >
        <!--
          `<Carousel>` already emits `role="region"` and the carousel
          roledescription, and `<CarouselItem>` the slide one, so the name is
          hung on the primitives rather than restated on wrappers.
        -->
        <Carousel
            :opts="{ loop: true }"
            class="w-full"
            aria-label="Featured promotions"
            @init-api="(value) => (api = value)"
        >
            <CarouselContent class="ml-0">
                <CarouselItem
                    v-for="(slide, index) in slides"
                    :key="slide.id"
                    class="pl-0"
                    :aria-label="`${index + 1} of ${slides.length}`"
                >
                    <div
                        class="bg-secondary relative aspect-[4/3] w-full overflow-hidden sm:aspect-[21/9] lg:aspect-[2181/624]"
                    >
                        <picture v-if="slide.desktopImage">
                            <source
                                v-if="slide.mobileImage"
                                :srcset="slide.mobileImage.url"
                                media="(max-width: 640px)"
                            />
                            <img
                                :src="slide.desktopImage.url"
                                :alt="slide.desktopImage.alt"
                                :loading="index === 0 ? 'eager' : 'lazy'"
                                :fetchpriority="index === 0 ? 'high' : 'auto'"
                                decoding="async"
                                class="size-full object-cover"
                            />
                        </picture>

                        <div
                            :class="
                                cn(
                                    'absolute inset-0 flex items-center px-4 sm:px-10 lg:px-16',
                                    alignmentClass(slide.alignment),
                                )
                            "
                        >
                            <!--
                              A solid slab rather than a gradient scrim: the
                              headline reads like a signwritten name board, and
                              contrast is guaranteed whatever the artwork does.
                            -->
                            <div
                                :class="
                                    cn(
                                        'max-w-md p-5 sm:p-7',
                                        slabClass(slide.textTheme),
                                    )
                                "
                            >
                                <h2
                                    class="font-display text-2xl leading-[1.05] font-black tracking-[-0.035em] uppercase sm:text-4xl lg:text-5xl"
                                >
                                    {{ slide.headline }}
                                </h2>
                                <p
                                    v-if="slide.subheadline"
                                    class="mt-3 text-sm leading-relaxed opacity-80 sm:text-base"
                                >
                                    {{ slide.subheadline }}
                                </p>
                                <template v-if="slide.hasCallToAction">
                                    <Link
                                        v-if="isInternal(slide.ctaUrl!)"
                                        :href="slide.ctaUrl!"
                                        :class="CTA_CLASS"
                                    >
                                        {{ slide.ctaLabel }}
                                    </Link>
                                    <a
                                        v-else
                                        :href="slide.ctaUrl!"
                                        rel="noopener noreferrer"
                                        :class="CTA_CLASS"
                                    >
                                        {{ slide.ctaLabel }}
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>
                </CarouselItem>
            </CarouselContent>
        </Carousel>

        <!--
          Ticks double as autoplay progress: the active one fills over the
          advance interval, so the control tells you when the slide will change
          instead of just which one you are on.
        -->
        <div
            v-if="slides.length > 1"
            class="flex items-center gap-3 px-4 py-3 sm:px-6 lg:px-8"
        >
            <div class="flex flex-1 gap-1.5">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.id"
                    type="button"
                    class="group bg-rule focus-visible:outline-electric h-1 flex-1 focus-visible:outline-2 focus-visible:outline-offset-4"
                    :aria-label="`Go to slide ${index + 1}`"
                    :aria-current="index === current"
                    @click="api?.scrollTo(index)"
                >
                    <span
                        class="bg-electric block h-full"
                        :class="index === current ? 'hero-tick' : 'w-0'"
                        :style="{
                            animationDuration: `${ADVANCE_MS}ms`,
                            animationPlayState: paused ? 'paused' : 'running',
                        }"
                    />
                </button>
            </div>

            <!--
              Hover and focus pause the rotation, but neither is reachable by
              touch, so the control has to exist for the timer to be stoppable
              at all (WCAG 2.2.2). Hidden when reduced motion already stopped it.
            -->
            <button
                v-if="!reduceMotion"
                type="button"
                class="text-muted-foreground hover:text-foreground focus-visible:outline-electric shrink-0 rounded-xs p-1 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
                :aria-label="
                    playing
                        ? 'Pause automatic slide rotation'
                        : 'Play automatic slide rotation'
                "
                @click="playing = !playing"
            >
                <Pause v-if="playing" class="size-4" aria-hidden="true" />
                <Play v-else class="size-4" aria-hidden="true" />
            </button>
        </div>
    </section>
</template>

<style scoped>
.hero-tick {
    width: 100%;
    animation-name: hero-fill;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
}

@keyframes hero-fill {
    from {
        width: 0;
    }
    to {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-tick {
        animation: none;
        width: 100%;
    }
}
</style>
