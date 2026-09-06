<script setup lang="ts">
import {
    Check,
    CircleX,
    ClipboardList,
    PackageCheck,
    RotateCcw,
    Truck,
} from '@lucide/vue';
import { computed } from 'vue';
import { formatIsoDate } from '@/lib/utils';

/**
 * Where the order has got to.
 *
 * A shopper opening an order months later is asking one question — "what
 * happened to it" — and a badge answers it only if you already know the
 * vocabulary. The line of steps says where the order sits in a sequence, which
 * is the same information without the vocabulary.
 *
 * Driven entirely by what is already on the order: `status` places the marker,
 * `placedAt` and `paidAt` date the two steps that have a timestamp. Cancelled
 * and refunded are not points on the line — they are what happens instead of
 * it — so they replace the run rather than extending it.
 */
const { order } = defineProps<{ order: App.Data.OrderData }>();

/**
 * The four states an order moves through, in order. The labels are stated here
 * rather than read off the order because the server only sends the wording for
 * the status the order currently holds, and a progression has to name the steps
 * it has not reached yet.
 */
const FLOW = [
    {
        status: 'pending',
        label: 'Placed',
        description: 'We have your order.',
        icon: ClipboardList,
    },
    {
        status: 'processing',
        label: 'Processing',
        description: 'Being picked and packed.',
        icon: PackageCheck,
    },
    {
        status: 'out_for_delivery',
        label: 'Out for delivery',
        description: 'On its way to you.',
        icon: Truck,
    },
    {
        status: 'completed',
        label: 'Completed',
        description: 'Delivered and done.',
        icon: Check,
    },
] as const;

/**
 * An order that was cancelled or refunded stopped somewhere on the line, and
 * nothing on the record says where. Drawing a marker would be a guess, so the
 * run is shown at rest with the real outcome stated beside it.
 */
const stoppedStatus = computed(() =>
    order.status === 'cancelled' || order.status === 'refunded'
        ? order.status
        : null,
);

const reachedIndex = computed(() =>
    FLOW.findIndex((step) => step.status === order.status),
);

type StepState = 'done' | 'current' | 'upcoming';

const steps = computed(() =>
    FLOW.map((step, index) => {
        const state: StepState = stoppedStatus.value
            ? 'upcoming'
            : index < reachedIndex.value
              ? 'done'
              : index === reachedIndex.value
                ? 'current'
                : 'upcoming';

        const at =
            step.status === 'pending'
                ? order.placedAt
                : step.status === 'processing'
                  ? order.paidAt
                  : null;

        return { ...step, state, at };
    }),
);
</script>

<template>
    <div class="border-rule shadow-card rounded-lg border bg-white p-5 sm:p-6">
        <ol class="flex flex-col gap-6 sm:flex-row sm:gap-4">
            <li
                v-for="(step, index) in steps"
                :key="step.status"
                class="flex min-w-0 flex-1 gap-3 sm:flex-col sm:gap-2"
            >
                <!--
                  The rail is drawn per step rather than as one line behind the
                  row, so it reflows with the column at every width instead of
                  needing a second set of positions for the stacked layout.
                -->
                <div
                    class="flex shrink-0 flex-col items-center sm:w-full sm:flex-row"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-full border"
                        :class="
                            step.state === 'upcoming'
                                ? 'border-rule text-muted-foreground bg-white'
                                : step.state === 'current'
                                  ? 'bg-electric border-electric text-white'
                                  : 'bg-tint-strong border-tint-strong text-electric'
                        "
                        aria-hidden="true"
                    >
                        <component :is="step.icon" class="size-4" />
                    </span>

                    <span
                        v-if="index < steps.length - 1"
                        class="w-px flex-1 sm:h-px sm:w-auto"
                        :class="
                            step.state === 'done' ? 'bg-electric' : 'bg-rule'
                        "
                        aria-hidden="true"
                    />
                </div>

                <div class="min-w-0 pb-2 sm:pb-0">
                    <p
                        class="font-display text-sm font-bold tracking-[-0.01em]"
                        :class="
                            step.state === 'upcoming'
                                ? 'text-muted-foreground'
                                : 'text-ink'
                        "
                    >
                        {{ step.label }}
                        <span v-if="step.state === 'current'" class="sr-only">
                            — where this order is now
                        </span>
                    </p>
                    <p class="text-muted-foreground mt-0.5 text-xs leading-5">
                        {{ step.description }}
                    </p>
                    <time
                        v-if="step.at"
                        :datetime="step.at"
                        class="text-muted-foreground mt-0.5 block text-xs tabular-nums"
                    >
                        {{ formatIsoDate(step.at) }}
                    </time>
                </div>
            </li>
        </ol>

        <p
            v-if="stoppedStatus"
            class="border-rule mt-5 flex items-start gap-3 border-t pt-5 text-sm"
        >
            <component
                :is="stoppedStatus === 'refunded' ? RotateCcw : CircleX"
                class="text-destructive mt-0.5 size-4 shrink-0"
                aria-hidden="true"
            />
            <span class="text-foreground">
                This order was
                <span class="font-medium">{{ order.statusLabel }}</span
                >, so it did not run to the end.
                <template v-if="stoppedStatus === 'refunded'">
                    Anything you paid has been sent back to you.
                </template>
            </span>
        </p>
    </div>
</template>
