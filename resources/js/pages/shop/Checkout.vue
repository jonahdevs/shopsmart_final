<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, ShieldCheck, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CheckoutAddressFields from '@/components/storefront/CheckoutAddressFields.vue';
import CheckoutAddressPicker from '@/components/storefront/CheckoutAddressPicker.vue';
import CheckoutCouponForm from '@/components/storefront/CheckoutCouponForm.vue';
import CheckoutDeliveryPicker from '@/components/storefront/CheckoutDeliveryPicker.vue';
import CheckoutLineItem from '@/components/storefront/CheckoutLineItem.vue';
import CheckoutSummary from '@/components/storefront/CheckoutSummary.vue';
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
const { quote, addresses, deliveryMethod } = defineProps<{
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
</script>

<template>
    <Head title="Checkout" />

    <div class="flex flex-col gap-16 px-4 py-8 sm:px-6 lg:px-8">
        <section aria-labelledby="checkout-heading">
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
                        id="checkout-heading"
                        class="font-display text-foreground mt-3 text-2xl font-black tracking-[-0.035em] uppercase sm:text-4xl"
                    >
                        Checkout
                    </h1>
                    <p class="text-muted-foreground mt-2 text-sm tabular-nums">
                        {{ quote.lines.length }}
                        {{ quote.lines.length === 1 ? 'line' : 'lines' }}, ready
                        to place.
                    </p>
                </div>

                <Link
                    :href="cartIndex()"
                    class="font-display text-electric hover:border-electric focus-visible:outline-electric inline-flex items-center gap-1.5 border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
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
                class="border-destructive/40 bg-destructive/5 mt-8 flex items-start gap-3 rounded-xs border px-4 py-3"
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
                        class="text-electric hover:border-electric focus-visible:outline-electric mt-2 inline-block border-b border-transparent text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                    >
                        Fix it in your cart
                    </Link>
                </div>
            </div>

            <div
                class="mt-10 grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_20rem]"
            >
                <div class="flex min-w-0 flex-col gap-10">
                    <Form
                        v-bind="store.form()"
                        :options="{ preserveScroll: true, preserveState: true }"
                        v-slot="{ errors }"
                        id="checkout-form"
                        class="flex flex-col gap-10"
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
                            class="border-destructive/40 bg-destructive/5 flex items-start gap-3 rounded-xs border px-4 py-3"
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
                            <h2
                                id="checkout-delivery-heading"
                                class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                            >
                                How you get it
                            </h2>

                            <div class="mt-4">
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
                            <h2
                                id="checkout-address-heading"
                                class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                            >
                                Delivery address
                            </h2>

                            <div class="mt-4">
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
                        class="border-rule flex flex-col gap-5 rounded-xs border border-dashed p-5"
                    >
                        <div>
                            <h2
                                class="font-display text-foreground text-sm font-black tracking-[0.02em] uppercase"
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
                            class="border-ink hover:bg-ink font-display focus-visible:outline-electric text-foreground h-10 w-fit rounded-xs border px-5 text-sm font-bold tracking-wide transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                        >
                            Save this address
                        </button>
                    </Form>

                    <section aria-labelledby="checkout-note-heading">
                        <h2
                            id="checkout-note-heading"
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            Anything we should know?
                        </h2>

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
                            class="border-rule text-foreground placeholder:text-muted-foreground focus-visible:outline-electric mt-4 w-full rounded-xs border bg-white px-3 py-2 text-sm focus-visible:outline-2 focus-visible:-outline-offset-2"
                        />
                    </section>

                    <section aria-labelledby="checkout-payment-heading">
                        <h2
                            id="checkout-payment-heading"
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            How you pay
                        </h2>
                        <p class="text-muted-foreground mt-2 text-sm">
                            Nothing is charged now. Place the order and we will
                            take you through payment on the order itself.
                        </p>

                        <ul
                            v-if="paymentMethods.length > 0"
                            class="mt-4 flex flex-col gap-3"
                        >
                            <li
                                v-for="method in paymentMethods"
                                :key="method.value"
                                class="border-rule rounded-xs border p-4"
                            >
                                <p class="text-foreground text-sm font-medium">
                                    {{ method.label }}
                                </p>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    {{ method.description }}
                                </p>
                            </li>
                        </ul>
                    </section>

                    <section aria-labelledby="checkout-items-heading">
                        <h2
                            id="checkout-items-heading"
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            What you are buying
                        </h2>

                        <ul
                            class="border-rule divide-rule mt-4 divide-y border-t"
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
                    class="bg-card rounded-xs lg:sticky lg:top-28"
                    aria-labelledby="checkout-summary-heading"
                >
                    <span
                        class="bg-ink block h-0.5 w-full"
                        aria-hidden="true"
                    />

                    <div class="flex flex-col gap-5 p-6">
                        <h2
                            id="checkout-summary-heading"
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
                        >
                            Summary
                        </h2>

                        <CheckoutSummary :totals="quote.totals" />

                        <p
                            v-if="quote.freeShippingRemainingFormatted"
                            class="bg-accent text-accent-foreground rounded-xs px-3 py-2 text-xs leading-relaxed"
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
                            class="bg-ink font-display focus-visible:outline-electric flex h-11 w-full items-center justify-center gap-2 rounded-xs text-sm font-bold tracking-[0.08em] text-white uppercase transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
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
