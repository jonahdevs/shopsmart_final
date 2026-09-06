<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import OrderStatusBadge from '@/components/storefront/OrderStatusBadge.vue';
import Price from '@/components/storefront/Price.vue';
import { formatIsoDate } from '@/lib/utils';
import { show } from '@/routes/orders';

/**
 * One order in the history.
 *
 * The order number is the link, because it is what the shopper quotes to us and
 * what they scan the list for. The first few product names are shown under it
 * so the row is recognisable without opening it — a list of numbers and dates
 * tells nobody which order was which.
 */
const { order } = defineProps<{ order: App.Data.OrderData }>();

/** Enough to recognise the order by, without wrapping the row. */
const SUMMARY_LINES = 2;

const summary = computed(() => {
    const names = order.lines.slice(0, SUMMARY_LINES).map((line) => line.name);
    const remaining = order.lines.length - names.length;

    return remaining > 0
        ? `${names.join(', ')} and ${remaining} more`
        : names.join(', ');
});
</script>

<template>
    <li class="border-rule flex flex-col gap-4 border-b py-6 last:border-b-0">
        <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
            <div class="min-w-0">
                <h3
                    class="font-display text-ink text-lg font-extrabold tracking-[-0.02em]"
                >
                    <Link
                        :href="show(order.orderNumber)"
                        class="hover:text-electric focus-visible:outline-electric rounded-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                    >
                        {{ order.orderNumber }}
                    </Link>
                </h3>

                <p
                    class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs"
                >
                    <time :datetime="order.placedAt" class="tabular-nums">
                        {{ formatIsoDate(order.placedAt) }}
                    </time>
                    <span aria-hidden="true">&middot;</span>
                    <span class="tabular-nums">
                        {{ order.itemCount }}
                        {{ order.itemCount === 1 ? 'item' : 'items' }}
                    </span>
                </p>

                <p
                    v-if="summary"
                    class="text-muted-foreground mt-2 max-w-prose truncate text-sm"
                >
                    {{ summary }}
                </p>
            </div>

            <div class="flex shrink-0 flex-col items-end gap-2">
                <Price size="sm" :formatted="order.totals.totalFormatted" />
                <div class="flex flex-wrap justify-end gap-2">
                    <OrderStatusBadge
                        :label="order.statusLabel"
                        :variant="order.statusVariant"
                    />
                    <OrderStatusBadge
                        :label="order.paymentStatusLabel"
                        :variant="order.paymentStatusVariant"
                    />
                </div>
            </div>
        </div>

        <Link
            :href="show(order.orderNumber)"
            class="text-electric focus-visible:outline-electric group inline-flex w-fit items-center gap-1.5 rounded-sm text-sm font-bold transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-4"
        >
            View order
            <span class="sr-only">{{ order.orderNumber }}</span>
            <ArrowRight
                class="size-4 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none"
                aria-hidden="true"
            />
        </Link>
    </li>
</template>
