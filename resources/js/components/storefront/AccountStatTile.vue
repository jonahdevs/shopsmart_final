<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

/**
 * One counter across the top of the account dashboard.
 *
 * The whole tile is the link, not just the number: a figure on this page is
 * only worth printing if pressing it takes you to the thing it counts.
 *
 * The label sits above the number rather than below it, so a column of tiles
 * can be read as a list of questions before the eye picks up any of the
 * answers — which is the order a shopper actually scans a hub page in.
 */
defineProps<{
    label: string;
    value: number;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
}>();
</script>

<template>
    <Link
        :href="href"
        class="border-rule shadow-card hover:shadow-card-hover focus-visible:outline-electric flex h-full flex-col justify-between gap-4 rounded-lg border bg-white p-5 transition-shadow focus-visible:outline-2 focus-visible:outline-offset-2"
    >
        <div class="flex items-start justify-between gap-3">
            <span
                class="font-display text-muted-foreground text-[0.625rem] font-bold tracking-[0.16em] uppercase"
            >
                {{ label }}
            </span>
            <span
                class="bg-tint-strong text-electric flex size-8 shrink-0 items-center justify-center rounded-full"
                aria-hidden="true"
            >
                <component :is="icon" class="size-4" />
            </span>
        </div>

        <span
            class="font-display text-ink text-3xl leading-none font-extrabold tracking-[-0.02em] tabular-nums"
        >
            {{ value }}
        </span>
    </Link>
</template>
