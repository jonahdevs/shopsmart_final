<script setup lang="ts">
import { Deferred, Form, Head } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import CartEmptyState from '@/components/storefront/CartEmptyState.vue';
import CartLineItem from '@/components/storefront/CartLineItem.vue';
import CartSummary from '@/components/storefront/CartSummary.vue';
import ProductRail from '@/components/storefront/ProductRail.vue';
import ProductRailSkeleton from '@/components/storefront/ProductRailSkeleton.vue';
import { clear } from '@/routes/cart';

/**
 * The cart.
 *
 * `crossSells` is deferred, so it is never dereferenced before it lands — the
 * `Deferred` wrapper holds a skeleton of the exact rail geometry until it does,
 * and it is only mounted for a cart with something in it, because the server
 * returns nothing to cross-sell against an empty one.
 *
 * The page title keeps the storefront's section rhythm — blue eyebrow, heavy
 * display line, muted subtitle — but stays an `<h1>` rather than composing
 * `SectionHeading`, which is an `<h2>`: this is the document's own title, and
 * the "empty the cart" control beside it is a form submit, not a "view all"
 * link.
 */
defineProps<{
    cart: App.Data.CartData;
    crossSells?: App.Data.ProductCardData[];
}>();
</script>

<template>
    <Head title="Your cart" />

    <div class="container flex flex-col gap-14 py-10">
        <section aria-labelledby="cart-heading">
            <div
                class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4"
            >
                <div>
                    <p
                        class="text-electric font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase"
                    >
                        Shopping
                    </p>
                    <h1
                        id="cart-heading"
                        class="font-display text-ink mt-0.5 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl"
                    >
                        Your cart
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm tabular-nums">
                        <template v-if="cart.isEmpty">
                            Nothing in your cart yet.
                        </template>
                        <template v-else>
                            {{ cart.lineCount }}
                            {{ cart.lineCount === 1 ? 'line' : 'lines' }},
                            {{ cart.itemCount }}
                            {{ cart.itemCount === 1 ? 'item' : 'items' }}
                        </template>
                    </p>
                </div>

                <Form
                    v-if="!cart.isEmpty"
                    v-bind="clear.form()"
                    v-slot="{ processing }"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="border-rule text-muted-foreground hover:border-destructive hover:text-destructive focus-visible:outline-electric rounded-lg border bg-white px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                    >
                        Empty the cart
                    </button>
                </Form>
            </div>

            <CartEmptyState v-if="cart.isEmpty" class="mt-8" />

            <div
                v-else
                class="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]"
            >
                <div class="flex min-w-0 flex-col gap-4">
                    <!--
                      Stated once at the top as well as per line, because a
                      shopper who scrolled straight to the subtotal would
                      otherwise never meet the explanation for it.
                    -->
                    <p
                        v-if="cart.hasPriceChanges"
                        class="bg-tint border-rule text-ink flex items-start gap-2.5 rounded-lg border px-4 py-3 text-sm"
                    >
                        <TriangleAlert
                            class="text-electric mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            Some catalogue prices have moved since you added
                            these. Your cart keeps the price each line was
                            opened at &mdash; the current one is shown against
                            the line.
                        </span>
                    </p>

                    <div>
                        <h2 class="sr-only">Items in your cart</h2>
                        <ul
                            class="border-rule shadow-card divide-rule divide-y rounded-lg border bg-white"
                        >
                            <CartLineItem
                                v-for="item in cart.items"
                                :key="item.key"
                                :item="item"
                            />
                        </ul>
                    </div>
                </div>

                <CartSummary :cart="cart" class="lg:sticky lg:top-28" />
            </div>
        </section>

        <Deferred v-if="!cart.isEmpty" data="crossSells">
            <template #fallback>
                <ProductRailSkeleton />
            </template>

            <ProductRail
                eyebrow="Recommended"
                title="Goes well with this"
                :products="crossSells ?? []"
            />
        </Deferred>
    </div>
</template>
