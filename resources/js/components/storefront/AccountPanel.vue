<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import { ChevronRight } from '@lucide/vue';
import { useId } from 'vue';

/**
 * A headed card: a bordered strip carrying a micro-caps title and an optional
 * action, then the body.
 *
 * The account area is dense with small facts — an address, an email, a row of
 * settings — and a plain card leaves the reader to work out where one fact ends
 * and the next begins. The strip does that work. It lives here rather than
 * being restated per page because three of these stacked on one screen only
 * read as a system if the strip is identical in all three.
 *
 * Distinct from SectionHeading on purpose: that one is the page's own rhythm
 * (blue eyebrow, heavy display heading, muted subtitle) and belongs to content
 * sections. This one is a container for facts, and never carries a subtitle.
 */
defineProps<{
    title: string;
    icon?: LucideIcon;
    actionHref?: NonNullable<InertiaLinkProps['href']>;
    actionLabel?: string;
    /**
     * Drop the body padding, for a panel whose content is its own list of
     * edge-to-edge rows.
     */
    flush?: boolean;
}>();

const headingId = useId();
</script>

<template>
    <!--
      `overflow-hidden` so a flush row's hover band cannot square off the
      panel's bottom corners. Rows inside must therefore draw their focus ring
      inset (`-outline-offset`), because an outset one would be clipped.
    -->
    <section
        :aria-labelledby="headingId"
        class="border-rule shadow-card overflow-hidden rounded-lg border bg-white"
    >
        <div
            class="border-rule flex items-center justify-between gap-4 border-b px-5 py-3"
        >
            <div class="flex min-w-0 items-center gap-2">
                <component
                    :is="icon"
                    v-if="icon"
                    class="text-electric size-4 shrink-0"
                    aria-hidden="true"
                />
                <h2
                    :id="headingId"
                    class="font-display text-ink truncate text-[0.6875rem] font-bold tracking-[0.14em] uppercase"
                >
                    {{ title }}
                </h2>
            </div>

            <Link
                v-if="actionHref"
                :href="actionHref"
                class="text-electric font-display focus-visible:outline-electric inline-flex shrink-0 items-center gap-1 rounded-sm text-[0.6875rem] font-bold tracking-[0.12em] uppercase transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:-outline-offset-2"
            >
                {{ actionLabel ?? 'Manage' }}
                <ChevronRight class="size-3.5" aria-hidden="true" />
            </Link>
        </div>

        <div :class="flush ? undefined : 'p-5'">
            <slot />
        </div>
    </section>
</template>
