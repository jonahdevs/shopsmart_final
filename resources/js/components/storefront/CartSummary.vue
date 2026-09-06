<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import Price from '@/components/storefront/Price.vue';
import { catalog } from '@/routes';
import { index as checkout } from '@/routes/checkout';

/**
 * The money panel.
 *
 * The subtotal is the sum of the captured line prices and nothing else —
 * delivery, tax and coupons are worked out by the checkout pricer, so the panel
 * says that out loud instead of implying this is the amount due.
 *
 * It is the storefront's card, and the one blue button on the page: checkout is
 * the single primary action here, so nothing else beside it is filled.
 */
defineProps<{ cart: App.Data.CartData }>();
</script>

<template>
    <section
        class="border-rule shadow-card rounded-lg border bg-white"
        aria-labelledby="cart-summary-heading"
    >
        <div class="flex flex-col gap-5 p-6">
            <h2
                id="cart-summary-heading"
                class="font-display text-ink text-xl font-extrabold tracking-[-0.02em]"
            >
                Summary
            </h2>

            <div
                class="border-rule flex items-end justify-between gap-4 border-t pt-5"
            >
                <p class="text-muted-foreground text-sm">
                    Subtotal
                    <span class="tabular-nums">
                        ({{ cart.itemCount }}
                        {{ cart.itemCount === 1 ? 'item' : 'items' }})
                    </span>
                </p>
                <Price size="md" :formatted="cart.subtotalFormatted" />
            </div>

            <p class="text-muted-foreground text-xs leading-relaxed">
                This is the goods total only. Delivery and taxes are worked out
                at checkout, on the next page.
            </p>

            <!--
              The same link for guests and signed-in shoppers: checkout sits
              behind the auth middleware, which parks the intended URL and
              returns them here-onwards after they sign in. Branching on auth
              state would only duplicate that, and get it wrong.
            -->
            <Link
                :href="checkout()"
                class="bg-electric font-display focus-visible:outline-electric flex h-11 w-full items-center justify-center gap-2 rounded-lg text-sm font-bold text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
            >
                Checkout
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>

            <Link
                :href="catalog()"
                class="font-display text-electric focus-visible:outline-electric self-start rounded-sm text-sm font-bold transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                Keep shopping
            </Link>
        </div>
    </section>
</template>
