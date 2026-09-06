<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, ShieldCheck, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CheckoutAddressFields from '@/components/storefront/CheckoutAddressFields.vue';
import CheckoutAddressPicker from '@/components/storefront/CheckoutAddressPicker.vue';
import CheckoutCouponForm from '@/components/storefront/CheckoutCouponForm.vue';
import CheckoutDeliveryPicker from '@/components/storefront/CheckoutDeliveryPicker.vue';
import CheckoutLineItem from '@/components/storefront/CheckoutLineItem.vue';
import CheckoutPaymentPicker from '@/components/storefront/CheckoutPaymentPicker.vue';
import CheckoutSummary from '@/components/storefront/CheckoutSummary.vue';
import SectionHeading from '@/components/storefront/SectionHeading.vue';
import StoreBreadcrumbs from '@/components/storefront/StoreBreadcrumbs.vue';
import { store as storeAddress } from '@/routes/addresses';
import { index as cartIndex } from '@/routes/cart';
import { store } from '@/routes/checkout';

/**
 * The last page of the storefront.
 *
 * Two independent forms live here, and they cannot be nested: HTML forbids it.
 * The left column IS the order form (`#checkout-form`), the coupon has a form
 * of its own in the summary panel, and the place-order button reaches back into
 * the first through the HTML5 `form` attribute. Everything the order needs to
 * post is either inside that form or associated with it by name, which is also
 * how the order note manages to sit below the new-address panel.
 *
 * `quoted_total_cents` is the total this page displayed. It is a confirmation,
 * never a price: the server re-quotes the cart and refuses the order if the two
 * disagree, which is what stops a catalogue price that moved mid-checkout from
 * being charged silently.
 */
const { quote, addresses, deliveryMethod, paymentMethods } = defineProps<{
    quote: App.Data.CheckoutQuoteData;
    addresses: App.Data.AddressData[];
    deliveryMethod: App.Enums.DeliveryMethod;
    deliveryMethods: { value: string; label: string }[];
    pickupAddress: string;
    paymentMethods: { value: string; label: string; description: string }[];
    breadcrumbs: App.Data.BreadcrumbData[];
}>();

/** Collection needs no address, so the whole picker goes away with it. */
const requiresAddress = computed(() => deliveryMethod === 'delivery');

/** The default address if there is one, else the newest, else a fresh one. */
function preferredAddressId(): number | 'new' {
    const preferred =
        addresses.find((address) => address.isDefault) ?? addresses[0];

    return preferred?.id ?? 'new';
}

const selection = ref<number | 'new'>(preferredAddressId());

const usingNewAddress = computed(() => selection.value === 'new');

/**
 * A saved address comes back as a fresh render of this page, so the new one is
 * picked out by comparing the lists rather than by anything the address form
 * could tell us — the redirect is a `back()`, and its flashed id never reaches
 * the page as a prop.
 */
watch(
    () => addresses,
    (next, previous) => {
        const added = next.find(
            (address) => !previous.some((old) => old.id === address.id),
        );

        if (added?.id != null) {
            selection.value = added.id;

            return;
        }

        if (
            typeof selection.value === 'number' &&
            !next.some((address) => address.id === selection.value)
        ) {
            selection.value = preferredAddressId();
        }
    },
);

/**
 * The place-order button is outside the form it submits, so it cannot read the
 * form's `processing` slot prop — the form reports its own state instead.
 */
const placing = ref(false);

const blocked = computed(() => quote.blockers.length > 0);

/**
 * The store's first offered method, which is the one most shoppers want — the
 * server orders the list on purpose, online first.
 */
const paymentMethod = ref<string>(paymentMethods[0]?.value ?? '');

const page = usePage();

/**
 * The payment radios live outside `#checkout-form` in the markup, so they are
 * out of reach of its `errors` slot prop. The page's own error bag carries the
 * same message, and is what the picker is shown.
 */
const paymentMethodError = computed<string | undefined>(() => {
    const message: unknown = page.props.errors.payment_method;

    return typeof message === 'string' ? message : undefined;
});

/** Stated once so the heading and the list cannot disagree about the count. */
const lineCountLabel = computed(
    () =>
        `${quote.lines.length} ${quote.lines.length === 1 ? 'line' : 'lines'}, ready to place.`,
);
</script>

<template>
    <Head title="Checkout" />

    <div class="container flex flex-col gap-16 py-8">
        <section aria-labelledby="checkout-heading">
            <StoreBreadcrumbs :items="breadcrumbs" />

            <div
                class="mt-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-3"
            >
                <div>
                    <p
                        class="text-electric font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase"
                    >
                        Almost there
                    </p>
                    <h1
                        id="checkout-heading"
                        class="font-display text-ink mt-1 text-2xl font-extrabold tracking-[-0.03em] sm:text-4xl"
                    >
                        Checkout
                    </h1>
                    <p class="text-muted-foreground mt-2 text-sm tabular-nums">
                        {{ lineCountLabel }}
                    </p>
                </div>

                <Link
                    :href="cartIndex()"
                    class="text-electric focus-visible:outline-electric group inline-flex shrink-0 items-center gap-1.5 rounded-sm text-sm font-bold transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-4"
                >
                    <ArrowLeft
                        class="size-4 transition-transform group-hover:-translate-x-0.5 motion-reduce:transition-none"
                        aria-hidden="true"
                    />
                    Back to the cart
                </Link>
            </div>

            <!--
              Stated at the top of the page rather than beside the button: these
              are things that have to be fixed in the cart, and the button they
              disable is two screens down on a phone.
            -->
            <div
                v-if="blocked"
                role="alert"
                class="border-destructive/40 bg-destructive/5 mt-8 flex items-start gap-3 rounded-lg border px-4 py-3"
            >
                <TriangleAlert
                    class="text-destructive mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <div>
                    <p class="text-foreground text-sm font-medium">
                        This order cannot be placed yet
                    </p>
                    <ul
                        class="text-muted-foreground mt-1 list-disc space-y-1 pl-4 text-sm"
                    >
                        <li v-for="blocker in quote.blockers" :key="blocker">
                            {{ blocker }}
                        </li>
                    </ul>
                    <Link
                        :href="cartIndex()"
                        class="text-electric focus-visible:outline-electric mt-2 inline-block rounded-sm text-sm font-bold transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-4"
                    >
                        Fix it in your cart
                    </Link>
                </div>
            </div>

            <div
                class="mt-10 grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_20rem]"
            >
                <div class="flex min-w-0 flex-col gap-12">
                    <Form
                        v-bind="store.form()"
                        :options="{ preserveScroll: true, preserveState: true }"
                        v-slot="{ errors }"
                        id="checkout-form"
                        class="flex flex-col gap-12"
                        @start="placing = true"
                        @finish="placing = false"
                    >
                        <input
                            type="hidden"
                            name="quoted_total_cents"
                            :value="quote.totals.totalCents"
                        />

                        <div
                            v-if="Object.keys(errors).length > 0"
                            role="alert"
                            class="border-destructive/40 bg-destructive/5 flex items-start gap-3 rounded-lg border px-4 py-3"
                        >
                            <TriangleAlert
                                class="text-destructive mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <ul class="space-y-1 text-sm">
                                <li
                                    v-for="(message, field) in errors"
                                    :key="field"
                                    class="text-foreground"
                                >
                                    {{ message }}
                                </li>
                            </ul>
                        </div>

                        <section aria-labelledby="checkout-delivery-heading">
                            <SectionHeading
                                eyebrow="How it arrives"
                                title="How you get it"
                                heading-id="checkout-delivery-heading"
                            />

                            <div class="mt-6">
                                <CheckoutDeliveryPicker
                                    :methods="deliveryMethods"
                                    :selected="deliveryMethod"
                                    :pickup-address="pickupAddress"
                                />
                            </div>
                        </section>

                        <section
                            v-if="requiresAddress"
                            aria-labelledby="checkout-address-heading"
                        >
                            <SectionHeading
                                eyebrow="Where it goes"
                                title="Delivery address"
                                heading-id="checkout-address-heading"
                            />

                            <div class="mt-6">
                                <CheckoutAddressPicker
                                    v-model="selection"
                                    :addresses="addresses"
                                    :error="errors.address_id"
                                />
                            </div>
                        </section>
                    </Form>

                    <!--
                      A sibling of the order form, never a child of it: nested
                      forms are invalid HTML, and this one posts somewhere else.
                    -->
                    <Form
                        v-if="requiresAddress && usingNewAddress"
                        v-bind="storeAddress.form()"
                        :options="{ preserveScroll: true, preserveState: true }"
                        v-slot="{ errors, processing }"
                        class="border-rule flex flex-col gap-5 rounded-lg border border-dashed bg-white p-5"
                    >
                        <div>
                            <h2
                                class="font-display text-ink text-base font-extrabold tracking-[-0.02em]"
                            >
                                New delivery address
                            </h2>
                            <p class="text-muted-foreground mt-1 text-sm">
                                Save it to your address book, then place the
                                order.
                            </p>
                        </div>

                        <CheckoutAddressFields :errors="errors" />

                        <button
                            type="submit"
                            :disabled="processing"
                            class="border-ink hover:bg-ink font-display focus-visible:outline-electric text-foreground h-10 w-fit rounded-lg border px-5 text-sm font-bold tracking-wide transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                        >
                            Save this address
                        </button>
                    </Form>

                    <section aria-labelledby="checkout-note-heading">
                        <SectionHeading
                            eyebrow="Optional"
                            title="Anything we should know?"
                            heading-id="checkout-note-heading"
                        />

                        <!--
                          Outside the order form in the markup, part of it in the
                          browser: `form=` associates a control with a form it is
                          not nested in, which is what lets the new-address panel
                          above sit where it belongs without nesting two forms.
                        -->
                        <label for="checkout-note" class="sr-only">
                            Note for this order
                        </label>
                        <textarea
                            id="checkout-note"
                            form="checkout-form"
                            name="customer_note"
                            rows="3"
                            maxlength="1000"
                            placeholder="Delivery instructions, a landmark, the best time to call…"
                            class="border-rule shadow-card text-foreground placeholder:text-muted-foreground focus-visible:outline-electric mt-6 w-full rounded-lg border bg-white px-3 py-2 text-sm focus-visible:outline-2 focus-visible:-outline-offset-2"
                        />
                    </section>

                    <section aria-labelledby="checkout-payment-heading">
                        <SectionHeading
                            eyebrow="Settling up"
                            title="How you pay"
                            subtitle="Nothing is charged now. Place the order and we will take you through payment on the order itself."
                            heading-id="checkout-payment-heading"
                        />

                        <div class="mt-6">
                            <CheckoutPaymentPicker
                                v-model="paymentMethod"
                                :methods="paymentMethods"
                                form="checkout-form"
                                :error="paymentMethodError"
                            />
                        </div>
                    </section>

                    <section aria-labelledby="checkout-items-heading">
                        <SectionHeading
                            eyebrow="In your basket"
                            title="What you are buying"
                            heading-id="checkout-items-heading"
                        />

                        <ul
                            class="border-rule divide-rule shadow-card mt-6 divide-y rounded-lg border bg-white"
                        >
                            <CheckoutLineItem
                                v-for="(line, position) in quote.lines"
                                :key="`${line.productId}-${line.variantId}-${position}`"
                                :line="line"
                            />
                        </ul>
                    </section>
                </div>

                <section
                    class="border-rule shadow-card rounded-lg border bg-white lg:sticky lg:top-28"
                    aria-labelledby="checkout-summary-heading"
                >
                    <div class="flex flex-col gap-5 p-5 sm:p-6">
                        <h2
                            id="checkout-summary-heading"
                            class="font-display text-ink text-lg font-extrabold tracking-[-0.02em]"
                        >
                            Summary
                        </h2>

                        <CheckoutSummary :totals="quote.totals" />

                        <p
                            v-if="quote.freeShippingRemainingFormatted"
                            class="bg-tint-strong text-electric rounded-lg px-3 py-2 text-xs leading-relaxed"
                        >
                            Add
                            <span class="font-semibold tabular-nums">
                                {{ quote.freeShippingRemainingFormatted }}
                            </span>
                            more to your order and delivery is on us.
                        </p>

                        <div class="border-rule border-t pt-5">
                            <CheckoutCouponForm
                                :coupon-code="quote.totals.couponCode"
                            />
                        </div>

                        <button
                            type="submit"
                            form="checkout-form"
                            :disabled="blocked || placing"
                            class="bg-electric font-display focus-visible:outline-electric flex h-11 w-full items-center justify-center gap-2 rounded-lg text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <Loader2
                                v-if="placing"
                                class="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                            {{ placing ? 'Placing order' : 'Place order' }}
                        </button>

                        <p
                            v-if="!quote.meetsMinimum"
                            class="text-muted-foreground -mt-3 text-xs"
                        >
                            Orders start at
                            <span class="tabular-nums">{{
                                quote.minOrderValueFormatted
                            }}</span
                            >.
                        </p>

                        <p
                            class="text-muted-foreground flex items-start gap-2 text-xs leading-relaxed"
                        >
                            <ShieldCheck
                                class="mt-0.5 size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            <span>
                                Placing the order does not charge you. Prices
                                are confirmed against the catalogue one last
                                time when you press it.
                            </span>
                        </p>
                    </div>
                </section>
            </div>
        </section>
    </div>
</template>
