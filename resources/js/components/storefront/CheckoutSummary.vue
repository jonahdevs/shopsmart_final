<script setup lang="ts">
import Price from '@/components/storefront/Price.vue';

/**
 * The money panel, rendered from the server's own arithmetic.
 *
 * One component for checkout and for the placed order, because both pages are
 * handed the same {@see App.Data.OrderTotalsData} — one built by the pricer,
 * one read back off the order row. Writing the rows twice is how the two pages
 * would eventually come to disagree about what a number means.
 *
 * Every figure here is a preformatted string. Nothing is added up, nothing is
 * formatted: the only judgements made locally are which rows to show at all.
 */
defineProps<{ totals: App.Data.OrderTotalsData }>();
</script>

<template>
    <dl class="flex flex-col gap-3 text-sm">
        <div class="flex items-baseline justify-between gap-4">
            <dt class="text-muted-foreground">Subtotal</dt>
            <dd class="text-foreground tabular-nums">
                {{ totals.subtotalFormatted }}
            </dd>
        </div>

        <!-- The one place the sale colour is allowed: money coming off. -->
        <div
            v-if="totals.discountCents > 0"
            class="flex items-baseline justify-between gap-4"
        >
            <dt
                class="text-muted-foreground flex flex-wrap items-baseline gap-2"
            >
                Discount
                <span
                    v-if="totals.couponCode"
                    class="bg-muted text-muted-foreground font-display rounded-full px-2 py-0.5 text-[0.625rem] font-bold tracking-[0.12em] uppercase"
                >
                    {{ totals.couponCode }}
                </span>
            </dt>
            <dd class="text-sale tabular-nums">
                &minus;{{ totals.discountFormatted }}
            </dd>
        </div>

        <div class="flex items-baseline justify-between gap-4">
            <dt class="text-muted-foreground">
                {{
                    totals.deliveryMethod === 'pickup'
                        ? 'Collection'
                        : 'Delivery'
                }}
            </dt>
            <dd class="text-foreground tabular-nums">
                <template v-if="totals.shippingCents === 0">Free</template>
                <template v-else>{{ totals.shippingFormatted }}</template>
            </dd>
        </div>

        <div class="flex items-baseline justify-between gap-4">
            <dt class="text-muted-foreground">{{ totals.taxLabel }}</dt>
            <dd class="text-foreground tabular-nums">
                {{ totals.taxFormatted }}
            </dd>
        </div>

        <div
            class="border-rule flex items-end justify-between gap-4 border-t pt-4"
        >
            <dt
                class="font-display text-ink text-sm font-extrabold tracking-[-0.01em]"
            >
                Total
            </dt>
            <dd>
                <Price size="md" :formatted="totals.totalFormatted" />
            </dd>
        </div>
    </dl>
</template>
