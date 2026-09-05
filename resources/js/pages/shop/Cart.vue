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
 */
defineProps<{
    cart: App.Data.CartData;
    crossSells?: App.Data.ProductCardData[];
}>();
</script>

<template>
    <Head title="Your cart" />

    <div class="container flex flex-col gap-16 py-8">
        <section aria-labelledby="cart-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <span
                        class="bg-electric block h-0.5 w-8"
                        aria-hidden="true"
                    />
                    <h1
                        id="cart-heading"
                        class="font-display text-foreground mt-3 text-2xl font-black tracking-[-0.035em] uppercase sm:text-4xl"
                    >
                        Your cart
                    </h1>
                    <p class="text-muted-foreground mt-2 text-sm tabular-nums">
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
                        class="text-muted-foreground hover:text-destructive focus-visible:outline-electric rounded-xs text-sm underline underline-offset-4 transition-colors focus-visible:outline-2 focus-visible:outline-offset-4 disabled:opacity-50"
                    >
                        Empty the cart
                    </button>
                </Form>
            </div>

            <CartEmptyState v-if="cart.isEmpty" class="mt-10" />

            <div
                v-else
                class="mt-10 grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_20rem]"
            >
                <div class="flex min-w-0 flex-col gap-6">
                    <!--
                      Stated once at the top as well as per line, because a
                      shopper who scrolled straight to the subtotal would
                      otherwise never meet the explanation for it.
                    -->
                    <p
                        v-if="cart.hasPriceChanges"
                        class="bg-accent text-accent-foreground flex items-start gap-2 rounded-xs px-4 py-3 text-sm"
                    >
                        <TriangleAlert
                            class="mt-0.5 size-4 shrink-0"
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
                        <ul class="border-rule divide-rule divide-y border-t">
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
                title="Goes well with this"
                :products="crossSells ?? []"
            />
        </Deferred>
    </div>
</template>
