<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import { TrendingDown, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import type { AdminTone } from '@/components/admin/tones';
import { adminTones } from '@/components/admin/tones';
import type { QueryParams } from '@/wayfinder';

/**
 * One figure at the top of an admin screen.
 *
 * Three things beyond the number, each of which earns its place:
 *
 * `change` is a period-on-period delta. It is signed, and a *fall* is not
 * automatically bad — refunds falling is good — so `invertTrend` lets the
 * caller say which direction is green rather than this component assuming
 * bigger is better.
 *
 * `href` makes the tile a link. A queue counter reading "Low stock: 14" that a
 * manager cannot click is a dead end; every count that names a filterable set
 * of records should carry the filter that produces it.
 *
 * The `spark` slot takes a sparkline, drawn full-bleed into the foot of the
 * tile. It is a shape, not a chart — no axes, no tooltip — so it reads as
 * texture on the number above it.
 */
const {
    change = null,
    invertTrend = false,
    tone = 'neutral',
} = defineProps<{
    label: string;
    value: string;
    hint?: string;
    icon?: LucideIcon;
    tone?: AdminTone;
    /** Signed percentage change against the previous period. */
    change?: number | null;
    /** Treat a fall as the good direction — refunds, cancellations, returns. */
    invertTrend?: boolean;
    href?: string | { url: string } | QueryParams;
}>();

const isUp = computed(() => (change ?? 0) >= 0);

/** Green when the movement is the direction this metric wants. */
const trendTone = computed<AdminTone>(() =>
    isUp.value !== invertTrend ? 'success' : 'danger',
);
</script>

<template>
    <AdminCard
        class="flex flex-col"
        :class="href && 'hover:border-primary/40 transition-colors'"
    >
        <component
            :is="href ? Link : 'div'"
            v-bind="href ? { href } : {}"
            class="flex flex-1 flex-col p-5"
        >
            <div class="flex items-start justify-between gap-3">
                <p
                    class="text-muted-foreground text-[0.6875rem] font-bold tracking-[0.1em] uppercase"
                >
                    {{ label }}
                </p>

                <div
                    v-if="icon"
                    class="flex size-8 shrink-0 items-center justify-center rounded-md"
                    :class="adminTones[tone].chip"
                >
                    <component :is="icon" class="size-4" aria-hidden="true" />
                </div>
            </div>

            <p
                class="font-display mt-2 text-2xl font-extrabold tracking-[-0.02em] tabular-nums"
            >
                {{ value }}
            </p>

            <div class="mt-1.5 flex items-center gap-2">
                <span
                    v-if="change !== null"
                    class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[0.625rem] font-bold tabular-nums"
                    :class="adminTones[trendTone].pill"
                >
                    <component
                        :is="isUp ? TrendingUp : TrendingDown"
                        class="size-3"
                        aria-hidden="true"
                    />
                    {{ Math.abs(change) }}%
                </span>

                <span v-if="hint" class="text-muted-foreground text-xs">
                    {{ hint }}
                </span>
            </div>
        </component>

        <!--
          Full-bleed into the card's own corners: the sparkline is the tile's
          floor, not a chart sitting inside padding.
        -->
        <div v-if="$slots.spark" class="mt-auto">
            <slot name="spark" />
        </div>
    </AdminCard>
</template>
