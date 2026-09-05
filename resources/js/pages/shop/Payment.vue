<script setup lang="ts">
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CreditCard,
    Landmark,
    Loader2,
    Lock,
    Mail,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import CheckoutLineItem from '@/components/storefront/CheckoutLineItem.vue';
import CheckoutSummary from '@/components/storefront/CheckoutSummary.vue';
import Price from '@/components/storefront/Price.vue';
import StoreBreadcrumbs from '@/components/storefront/StoreBreadcrumbs.vue';
import type { PaystackPopConstructor } from '@/lib/paystack';
import { loadPaystack, messageFrom } from '@/lib/paystack';
import { show as orderShow } from '@/routes/orders';
import { start, verify } from '@/routes/payment';

/**
 * Paying for an order that already exists.
 *
 * Deliberately its own page rather than the last step of checkout: the order is
 * placed and owed the moment the shopper presses "place order", so paying is a
 * separate act they can abandon, retry tomorrow, or reach from a link in an
 * email. Nothing on this page can change what is owed — it shows the frozen
 * order and takes money against it.
 *
 * The gateway runs in Paystack's own popup, which means the page must survive
 * the whole exchange. Opening a transaction is therefore a plain JSON call
 * (`useHttp`, not a visit): an Inertia visit would tear this component down and
 * take the popup's callbacks with it. Only the confirmation at the end is a
 * visit, because by then the server has somewhere else to send us.
 *
 * A reference from the popup is a claim, never a receipt. `payment.verify` asks
 * Paystack what actually happened; this page just carries the claim there.
 */
const { order, paystackEnabled, bankTransferEnabled, bankDetails } =
    defineProps<{
        order: App.Data.OrderData;
        paystackEnabled: boolean;
        bankTransferEnabled: boolean;
        bankDetails: string;
        breadcrumbs: App.Data.BreadcrumbData[];
    }>();

/**
 * Where the shopper is in the exchange.
 *
 * One value rather than a handful of booleans, because the states are strictly
 * sequential and the button reads exactly one of them: the popup being open is
 * indistinguishable from the request that opened it, and both must keep the
 * button disabled so a second transaction is never started against one order.
 */
type Phase = 'idle' | 'opening' | 'confirming';

const phase = ref<Phase>('idle');

/** Nothing went wrong; the shopper simply did not pay. Said quietly. */
const notice = ref<string | null>(null);

/** Something did go wrong, and the shopper needs to know before retrying. */
const failure = ref<string | null>(null);

const GATEWAY_UNREACHABLE =
    'We could not reach the payment provider. Please try again in a moment.';
const WINDOW_UNAVAILABLE =
    'The secure payment window could not be opened. Check your connection and try again.';
const POPUP_FAILED =
    'The payment could not be completed. Nothing has been charged.';
const NOTHING_CHARGED =
    'You closed the payment window, so nothing has been charged. Your order is still here whenever you are ready.';
const CONFIRMATION_FAILED =
    'We could not confirm that payment. If you were charged, do not pay again — the confirmation will follow shortly.';

/**
 * The JSON leg: opens a transaction server side and hands back its access code.
 *
 * `useHttp` is the v3 replacement for reaching for axios (which the adapter no
 * longer ships). It uses the same XHR client as every other Inertia request, so
 * the XSRF cookie is attached for us, but it does not touch the page — which is
 * the entire point of using it here.
 */
const gateway = useHttp<Record<string, never>, { accessCode: string }>({});

const bankInstructions = computed(() => bankDetails.trim());

const showsBankTransfer = computed(
    () => bankTransferEnabled && bankInstructions.value !== '',
);

const busy = computed(() => phase.value !== 'idle');

const buttonLabel = computed(() => {
    if (phase.value === 'confirming') {
        return 'Confirming payment';
    }

    return phase.value === 'opening'
        ? 'Opening secure payment'
        : `Pay ${order.totals.totalFormatted}`;
});

/**
 * Warm the script up while the shopper is reading the page.
 *
 * Fetching it here rather than in the document head keeps third-party code off
 * every other page of the storefront; fetching it on mount rather than on the
 * press means the popup opens in the same gesture that asked for it. A failure
 * is swallowed — the loader forgets it, and pressing the button tries again.
 */
onMounted(() => {
    if (!paystackEnabled) {
        return;
    }

    void loadPaystack().catch(() => {
        // Reported at the point of use, not before anyone has asked to pay.
    });
});

/** Open a transaction and return its access code, or null having explained. */
async function openTransaction(): Promise<string | null> {
    try {
        const opened = await gateway.submit(start(order.orderNumber), {
            // The server phrases 502 (gateway down) and 409 (already paid) for
            // the shopper, so those messages are shown as they arrive.
            onHttpException: (response: { data: unknown }) => {
                failure.value = messageFrom(response.data, GATEWAY_UNREACHABLE);
            },
        });

        return opened?.accessCode ?? null;
    } catch {
        return null;
    }
}

async function pay(): Promise<void> {
    if (busy.value) {
        return;
    }

    notice.value = null;
    failure.value = null;
    phase.value = 'opening';

    let Paystack: PaystackPopConstructor;

    try {
        Paystack = await loadPaystack();
    } catch {
        phase.value = 'idle';
        failure.value = WINDOW_UNAVAILABLE;

        return;
    }

    const accessCode = await openTransaction();

    if (accessCode === null) {
        phase.value = 'idle';
        failure.value ??= GATEWAY_UNREACHABLE;

        return;
    }

    new Paystack().resumeTransaction(accessCode, {
        onSuccess: (transaction) => {
            phase.value = 'confirming';

            // The one Inertia visit on this page: the server checks the
            // reference with Paystack and redirects to wherever the answer
            // leaves the order.
            router.post(
                verify(order.orderNumber),
                { reference: transaction.reference },
                {
                    onError: () => {
                        phase.value = 'idle';
                        failure.value = CONFIRMATION_FAILED;
                    },
                },
            );
        },
        onCancel: () => {
            phase.value = 'idle';
            notice.value = NOTHING_CHARGED;
        },
        onError: (error) => {
            phase.value = 'idle';
            failure.value = messageFrom(error, POPUP_FAILED);
        },
    });
}
</script>

<template>
    <Head :title="`Pay for ${order.orderNumber}`" />

    <div class="container flex flex-col gap-16 py-8">
        <section aria-labelledby="payment-heading">
            <StoreBreadcrumbs :items="breadcrumbs" />

            <div
                class="mt-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-3"
            >
                <div>
                    <span
                        class="bg-electric block h-0.5 w-8"
                        aria-hidden="true"
                    />
                    <h1
                        id="payment-heading"
                        class="font-display text-foreground mt-3 text-2xl font-black tracking-[-0.035em] uppercase sm:text-4xl"
                    >
                        Pay for {{ order.orderNumber }}
                    </h1>
                    <p class="text-muted-foreground mt-2 text-sm">
                        Your order is placed and waiting. Nothing else changes
                        until it is paid for.
                    </p>
                </div>

                <Link
                    :href="orderShow(order.orderNumber)"
                    class="font-display text-electric hover:border-electric focus-visible:outline-electric inline-flex items-center gap-1.5 border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Back to the order
                </Link>
            </div>

            <div
                class="mt-10 grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_20rem]"
            >
                <div class="flex min-w-0 flex-col gap-10">
                    <section
                        v-if="paystackEnabled"
                        aria-labelledby="payment-gateway-heading"
                        class="bg-card rounded-xs p-6"
                    >
                        <h2
                            id="payment-gateway-heading"
                            class="font-display text-foreground flex items-center gap-2 text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            <CreditCard class="size-5" aria-hidden="true" />
                            Card or mobile money
                        </h2>
                        <p class="text-muted-foreground mt-2 text-sm">
                            Paying opens a secure window from our payment
                            provider. Your card and M-Pesa details are entered
                            there and never reach us.
                        </p>

                        <div class="mt-6">
                            <p
                                class="text-muted-foreground text-[0.625rem] font-semibold tracking-[0.14em] uppercase"
                            >
                                Amount due
                            </p>
                            <div class="mt-1">
                                <Price
                                    size="lg"
                                    :formatted="order.totals.totalFormatted"
                                />
                            </div>
                        </div>

                        <button
                            type="button"
                            :disabled="busy"
                            class="bg-ink font-display focus-visible:outline-electric mt-6 flex h-11 w-full items-center justify-center gap-2 rounded-xs text-sm font-bold tracking-[0.08em] text-white uppercase transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto sm:px-8"
                            @click="pay"
                        >
                            <Loader2
                                v-if="busy"
                                class="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                            {{ buttonLabel }}
                        </button>

                        <p
                            v-if="failure"
                            role="alert"
                            class="border-destructive/40 bg-destructive/5 text-foreground mt-4 flex items-start gap-3 rounded-xs border px-4 py-3 text-sm"
                        >
                            <TriangleAlert
                                class="text-destructive mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>{{ failure }}</span>
                        </p>

                        <p
                            v-else-if="notice"
                            role="status"
                            class="text-muted-foreground mt-4 text-sm leading-relaxed"
                        >
                            {{ notice }}
                        </p>

                        <p
                            class="text-muted-foreground mt-6 flex items-start gap-2 text-xs leading-relaxed"
                        >
                            <Lock
                                class="mt-0.5 size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            <span>
                                You will only ever be charged
                                <span class="tabular-nums">
                                    {{ order.totals.totalFormatted }}
                                </span>
                                for this order. Closing the window charges you
                                nothing.
                            </span>
                        </p>
                    </section>

                    <section
                        v-else-if="showsBankTransfer"
                        aria-labelledby="payment-bank-heading"
                        class="bg-card rounded-xs p-6"
                    >
                        <h2
                            id="payment-bank-heading"
                            class="font-display text-foreground flex items-center gap-2 text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            <Landmark class="size-5" aria-hidden="true" />
                            Pay by bank transfer
                        </h2>
                        <p class="text-muted-foreground mt-2 text-sm">
                            Transfer
                            <span class="text-foreground font-medium">
                                {{ order.totals.totalFormatted }}
                            </span>
                            to the account below and quote
                            <span class="text-foreground font-medium">
                                {{ order.orderNumber }}
                            </span>
                            as the reference. We will confirm as soon as it
                            lands.
                        </p>

                        <p
                            class="border-rule text-foreground mt-4 rounded-xs border border-dashed p-4 text-sm leading-relaxed whitespace-pre-line"
                        >
                            {{ bankInstructions }}
                        </p>
                    </section>

                    <section
                        v-else
                        aria-labelledby="payment-contact-heading"
                        class="bg-card rounded-xs p-6"
                    >
                        <h2
                            id="payment-contact-heading"
                            class="font-display text-foreground flex items-center gap-2 text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            <Mail class="size-5" aria-hidden="true" />
                            Paying for this order
                        </h2>
                        <p class="text-muted-foreground mt-2 text-sm">
                            Online payment is not available right now. Get in
                            touch quoting
                            <span class="text-foreground font-medium">
                                {{ order.orderNumber }}
                            </span>
                            and we will tell you how to settle it. Your order is
                            held either way.
                        </p>
                    </section>

                    <section aria-labelledby="payment-items-heading">
                        <h2
                            id="payment-items-heading"
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            What you are paying for
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
                    aria-labelledby="payment-summary-heading"
                >
                    <span
                        class="bg-ink block h-0.5 w-full"
                        aria-hidden="true"
                    />

                    <div class="flex flex-col gap-5 p-6">
                        <h2
                            id="payment-summary-heading"
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            Summary
                        </h2>

                        <CheckoutSummary :totals="order.totals" />

                        <p
                            class="bg-accent text-accent-foreground rounded-xs px-3 py-2 text-xs leading-relaxed"
                        >
                            This is the total the order was placed at. It does
                            not change while it waits to be paid.
                        </p>

                        <Link
                            :href="orderShow(order.orderNumber)"
                            class="font-display text-electric hover:border-electric focus-visible:outline-electric self-start border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                        >
                            View the full order
                        </Link>
                    </div>
                </section>
            </div>
        </section>
    </div>
</template>
