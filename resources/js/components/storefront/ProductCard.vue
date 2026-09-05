<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Price from '@/components/storefront/Price.vue';
import Rating from '@/components/storefront/Rating.vue';
import SavedToggleButton from '@/components/storefront/SavedToggleButton.vue';
import { show } from '@/routes/product';

/**
 * The catalog's repeating unit.
 *
 * A white card on the white page, held apart from it by a hairline `--rule`
 * border and `shadow-card`, which deepens to `shadow-card-hover` when the card
 * is hovered or holds focus. The artwork sits on generous white padding so
 * photography shot on different backgrounds still reads as one shelf, and the
 * two things a shopper scans for — the discount and the price — are the only
 * coloured marks on the tile.
 */
const {
    product,
    eager = false,
    savable = true,
} = defineProps<{
    product: App.Data.ProductCardData;
    /** Set on the first row of a grid so above-the-fold art is not lazy-loaded. */
    eager?: boolean;
    /**
     * Turned off where the surrounding tile already offers the same action —
     * the wishlist grid hangs its own remove control under each card, and two
     * wishlist controls on one tile is a puzzle, not an affordance.
     */
    savable?: boolean;
}>();
</script>

<template>
    <article
        class="group border-rule shadow-card hover:shadow-card-hover focus-within:shadow-card-hover relative flex h-full flex-col rounded-lg border bg-white transition-shadow duration-200"
    >
        <Link
            :href="show(product.slug)"
            class="focus-visible:outline-electric flex flex-1 flex-col rounded-lg outline-offset-2 focus-visible:outline-2"
        >
            <div
                class="relative aspect-square overflow-hidden rounded-t-lg bg-white p-4 sm:p-5"
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
                        class="size-full object-contain transition-transform duration-500 ease-out group-hover:scale-[1.04] motion-reduce:transition-none"
                    />
                </picture>

                <div
                    v-if="!product.inStock"
                    class="bg-ink/85 font-display absolute inset-x-0 bottom-0 py-1 text-center text-[0.625rem] font-bold tracking-[0.14em] text-white uppercase"
                >
                    Out of stock
                </div>
            </div>

            <div class="flex flex-1 flex-col gap-1.5 px-4 pt-1 pb-4">
                <p
                    v-if="product.brandName"
                    class="text-muted-foreground truncate text-xs"
                >
                    {{ product.brandName }}
                </p>
                <!-- Lines reserved so prices align across the row. -->
                <h3
                    class="text-ink line-clamp-3 min-h-10 text-sm leading-5 font-medium"
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
                  showing both would be noise. The percentage itself is not
                  repeated here — it is already the pill on the artwork.
                -->
                <div class="mt-auto pt-0.5">
                    <Price
                        size="sm"
                        :formatted="product.effectivePriceFormatted"
                        :compare-formatted="
                            product.isOnSale ? product.priceFormatted : null
                        "
                    />
                </div>
            </div>
        </Link>

        <!-- Discount and save both sit outside the <Link>: a <form> nested in an
             <a> is invalid markup, and the pill must not be part of its label. -->
        <span
            v-if="product.isOnSale && product.discountPercent"
            class="bg-sale font-display pointer-events-none absolute top-3 left-3 rounded-full px-2 py-0.5 text-[0.6875rem] leading-4 font-bold text-white tabular-nums"
        >
            &minus;{{ product.discountPercent }}%
        </span>

        <SavedToggleButton
            v-if="savable"
            :product-id="product.id"
            list="wishlist"
            icon-only
            class="absolute top-3 right-3"
        />
    </article>
</template>
