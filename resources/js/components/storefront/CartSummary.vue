<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import Price from '@/components/storefront/Price.vue';
import { catalog } from '@/routes';

/**
 * The money panel.
 *
 * The subtotal is the sum of the captured line prices and nothing else —
 * delivery, tax and coupons belong to checkout, which is a later phase, so the
 * panel says that out loud instead of implying this is the amount due.
 */
defineProps<{ cart: App.Data.CartData }>();
</script>

<template>
    <section class="bg-card rounded-xs" aria-labelledby="cart-summary-heading">
        <span class="bg-ink block h-0.5 w-full" aria-hidden="true" />

        <div class="flex flex-col gap-5 p-6">
            <h2
                id="cart-summary-heading"
                class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase"
            >
                Summary
            </h2>

            <div class="flex items-end justify-between gap-4">
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
                Delivery and taxes are worked out at checkout, so this is the
                goods total only.
            </p>

            <!--
              Checkout is Phase 4. The button is present and plainly disabled
              rather than absent, because a cart with no visible next step reads
              as broken rather than as unfinished.
            -->
            <button
                type="button"
                disabled
                class="bg-ink font-display flex h-11 w-full items-center justify-center gap-2 rounded-xs text-sm font-bold tracking-[0.08em] text-white uppercase disabled:cursor-not-allowed disabled:opacity-40"
            >
                Checkout
                <ArrowRight class="size-4" aria-hidden="true" />
            </button>
            <p class="text-muted-foreground -mt-3 text-xs">
                Checkout opens in the next phase of the build.
            </p>

            <Link
                :href="catalog()"
                class="font-display text-electric hover:border-electric focus-visible:outline-electric self-start border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                Keep shopping
            </Link>
        </div>
    </section>
</template>
