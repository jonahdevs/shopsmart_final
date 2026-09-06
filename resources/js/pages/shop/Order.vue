<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CircleCheck,
    Clock,
    CreditCard,
    Download,
    MapPin,
    PackageCheck,
    Truck,
} from '@lucide/vue';
import { computed } from 'vue';
import CheckoutLineItem from '@/components/storefront/CheckoutLineItem.vue';
import CheckoutSummary from '@/components/storefront/CheckoutSummary.vue';
import OrderStatusBadge from '@/components/storefront/OrderStatusBadge.vue';
import OrderTimeline from '@/components/storefront/OrderTimeline.vue';
import SectionHeading from '@/components/storefront/SectionHeading.vue';
import StoreBreadcrumbs from '@/components/storefront/StoreBreadcrumbs.vue';
import { formatIsoDate } from '@/lib/utils';
import { catalog } from '@/routes';
import { index as ordersIndex, receipt } from '@/routes/orders';
import { show as pay } from '@/routes/payment';

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

/** Stated once so the heading and the list cannot disagree about the count. */
const itemCountLabel = computed(
    () => `${order.itemCount} ${order.itemCount === 1 ? 'item' : 'items'}`,
);

/** "bank_transfer" is a column value, not something to show a shopper. */
const paymentMethodLabel = computed(() =>
    order.paymentMethod === null
        ? null
        : order.paymentMethod.replace(/_/g, ' '),
);

/**
 * Only the gateway has something to press.
 *
 * A bank transfer or a cash-on-delivery order is equally unpaid, but nothing on
 * this site can settle it — offering a "pay now" button for either would send
 * the shopper to a page that cannot take their money.
 */
const canPayNow = computed(
    () => order.awaitsPayment && order.paymentMethod === 'paystack',
);

/** What "still owes money" actually means for the method that was chosen. */
const outstandingCopy = computed(() => {
    switch (order.paymentMethod) {
        case 'paystack':
            return 'Payment is still outstanding. Settle it here and we will start packing straight away.';
        case 'bank_transfer':
            return `Payment is still outstanding. Transfer the total to the account details we sent to ${order.customerEmail}, quoting ${order.orderNumber} as the reference.`;
        case 'cash_on_delivery':
            return isCollection.value
                ? 'Payment is still outstanding. Have the total ready in cash when you collect.'
                : 'Payment is still outstanding. Have the total ready in cash when your order arrives.';
        default:
            return 'Payment is still outstanding. We will be in touch with how to settle it.';
    }
});
</script>

<template>
    <Head :title="`Order ${order.orderNumber}`" />

    <div class="container flex flex-col gap-16 py-8">
        <section aria-labelledby="order-heading">
            <StoreBreadcrumbs :items="breadcrumbs" />

            <div class="mt-6">
                <p
                    class="text-electric font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase"
                >
                    Your order
                </p>
                <h1
                    id="order-heading"
                    class="font-display text-ink mt-1 text-2xl font-extrabold tracking-[-0.03em] sm:text-4xl"
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
                <div class="flex min-w-0 flex-col gap-12">
                    <section aria-labelledby="order-progress-heading">
                        <SectionHeading
                            eyebrow="Progress"
                            title="Where it has got to"
                            heading-id="order-progress-heading"
                        />

                        <div class="mt-6">
                            <OrderTimeline :order="order" />
                        </div>
                    </section>

                    <section
                        aria-labelledby="order-next-heading"
                        class="border-rule shadow-card rounded-lg border bg-white p-5 sm:p-6"
                    >
                        <h2
                            id="order-next-heading"
                            class="font-display text-ink text-lg font-extrabold tracking-[-0.02em]"
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
                              An order that still owes money says so plainly
                              rather than pretending it is settled, and says
                              what settling it involves — which is a button
                              only for the gateway; the offline methods have
                              nothing to press.
                            -->
                            <li
                                v-if="order.awaitsPayment"
                                class="flex items-start gap-3"
                            >
                                <Clock
                                    class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <div class="flex flex-col items-start gap-3">
                                    <span class="text-foreground">
                                        {{ outstandingCopy }}
                                    </span>

                                    <Link
                                        v-if="canPayNow"
                                        :href="pay(order.orderNumber)"
                                        class="bg-electric font-display focus-visible:outline-electric inline-flex h-10 items-center justify-center gap-2 rounded-lg px-6 text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
                                    >
                                        <CreditCard
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Pay {{ order.totals.totalFormatted }}
                                    </Link>
                                </div>
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
                        <SectionHeading
                            eyebrow="Where it goes"
                            :title="
                                isCollection
                                    ? 'Collection point'
                                    : 'Delivering to'
                            "
                            heading-id="order-destination-heading"
                        />

                        <div
                            class="border-rule shadow-card mt-6 flex items-start gap-3 rounded-lg border bg-white p-5 text-sm"
                        >
                            <MapPin
                                class="text-electric mt-0.5 size-4 shrink-0"
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
                        <SectionHeading
                            eyebrow="You told us"
                            title="Your note"
                            heading-id="order-note-heading"
                        />
                        <p
                            class="border-rule shadow-card text-muted-foreground mt-6 rounded-lg border bg-white p-5 text-sm leading-relaxed whitespace-pre-line"
                        >
                            {{ order.customerNote }}
                        </p>
                    </section>

                    <section aria-labelledby="order-items-heading">
                        <SectionHeading
                            eyebrow="In the box"
                            title="What you bought"
                            :subtitle="itemCountLabel"
                            heading-id="order-items-heading"
                        />

                        <ul
                            class="border-rule divide-rule shadow-card mt-6 divide-y rounded-lg border bg-white"
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
                    class="border-rule shadow-card rounded-lg border bg-white lg:sticky lg:top-28"
                    aria-labelledby="order-summary-heading"
                >
                    <div class="flex flex-col gap-5 p-5 sm:p-6">
                        <h2
                            id="order-summary-heading"
                            class="font-display text-ink text-lg font-extrabold tracking-[-0.02em]"
                        >
                            Summary
                        </h2>

                        <CheckoutSummary :totals="order.totals" />

                        <div
                            v-if="paymentMethodLabel"
                            class="border-rule border-t pt-5"
                        >
                            <p
                                class="font-display text-muted-foreground text-[0.625rem] font-bold tracking-[0.14em] uppercase"
                            >
                                Paying by
                            </p>
                            <p class="text-foreground mt-1 text-sm capitalize">
                                {{ paymentMethodLabel }}
                            </p>
                        </div>

                        <p
                            v-if="order.awaitsPayment"
                            class="bg-tint-strong text-electric rounded-lg px-3 py-2 text-xs leading-relaxed"
                        >
                            This order is not paid for yet.
                        </p>

                        <!--
                          A plain anchor, not a <Link>: the receipt is a PDF the
                          browser downloads, and an Inertia visit would ask the
                          server for a page and get a file it cannot render.
                        -->
                        <a
                            :href="receipt.url(order.orderNumber)"
                            class="border-ink hover:bg-ink font-display focus-visible:outline-electric text-foreground inline-flex h-10 items-center justify-center gap-2 rounded-lg border text-sm font-bold tracking-wide transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                        >
                            <Download class="size-4" aria-hidden="true" />
                            Download receipt
                        </a>

                        <div
                            class="border-rule flex flex-col items-start gap-3 border-t pt-5"
                        >
                            <Link
                                :href="ordersIndex()"
                                class="text-electric focus-visible:outline-electric rounded-sm text-sm font-bold transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-4"
                            >
                                All your orders
                            </Link>

                            <Link
                                :href="catalog()"
                                class="text-electric focus-visible:outline-electric rounded-sm text-sm font-bold transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-4"
                            >
                                Keep shopping
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </div>
</template>
