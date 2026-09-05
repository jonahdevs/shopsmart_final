<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck, Clock, MapPin, PackageCheck, Truck } from '@lucide/vue';
import { computed } from 'vue';
import CheckoutLineItem from '@/components/storefront/CheckoutLineItem.vue';
import CheckoutSummary from '@/components/storefront/CheckoutSummary.vue';
import OrderStatusBadge from '@/components/storefront/OrderStatusBadge.vue';
import StoreBreadcrumbs from '@/components/storefront/StoreBreadcrumbs.vue';
import { formatIsoDate } from '@/lib/utils';
import { catalog } from '@/routes';
import { index as ordersIndex } from '@/routes/orders';

/**
 * One order: the confirmation the shopper lands on, and the page they come back
 * to months later. Deliberately the same component — an order that reads
 * differently the second time you open it is an order you cannot trust.
 *
 * Everything shown is the frozen record on the order row, priced by the same
 * {@see CheckoutSummary} the checkout page used, so the two cannot disagree.
 */
const { order } = defineProps<{
    order: App.Data.OrderData;
    pickupAddress: string;
    breadcrumbs: App.Data.BreadcrumbData[];
}>();

const isCollection = computed(() => order.totals.deliveryMethod === 'pickup');

/** "bank_transfer" is a column value, not something to show a shopper. */
const paymentMethodLabel = computed(() =>
    order.paymentMethod === null
        ? null
        : order.paymentMethod.replace(/_/g, ' '),
);
</script>

<template>
    <Head :title="`Order ${order.orderNumber}`" />

    <div class="flex flex-col gap-16 px-4 py-8 sm:px-6 lg:px-8">
        <section aria-labelledby="order-heading">
            <StoreBreadcrumbs :items="breadcrumbs" />

            <div class="mt-6">
                <span class="bg-electric block h-0.5 w-8" aria-hidden="true" />
                <h1
                    id="order-heading"
                    class="font-display text-foreground mt-3 text-2xl font-black tracking-[-0.035em] uppercase sm:text-4xl"
                >
                    Order {{ order.orderNumber }}
                </h1>

                <div
                    class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm"
                >
                    <OrderStatusBadge
                        :label="order.statusLabel"
                        :variant="order.statusVariant"
                    />
                    <OrderStatusBadge
                        :label="order.paymentStatusLabel"
                        :variant="order.paymentStatusVariant"
                    />
                    <p class="text-muted-foreground">
                        Placed
                        <time :datetime="order.placedAt" class="tabular-nums">
                            {{ formatIsoDate(order.placedAt) }}
                        </time>
                    </p>
                </div>
            </div>

            <div
                class="mt-10 grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_20rem]"
            >
                <div class="flex min-w-0 flex-col gap-10">
                    <section
                        aria-labelledby="order-next-heading"
                        class="bg-card rounded-xs p-6"
                    >
                        <h2
                            id="order-next-heading"
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            What happens next
                        </h2>

                        <ol class="mt-4 flex flex-col gap-4 text-sm">
                            <li class="flex items-start gap-3">
                                <CircleCheck
                                    class="text-electric mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="text-foreground">
                                    We have your order. A copy is on its way to
                                    {{ order.customerEmail }}.
                                </span>
                            </li>

                            <!--
                              Payment is Phase 5. Until the gateway lands, an
                              order that still owes money says so plainly rather
                              than pretending it is settled; the Pay button goes
                              here, driven by the same `awaitsPayment` flag.
                            -->
                            <li
                                v-if="order.awaitsPayment"
                                class="flex items-start gap-3"
                            >
                                <Clock
                                    class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="text-foreground">
                                    Payment is still outstanding. We will be in
                                    touch with how to settle it, and paying from
                                    this page is coming shortly.
                                </span>
                            </li>
                            <li v-else class="flex items-start gap-3">
                                <CircleCheck
                                    class="text-electric mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="text-foreground">
                                    Payment received. Nothing more to pay.
                                </span>
                            </li>

                            <li class="flex items-start gap-3">
                                <component
                                    :is="isCollection ? PackageCheck : Truck"
                                    class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="text-foreground">
                                    <template v-if="isCollection">
                                        We will let you know the moment it is
                                        packed and ready to collect.
                                    </template>
                                    <template v-else>
                                        We will pack it and let you know when it
                                        is on its way to you.
                                    </template>
                                </span>
                            </li>
                        </ol>
                    </section>

                    <section aria-labelledby="order-destination-heading">
                        <h2
                            id="order-destination-heading"
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            {{
                                isCollection
                                    ? 'Collection point'
                                    : 'Delivering to'
                            }}
                        </h2>

                        <div
                            class="border-rule mt-4 flex items-start gap-3 rounded-xs border p-4 text-sm"
                        >
                            <MapPin
                                class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />

                            <div v-if="isCollection" class="min-w-0">
                                <p class="text-foreground">
                                    {{ pickupAddress }}
                                </p>
                                <p class="text-muted-foreground mt-1">
                                    Bring your order number with you.
                                </p>
                            </div>

                            <div
                                v-else-if="order.shippingAddress"
                                class="min-w-0"
                            >
                                <p class="text-foreground font-medium">
                                    {{ order.shippingAddress.fullName }}
                                </p>
                                <p
                                    class="text-muted-foreground mt-1 leading-relaxed"
                                >
                                    {{ order.shippingAddress.summary }}
                                </p>
                                <p
                                    v-if="order.shippingAddress.phone"
                                    class="text-muted-foreground mt-1 tabular-nums"
                                >
                                    {{ order.shippingAddress.phone }}
                                </p>
                                <p
                                    v-if="order.shippingAddress.deliveryNotes"
                                    class="text-muted-foreground mt-2"
                                >
                                    {{ order.shippingAddress.deliveryNotes }}
                                </p>
                            </div>

                            <p v-else class="text-muted-foreground">
                                No delivery address was recorded on this order.
                            </p>
                        </div>
                    </section>

                    <section
                        v-if="order.customerNote"
                        aria-labelledby="order-note-heading"
                    >
                        <h2
                            id="order-note-heading"
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            Your note
                        </h2>
                        <p
                            class="text-muted-foreground mt-4 text-sm leading-relaxed whitespace-pre-line"
                        >
                            {{ order.customerNote }}
                        </p>
                    </section>

                    <section aria-labelledby="order-items-heading">
                        <h2
                            id="order-items-heading"
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            What you bought
                        </h2>
                        <p
                            class="text-muted-foreground mt-2 text-sm tabular-nums"
                        >
                            {{ order.itemCount }}
                            {{ order.itemCount === 1 ? 'item' : 'items' }}
                        </p>

                        <ul
                            class="border-rule divide-rule mt-4 divide-y border-t"
                        >
                            <CheckoutLineItem
                                v-for="(line, position) in order.lines"
                                :key="`${line.productId}-${line.variantId}-${position}`"
                                :line="line"
                            />
                        </ul>
                    </section>
                </div>

                <section
                    class="bg-card rounded-xs lg:sticky lg:top-28"
                    aria-labelledby="order-summary-heading"
                >
                    <span
                        class="bg-ink block h-0.5 w-full"
                        aria-hidden="true"
                    />

                    <div class="flex flex-col gap-5 p-6">
                        <h2
                            id="order-summary-heading"
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            Summary
                        </h2>

                        <CheckoutSummary :totals="order.totals" />

                        <div
                            v-if="paymentMethodLabel"
                            class="border-rule border-t pt-5"
                        >
                            <p
                                class="text-muted-foreground text-[0.625rem] font-semibold tracking-[0.14em] uppercase"
                            >
                                Paying by
                            </p>
                            <p class="text-foreground mt-1 text-sm capitalize">
                                {{ paymentMethodLabel }}
                            </p>
                        </div>

                        <p
                            v-if="order.awaitsPayment"
                            class="bg-accent text-accent-foreground rounded-xs px-3 py-2 text-xs leading-relaxed"
                        >
                            This order is not paid for yet.
                        </p>

                        <Link
                            :href="ordersIndex()"
                            class="font-display text-electric hover:border-electric focus-visible:outline-electric self-start border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                        >
                            All your orders
                        </Link>

                        <Link
                            :href="catalog()"
                            class="font-display text-electric hover:border-electric focus-visible:outline-electric self-start border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                        >
                            Keep shopping
                        </Link>
                    </div>
                </section>
            </div>
        </section>
    </div>
</template>
