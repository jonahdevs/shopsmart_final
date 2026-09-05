<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';

/**
 * The storefront's section rhythm, in one place.
 *
 * A blue letter-spaced eyebrow, a heavy display title, a muted one-line
 * subtitle, and an optional blue "View all" pushed to the right of the title —
 * every section on the shop composes this rather than restating the pattern,
 * which is what keeps the page reading as one document.
 *
 * The link sits in a row with the heading rather than below the subtitle so it
 * stays on the title's line at every width, wrapping under it only when the two
 * no longer fit.
 */
defineProps<{
    title: string;
    /** Blue micro-caps label above the title. */
    eyebrow?: string;
    /** One muted line under the title. Keep it to a sentence. */
    subtitle?: string;
    /** Set when the surrounding <section> names itself with aria-labelledby. */
    headingId?: string;
    viewAllHref?: NonNullable<InertiaLinkProps['href']>;
    viewAllLabel?: string;
}>();
</script>

<template>
    <div>
        <p
            v-if="eyebrow"
            class="text-electric font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase"
        >
            {{ eyebrow }}
        </p>

        <div
            class="flex flex-wrap items-center justify-between gap-x-6 gap-y-1"
        >
            <h2
                :id="headingId"
                class="font-display text-ink text-2xl font-extrabold tracking-[-0.03em] sm:text-[1.75rem]"
            >
                {{ title }}
            </h2>

            <Link
                v-if="viewAllHref"
                :href="viewAllHref"
                class="text-electric focus-visible:outline-electric group inline-flex shrink-0 items-center gap-1.5 rounded-sm text-sm font-bold transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                {{ viewAllLabel ?? 'View all' }}
                <ArrowRight
                    class="size-4 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none"
                    aria-hidden="true"
                />
            </Link>
        </div>

        <p
            v-if="subtitle"
            class="text-muted-foreground mt-1 text-sm sm:text-base"
        >
            {{ subtitle }}
        </p>
    </div>
</template>
