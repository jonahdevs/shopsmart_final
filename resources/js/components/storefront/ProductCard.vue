<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Price from '@/components/storefront/Price.vue';
import Rating from '@/components/storefront/Rating.vue';
import { show } from '@/routes/product';

/**
 * The catalog's repeating unit.
 *
 * Everything here is deliberately quiet so the price block carries the
 * identity: no card border, no shadow, photography sitting straight on white.
 * The only flourish is a pinstripe that draws itself under the card on hover,
 * echoing the header's category rule.
 */
const { product, eager = false } = defineProps<{
    product: App.Data.ProductCardData;
    /** Set on the first row of a grid so above-the-fold art is not lazy-loaded. */
    eager?: boolean;
}>();
</script>

<template>
    <article class="group relative">
        <Link
            :href="show(product.slug)"
            class="block rounded-xs outline-offset-4 focus-visible:outline-2 focus-visible:outline-electric"
        >
            <div
                class="relative aspect-square overflow-hidden rounded-xs bg-white"
                :style="
                    product.image?.placeholder
                        ? {
                              backgroundImage: `url(${product.image.placeholder})`,
                              backgroundSize: 'cover',
                          }
                        : undefined
                "
            >
                <picture v-if="product.image">
                    <source
                        v-if="product.image.webpUrl"
                        :srcset="product.image.webpUrl"
                        type="image/webp"
                    />
                    <img
                        :src="product.image.url"
                        :alt="product.image.alt"
                        :loading="eager ? 'eager' : 'lazy'"
                        :fetchpriority="eager ? 'high' : 'auto'"
                        decoding="async"
                        class="size-full object-contain transition-transform duration-500 ease-out group-hover:scale-[1.04]"
                    />
                </picture>

                <div
                    v-if="!product.inStock"
                    class="absolute inset-x-0 bottom-0 bg-ink/85 py-1 text-center font-display text-[0.625rem] font-bold tracking-[0.14em] text-white uppercase"
                >
                    Out of stock
                </div>
            </div>

            <div class="mt-3 space-y-1.5">
                <p
                    v-if="product.brandName"
                    class="font-display text-[0.6875rem] font-bold tracking-[0.14em] text-muted-foreground uppercase"
                >
                    {{ product.brandName }}
                </p>
                <!-- Two lines reserved so prices align across the row. -->
                <h3
                    class="line-clamp-2 min-h-10 text-sm leading-5 text-foreground"
                >
                    {{ product.name }}
                </h3>

                <Rating
                    :average="product.ratingAverage"
                    :count="product.ratingCount"
                />

                <!--
                  The struck-through original only appears when there is a real
                  discount; otherwise the two formatted prices are identical and
                  showing both would be noise.
                -->
                <Price
                    size="sm"
                    :formatted="product.effectivePriceFormatted"
                    :compare-formatted="
                        product.isOnSale ? product.priceFormatted : null
                    "
                    :discount-percent="product.discountPercent"
                />
            </div>
        </Link>

        <span
            aria-hidden="true"
            class="mt-3 block h-0.5 w-0 bg-electric transition-[width] duration-300 ease-out group-hover:w-full motion-reduce:transition-none"
        />
    </article>
</template>
